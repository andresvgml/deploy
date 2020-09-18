<?php

namespace App\CheckDefinitions;

use Spatie\Regex\Regex;
use Spatie\ServerMonitor\CheckDefinitions\CheckDefinition;
use Spatie\ServerMonitor\Models\Enums\CheckStatus;
use Symfony\Component\Process\Process;

class Diskspace extends CheckDefinition
{
    public $command = 'df -P -x"squashfs"';

    /**
     * Run every time a successful check will run again 1 day later
     *
     * @return integer
     */
    public function performNextRunInMinutes(): int
    {
        if ($this->check->hasStatus(CheckStatus::SUCCESS) || $this->check->hasStatus(CheckStatus::WARNING)) {
            return 1440;
        }
        return config('server-monitor.next_run_in_minutes');
    }

    public function resolve(Process $process)
    {
        $percentages = $this->getDiskUsagePercentage($process->getOutput());
		
        foreach ($percentages as $_p) {
			$percentage = $_p->group(1);
			
            $message = "disk usage at {$percentage}%";

            $thresholds = config('server-monitor.diskspace_percentage_threshold', [
                'warning' => 80,
                'fail'    => 90,
            ]);

            if ($percentage >= $thresholds['fail']) {
                $this->check->fail($message);
                continue;
            }

            if ($percentage >= $thresholds['warning']) {
                $this->check->warn($message);
                continue;
            }

            $this->check->succeed($message);
        }
    }

    protected function getDiskUsagePercentage(string $commandOutput)
    {
        return Regex::matchAll('/(\d?\d)%/', $commandOutput)->results();
    }
}
