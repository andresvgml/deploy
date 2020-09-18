<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use PDO;

class DatabaseCreateCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'migrate:createdb';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'This command creates a new database';

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
     * @return mixed
     */
    public function handle()
    {
        $con      = config("database.default");
        $database = config("database.connections.$con.database");
        $config   = config("database.connections.$con");
        
        if (!$database) {
            throw new Exception("Varible de entorno DB_DATABASE vacia", 1);
        }

        $pdo = $this->getPDOConnection($config["host"], $config["port"], $config["username"], $config["password"]);

        if(!$pdo){
            throw new Exception("Imposible realizar la conexión al Host", 1);
        }

        $pdo->exec(sprintf(
            'CREATE DATABASE IF NOT EXISTS %s CHARACTER SET %s COLLATE %s;',
            $database,
            $config['charset'],
            $config['collation']
        ));

        \Log::debug('Base de datos creada satisfactoriamente');
    }

    /**
     * @param  string $host
     * @param  integer $port
     * @param  string $username
     * @param  string $password
     * @return PDO
     */
    private function getPDOConnection($host, $port, $username, $password)
    {
        return new PDO(sprintf('mysql:host=%s;port=%d;', $host, $port), $username, $password);
    }
}
