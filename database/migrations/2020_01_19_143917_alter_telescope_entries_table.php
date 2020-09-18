<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AlterTelescopeEntriesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Agrega columna 'notified' en 'telescope_entries'
        if (Schema::hasTable('telescope_entries') && !Schema::hasColumn('telescope_entries', 'notified')) {
            Schema::table('telescope_entries', function (Blueprint $table) {
                $table->boolean('notified')->default(false);
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // Elimina columna 'notified' en 'telescope_entries'
        if (Schema::hasTable('telescope_entries') && Schema::hasColumn('telescope_entries', 'notified')) {
            Schema::table('telescope_entries', function (Blueprint $table){
                $table->dropColumn('notified');
            });
        }
    }
}
