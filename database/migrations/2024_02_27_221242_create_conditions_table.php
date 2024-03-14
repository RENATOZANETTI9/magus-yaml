<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateConditionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('conditions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('id_parameter');
            $table->integer('ordem');
            $table->string('logical_operator')->nullable();
            $table->string('variable')->nullable();
            $table->string('operator')->nullable();
            $table->text('value');
            $table->text('custom_fields');
            $table->tinyInteger('active')->default(1);
            $table->timestamps();

            $table->foreign('id_parameter')->references('id')->on('parameters');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('conditions');
    }
}
