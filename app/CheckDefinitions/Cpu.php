<?php

namespace App\CheckDefinitions;

use Symfony\Component\Process\Process;
use Spatie\ServerMonitor\CheckDefinitions\CheckDefinition;

class Cpu extends CheckDefinition
{
    public $command = "grep 'cpu ' /proc/stat | awk '{usage=($2+$4)*100/($2+$4+$5)} END {print usage \"%\"}'";

    public function resolve(Process $process)
    {
        $percentage = $this->getCpuUsagePercentage($process->getOutput());

        $message = "cpu usage at {$percentage}%";

        $thresholds = config('server-monitor.cpu_percentage_threshold', [
            'warning' => 80,
            'fail'    => 90,
        ]);

        if ($percentage >= $thresholds['fail']) {
            $this->check->fail($message);
            return;
        }

        if ($percentage >= $thresholds['warning']) {
            $this->check->warn($message);
            return;
        }

        $this->check->succeed($message);
    }

    protected function getCpuUsagePercentage(string $commandOutput): int
    {
        return (float) trim(preg_replace("/[^0-9]/", "", $commandOutput));
    }
}
