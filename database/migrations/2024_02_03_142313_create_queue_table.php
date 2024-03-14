<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateQueueTable extends Migration
{
    public function up()
    {
        Schema::create('queue', function (Blueprint $table) {
            $table->id();
            $table->string('identify', 11);
            $table->text('header');
            $table->string('referer', 255);
            $table->text('request');
            $table->text('response')->nullable();
            $table->integer('response_status')->default(0);
            $table->text('message');
            $table->unsignedTinyInteger('flag_process')->default(0);
            $table->timestamps();
            $table->timestamp('last_execution')->nullable();
            $table->timestamp('next_execution')->nullable();
            $table->string('next_timeout_retry')->nullable();
            
            $table->index('identify', 'queue_identify_index');
        });
    }

    public function down()
    {
        Schema::dropIfExists('queue');
    }
}
