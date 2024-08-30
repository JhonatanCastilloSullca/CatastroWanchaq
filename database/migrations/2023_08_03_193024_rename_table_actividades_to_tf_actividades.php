<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::rename('actividades', 'tf_actividades');
    }

    public function down()
    {
        Schema::rename('tf_actividades', 'actividades');
    }
};
