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

        foreach ($allResults as $host => $data) {
            $target = $hostToTarget->get($host);
            if (!$target) {
                continue;
            }

            $inserts[] = [
                'target_id' => $target->id,
                'ts' => $now,
                'min_ms' => $data['min'] ?? null,
                'avg_ms' => $data['avg'] ?? null,
                'max_ms' => $data['max'] ?? null,
                'loss_pct' => $data['loss'] ?? null,
            ];
        }

        if (!empty($inserts)) {
            PingResult::insert($inserts);
            $count = count($inserts);
            $batchCount = count($batches);
            $msg = $batchCount > 1 
                ? sprintf('[%s] Polled %d targets in %d batches', $now->format('H:i:s'), $count, $batchCount)
                : sprintf('[%s] Polled %d targets', $now->format('H:i:s'), $count);
            $this->line($msg);
        }
    }

    private function runFping(array $hosts): array
    {
        $hostList = implode(' ', array_map('escapeshellarg', $hosts));
        $cmd = "{$this->fpingPath} -J -c 3 -q {$hostList} 2>&1";

        $output = [];
        $returnCode = 0;
        exec($cmd, $output, $returnCode);

        $results = [];

        foreach ($output as $line) {
            $json = json_decode($line, true);
            if (!$json) {
                continue;
            }

            if (isset($json['summary'])) {
                $summary = $json['summary'];
                $host = $summary['host'] ?? null;
                if (!$host) {
                    continue;
                }

                $xmt = $summary['xmt'] ?? 0;
                $rcv = $summary['rcv'] ?? 0;
                $lossPct = $xmt > 0 ? (($xmt - $rcv) / $xmt) * 100 : 100;

                $results[$host] = [
                    'min' => $summary['rttMin'] ?? null,
                    'avg' => $summary['rttAvg'] ?? null,
                    'max' => $summary['rttMax'] ?? null,
                    'loss' => $lossPct,
                ];
            }
        }

        return $results;
    }
}
