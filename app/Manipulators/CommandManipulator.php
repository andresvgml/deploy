<?php

namespace App\Manipulators;

use Spatie\ServerMonitor\Models\Check;
use Symfony\Component\Process\Process;
use Spatie\ServerMonitor\Manipulators\Manipulator;

class CommandManipulator implements Manipulator
{
    public function manipulateProcess(Process $process, Check $check): Process
    {
        // dd(encrypt("'"));
        $custom_properties = $check->host->custom_properties;
        // Get base command
        $regex = '/(?<=set \-e )[\s\S]*?(?=EOF\-)/';
        preg_match($regex, preg_replace( "/\r|\n/", " ", $process->getCommandLine()), $matches);
        $command = trim(array_shift($matches));
        
        // Pre-command
        $command = $this->get_custom_property('pre_command', $custom_properties) . " {$command}";
        // Post-command
        $command .= " " . $this->get_custom_property('post_command', $custom_properties);

        // Modify the command for verification of ssl certificates and domains
        if(in_array($check->type, ['certificate', 'domain'])){
            $command = sprintf($command, $check->host['name'], $check->host['name'], $check->host['name']);
        }
        return Process::fromShellCommandline($command);
    }

    /**
     * Get the host property
     *
     * @param string $key
     * @param array $properties
     * @return string
     */
    private function get_custom_property($key, $properties)
    {
        if(!isset($properties[$key]) || !$properties[$key]){
            return "";
        }
        return isset($properties['encrypt']) && $properties['encrypt'] ? decrypt($properties[$key]) : $properties[$key];
    }
}
