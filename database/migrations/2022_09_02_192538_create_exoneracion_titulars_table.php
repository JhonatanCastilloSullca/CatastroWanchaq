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
        Schema::create('tf_exoneraciones_titular', function (Blueprint $table) {
            $table->string('id_ficha', 19)->unsigned();
            $table->foreign('id_ficha')->references('id_ficha')->on('tf_fichas')->onDelete('cascade')->onUpdate('cascade');
            $table->string('id_persona', 21)->unsigned();
            $table->foreign('id_persona')->references('id_persona')->on('personas')->onDelete('cascade')->onUpdate('cascade');
            $table->string('condicion', 2)->nullable();
            $table->string('nume_resolucion', 20)->nullable();
            $table->string('nume_boleta_pension', 20)->nullable();
            $table->date('fecha_inicio')->nullable();
            $table->date('fecha_vencimiento')->nullable();
        });
        
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('tf_exoneraciones_titular');
    }
};
