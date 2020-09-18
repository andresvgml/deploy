<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CopyLogs extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'copy:logs';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Copy remote logs files';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $date = date("Y-m-d");
        $separator = DIRECTORY_SEPARATOR;

        // Get the host
        $hosts = DB::table('hosts')
            ->select('name', 'custom_properties')
            ->whereNotNull('custom_properties->pre_command')
            ->whereNotNull('custom_properties->log_path')
            ->get();

        foreach ($hosts as $host) {
            $properties = json_decode($host->custom_properties);
            // Get the base command
            $command = $properties->pre_command;
            $log_path = $properties->log_path;
            if(isset($properties->encrypt) && $properties->encrypt){
                $command = decrypt($properties->pre_command);
                $log_path = decrypt($properties->log_path);
            }
            
            // Replace command 
            $command = str_replace("ssh ", "scp ", $command);
            $command = trim(str_replace("'cd /d C:\cygwin64\bin\ &&", "", $command));
            
            // Get log path(s)
            $paths = explode(",", $log_path);
            foreach ($paths as $path) {
                // Create folder
                $slug = count($paths) > 1 ? Str::slug($this->get_folder_name($path)) : Str::slug($host->name, "_");
                $folder = storage_path("logs/{$slug}");
                if(!is_dir($folder)){
                    mkdir($folder, 0777, true);
                }
                $command = preg_replace('#/+#','/', "{$command}:\"{$path}{$separator}*{$date}.log\" {$folder}");
                // Copy the log files
                exec($command);
                // Delete certificates
                exec("rm -rf *.pem");
            }
        }
        return 0;
    }

    private function get_folder_name($path)
    {
        $parts = preg_split("/\//", str_replace('\\', '/', $path));
        return (isset($parts[count($parts) - 3])) ? $parts[count($parts) - 3] : $parts[0];
    }
}
