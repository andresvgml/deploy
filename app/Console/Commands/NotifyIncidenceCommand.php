<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Laravel\Telescope\Contracts\EntriesRepository;
use Laravel\Telescope\IncomingEntry;
use Laravel\Telescope\Storage\EntryQueryOptions;
use Laravel\Telescope\EntryType;

use App\Notifications\Incidence;
use App\User;
use Notification;

class NotifyIncidenceCommand extends Command
{
    protected $storage;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'notify:incidence';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Notifica las incidencias encontradas';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct(EntriesRepository $storage)
    {
        parent::__construct();
        $this->storage = $storage;
    }
/*
    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        \DB::statement('
        DELETE FROM  telescope_entries
        WHERE TYPE IN ("view","cache", "schedule", "command", "event", "job", "log"); 
        ');
        \DB::statement('
        DELETE FROM  telescope_entries 
        WHERE TYPE = "query" AND content->>"$.slow" = "false" AND created_at > (UTC_TIMESTAMP() - INTERVAL 1 HOUR);
        ');
        \DB::statement('
        DELETE FROM telescope_entries 
        WHERE TYPE = "request" AND content->>"$.response_status"=200 AND created_at > (UTC_TIMESTAMP() - INTERVAL 1 HOUR);
        ');
        \DB::statement('
        DELETE FROM telescope_entries 
        WHERE  created_at < (CURDATE() - INTERVAL 15 DAY);
        ');
        
		// Borra entradas innecesarias 
		/*
        \DB::statement('
			DELETE a 
			FROM telescope_entries AS a 
			INNER JOIN ( 
				SELECT sequence from telescope_entries WHERE 
				TYPE IN ("view","cache", "schedule", "command", "event", "job", "log") OR 
				(type = "query" AND content LIKE \'%"slow":false,%\') OR 
				(type = "request" AND content LIKE \'%"response_status":200,%\') OR 
				DATE(created_at) < (curdate() - INTERVAL 15 DAY)
			) as b on a.sequence = b.sequence
		');
		*/
        
        // Actuliza entradas no notificables
        \DB::table('telescope_entries')
            ->whereIn('type', ["model", "query", "cache", "mail", "schedule", "view", "event", "command", "log"])
            ->where("notified", '0')
            ->update([ 'notified' => '1']);

        // Consulta entradas de incidencias
        $entries = $this->storage->get(null, EntryQueryOptions::forNotified(false));
        
        // Recorre entradas
        foreach ($entries as $entry) {
            $json = json_decode(json_encode($this->storage->find($entry->id)));

            $entry->content['level'] = $entry->content['level'] ?? null;

            // Crea instancia de 'IncomingEntry'
            $incomingEntry = new IncomingEntry($entry->content);
            $incomingEntry->type = $entry->type;
            $incomingEntry->tags = $entry->tags ?? null;
			$entry->_tags = $json->tags;

            if(
                $entry->type == EntryType::EXCEPTION || 
                $incomingEntry->isReportableException() || 
                $incomingEntry->isFailedRequest() || 
                $incomingEntry->isFailedJob() || 
                in_array(mb_strtolower($entry->content['level']), ['error', 'emergency', 'critical', 'alert'])
            ){
                // Ejecuta notificación
                Notification::send([User::find(1)], new Incidence($entry));
            }
            // Actualiza 'notified' en la entrada
            \DB::update('UPDATE telescope_entries SET notified = 1 WHERE uuid = ?', [$entry->id]);
        }
    }
}