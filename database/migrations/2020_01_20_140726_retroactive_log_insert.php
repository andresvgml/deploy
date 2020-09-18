<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Arr;
use \Illuminate\Support\Str;
use Illuminate\Support\Facades\Schema;

class RetroactiveLogInsert extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Uuids generados 
        $uuids = [];
        // Ruta log
        $log_path = '\storage\logs';
        // Secuencia inicial
        $sequence = 1000000;

        // Define las rutas a buscar
        $paths = [
            'D:\Signio.v03\apiAlertas',
            'D:\Signio.v03\apiSign',
            'D:\Signio.v03\identityApi',
            'D:\Signio.v03\Signio',
            'D:\WebApps\cumplimiento',
            'D:\WebApps\Gestion'
        ];

        // Recorre rutas
        foreach ($paths as $path) {
            if(is_dir($path . $log_path)){
                // Nombre del aplicativo
                $arr = explode("\\", $path);
                $app = Str::slug(end($arr));
                
                // Recorre todos los archivos de log
                $files = array_diff(scandir($path . $log_path), array('.', '..', '.gitignore'));
                foreach ($files as $file) {
                    // Genera batch_id
                    $batch_id = (string) Str::uuid();

                    // Borra los archivos en la carpeta log
                    $this->deleteLogs();

                    // Copia los archivo .log
                    \File::copy($path . $log_path . DIRECTORY_SEPARATOR . $file, storage_path("logs/$file"));

                    // LogViewer
                    $lc = new \Rap2hpoutre\LaravelLogViewer\LogViewerController();

                    // Serializa los logs
                    $logs = $lc->index()->getData();

                    // Recorre el array de logs
                    foreach ($logs['logs'] as $log) {
                        // uuid registro 
                        $uuid = (string) Str::uuid();

                        $uuids[] = $uuid;

                        // Guarda en la base de datos
                        DB::table('telescope_entries')->insert([
                            'sequence'                => $sequence++,
                            'uuid'                    => $uuid,
                            'batch_id'                => $batch_id,
                            'family_hash'             => null,
                            'should_display_on_index' => 1,
                            'type'                    => 'log',
                            'created_at'              => $log['date'],
                            'notified'                => 1,
                            'content'                 => json_encode([
                                "level"    => $log['level'],
                                "message"  => $log['text'],
                                "hostname" => gethostname(),
                                "context"  => [
                                    "xdebug_message" => $log['stack']
                                ]
                            ]),
                        ]);

                        // Agrega tag de aplicativo
                        DB::table('telescope_entries_tags')->insert([
                            'entry_uuid' => $uuid,
                            'tag'        => "application:$app",
                        ]);
                        
                        // Agrega tag de retroactivo
                        DB::table('telescope_entries_tags')->insert([
                            'entry_uuid' => $uuid,
                            'tag'        => 'log:retroactive',
                        ]);
                    }
                }
            }
        }

        // Reordena las secuencias
        if(count($uuids)){
            // Consulta el indice maximo indice
            $max = DB::select('SELECT MAX(sequence) AS num FROM telescope_entries WHERE type != ?;', ["log"]);

            // Consulta logs
            $logs = DB::table('telescope_entries')
                ->whereIn('uuid', $uuids)
                ->orderBy('created_at', 'asc')
                ->get();

            foreach ($logs as $keyL => $log) {
                DB::update("UPDATE telescope_entries SET sequence = ? WHERE uuid = ?", [$max[0]->num + ($keyL + 1), $log->uuid]);
            }
        }
    }

    private function deleteLogs()
    {
        $files = Arr::where(Storage::disk('log')->files(), function($filename) {
            return Str::endsWith($filename,'.log');
        });

        $count = count($files);

        if(Storage::disk('log')->delete($files)) {
            dump(sprintf('Eliminados %s %s!', $count, Str::plural('file', $count)));
        } else {
            dump("Error al eliminar archivos");
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
       // Elimina los logs migrados
       DB::delete('DELETE telescope_entries as te 
       INNER JOIN telescope_entries_tags ta ON ta.entry_uuid = te.uuid
       WHERE tag = ?', ["log:retroactive"]);
    }
}
