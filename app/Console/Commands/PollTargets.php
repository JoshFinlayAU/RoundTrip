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

    public function handle(): int
    {
        if (!file_exists($this->fpingPath)) {
            $this->error("fping not found at {$this->fpingPath}");
            return 1;
        }

        $runOnce = $this->option('once');

        $this->info('Starting RoundTrip poller...');

        do {
            $this->pollCycle();

            if (!$runOnce) {
                sleep(5);
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

        $results = $this->runFping($hosts);

        if (empty($results)) {
            return;
        }

        $now = Carbon::now();
        $inserts = [];

        foreach ($results as $host => $data) {
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
            $this->line(sprintf('[%s] Polled %d targets', $now->format('H:i:s'), count($inserts)));
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
