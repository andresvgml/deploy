<?php

namespace App\CheckDefinitions;

use Symfony\Component\Process\Process;
use Spatie\ServerMonitor\CheckDefinitions\CheckDefinition;

class Memory extends CheckDefinition
{
    public $command = "grep 'Mem' /proc/meminfo";

    public function resolve(Process $process)
    {
        $data = $this->getMemoryTotals($process->getOutput());
        
        // Verifica que exista información
        if(!isset($data['MemTotal']) || !isset($data['MemFree'])){
            throw new \Exception("parameters not found", 1);
        }

        // Da formato a los valores de memoria
        $memory_total = (float) trim(preg_replace("/[^0-9]/", "", $data['MemTotal']));
        $memory_avaiable = (float) trim(preg_replace("/[^0-9]/", "", $data['MemFree']));

        $percentage = round((($memory_total - $memory_avaiable) * 100 / $memory_total), 2);

        $message = "memory usage at {$percentage}%";

        // Obtiene limites
        $thresholds = config('server-monitor.memory_percentage_threshold', [
            'warning' => 85,
            'fail'    => 95,
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

    protected function getMemoryTotals($commandOutput): array
    {
        $headers = [];
        foreach(explode("\n", $commandOutput) as $r){
            preg_match_all('/(.*?):\s?(.*?)(\r\n|$)/', $r, $matches);
            $headers += array_combine(
                array_map('trim', $matches[1]), 
                array_map('trim', $matches[2])
            );
        }
        return $headers;
    }
}
