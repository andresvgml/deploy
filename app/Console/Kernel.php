<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        //
    ];

    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        // Delete records
        $schedule->command('telescope:prune')->daily();

        // Run incident verification
        $schedule->command('notify:incidence')
            ->withoutOverlapping()
            ->everyMinute();

        // Run server(s) checks
        $schedule->command('server-monitor:run-checks')
            ->withoutOverlapping()
            ->everyMinute();

        // Copy logs
        $schedule->command('copy:logs')
            ->withoutOverlapping()
            ->everyMinute();
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
