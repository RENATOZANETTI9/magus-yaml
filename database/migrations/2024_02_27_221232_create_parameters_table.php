<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateParametersTable extends Migration
{
    public function up()
    {
        Schema::create('parameters', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('id_status')->nullable();
            $table->integer('ordem');
            $table->string('name', 255)->nullable(false)->unique();
            $table->string('message', 255);
            $table->string('timeout_retry', 255)->nullable();
            $table->text('action');
            $table->boolean('active')->default(1);
            $table->timestamps();
            $table->foreign('id_status')->references('id')->on('status');
        });
    }

    public function down()
    {
        Schema::dropIfExists('parameters');
    }
}
