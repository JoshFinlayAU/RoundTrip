<?php

namespace App\Console\Commands;

use App\Models\PingResult;
use App\Models\Target;
use Carbon\Carbon;
use Illuminate\Console\Command;

class PollTargets extends Command
{
    protected $signature = 'roundtrip:poll {--once : Run a single poll cycle and exit}';
    protected $description = 'Poll all enabled targets using fping';

    private string $fpingPath = '/usr/local/sbin/fping';
    private int $pollInterval = 5; // seconds
    private int $batchSize = 500; // max hosts per fping call

    public function handle(): int
    {
        if (!file_exists($this->fpingPath)) {
            $this->error("fping not found at {$this->fpingPath}");
            return 1;
        }

        $runOnce = $this->option('once');

        $this->info('Starting RoundTrip poller...');

        do {
            $startTime = microtime(true);
            
            $this->pollCycle();
            
            if (!$runOnce) {
                // Dynamic sleep - account for poll duration
                $elapsed = microtime(true) - $startTime;
                $sleepTime = max(0, $this->pollInterval - $elapsed);
                
                if ($sleepTime > 0) {
                    usleep((int)($sleepTime * 1000000));
                } else {
                    $this->warn(sprintf('Poll took %.1fs, exceeds %ds interval', $elapsed, $this->pollInterval));
                }
            }
        } while (!$runOnce);

        return 0;
    }

    private function pollCycle(): void
    {
        $targets = Target::query()
            ->where('enabled', true)
            ->get();

        if ($targets->isEmpty()) {
            $this->warn('No enabled targets configured');
            return;
        }

        $hostToTarget = $targets->keyBy('host');
        $hosts = $targets->pluck('host')->toArray();

        // Batch large target lists to avoid fping file descriptor limits
        $batches = array_chunk($hosts, $this->batchSize);
        $allResults = [];

        foreach ($batches as $batchHosts) {
            $results = $this->runFping($batchHosts);
            $allResults = array_merge($allResults, $results);
        }

        if (empty($allResults)) {
            return;
        }

        $now = Carbon::now();
        $inserts = [];

        foreach ($allResults as $host => $pings) {
            $target = $hostToTarget->get($host);
            if (!$target) {
                continue;
            }

            foreach ($pings as $ping) {
                $inserts[] = [
                    'target_id' => $target->id,
                    'ts' => $now,
                    'rtt_ms' => $ping['rtt'],
                    'seq' => $ping['seq'],
                    'lost' => $ping['lost'],
                ];
            }
        }

        if (!empty($inserts)) {
            PingResult::insert($inserts);
            $targetCount = count($allResults);
            $pingCount = count($inserts);
            $batchCount = count($batches);
            $msg = $batchCount > 1 
                ? sprintf('[%s] Polled %d targets (%d pings) in %d batches', $now->format('H:i:s'), $targetCount, $pingCount, $batchCount)
                : sprintf('[%s] Polled %d targets (%d pings)', $now->format('H:i:s'), $targetCount, $pingCount);
            $this->line($msg);
        }
    }

    private function runFping(array $hosts): array
    {
        $hostList = implode(' ', array_map('escapeshellarg', $hosts));
        // Remove -q to get individual ping responses, use 5 pings
        $cmd = "{$this->fpingPath} -J -c 5 {$hostList} 2>&1";

        $output = [];
        $returnCode = 0;
        exec($cmd, $output, $returnCode);

        // Track individual pings per host
        $results = [];
        $expectedSeqs = []; // Track which seqs we've seen per host

        foreach ($output as $line) {
            $json = json_decode($line, true);
            if (!$json) {
                continue;
            }

            // Individual ping response
            if (isset($json['resp'])) {
                $resp = $json['resp'];
                $host = $resp['host'] ?? null;
                if (!$host) continue;

                if (!isset($results[$host])) {
                    $results[$host] = [];
                    $expectedSeqs[$host] = [];
                }

                $seq = $resp['seq'] ?? 0;
                $expectedSeqs[$host][$seq] = true;
                
                $results[$host][] = [
                    'seq' => $seq,
                    'rtt' => $resp['rtt'] ?? null,
                    'lost' => false,
                ];
            }

            // Summary - use to detect lost packets
            if (isset($json['summary'])) {
                $summary = $json['summary'];
                $host = $summary['host'] ?? null;
                if (!$host) continue;

                $xmt = $summary['xmt'] ?? 5;
                
                if (!isset($results[$host])) {
                    $results[$host] = [];
                    $expectedSeqs[$host] = [];
                }

                // Add lost packets for any missing seqs
                for ($seq = 0; $seq < $xmt; $seq++) {
                    if (!isset($expectedSeqs[$host][$seq])) {
                        $results[$host][] = [
                            'seq' => $seq,
                            'rtt' => null,
                            'lost' => true,
                        ];
                    }
                }
            }
        }

        return $results;
    }
}
