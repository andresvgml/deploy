<?php

namespace App\CheckDefinitions;

use Symfony\Component\Process\Process;
use Spatie\ServerMonitor\CheckDefinitions\CheckDefinition;
use Spatie\ServerMonitor\Models\Enums\CheckStatus;
use Illuminate\Support\Carbon;

class Certificate extends CheckDefinition
{
    public $command = 'cd /d C:\cygwin64\bin\ && echo | openssl s_client -servername %s -connect %s:443 2>C:\TEMP\%s | openssl x509 -noout -dates';

    /**
     * Run every time a successful check will run again 1 day later
     *
     * @return integer
     */
    public function performNextRunInMinutes(): int
    {
        if ($this->check->hasStatus(CheckStatus::SUCCESS)) {
            return 1440;
        }
        return config('server-monitor.next_run_in_minutes');
    }

    /**
     * When executing a command on the server a timeout of 60 seconds will be used. 
     * If a command takes longer than that the check will be marked as failed
     *
     * @return integer
     */
    public function timeoutInSeconds(): int
    {
        return 60;
    }

    public function resolve(Process $process)
    {
        // Obtiene la fecha de expiración
        $expiration = $this->getExpirationDate($process->getOutput());
        $days = $expiration->diff(Carbon::now())->days;

        $message = "days to expire {$days}";

        // Verifica los dias de diferencia
        $thresholds = config('server-monitor.days_expiration_certificates_threshold', [
            'warning' => 5,
            'fail' => 1,
        ]);

        if ($days <= $thresholds['fail']) {
            $this->check->fail($message);
            return;
        }

        if ($days <= $thresholds['warning']) {
            $this->check->warn($message);
            return;
        }

        $this->check->succeed($message);
    }

    protected function getExpirationDate($commandOutput): Carbon
    {
        preg_match_all('/(.*?)=\s?(.*?)(\r\n|$)/', $commandOutput, $matches);
        $headers = array_combine(array_map('trim', $matches[1]), $matches[2]);
        return Carbon::parse($headers["notAfter"]) ?? Carbon::now();
    }
}
