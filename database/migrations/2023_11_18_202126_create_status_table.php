<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateStatusTable extends Migration
{
    public function up()
    {
        Schema::create('status', function (Blueprint $table) {
            $table->unsignedTinyInteger('id')->autoIncrement(); // Cria uma coluna de ID autoincremento
            $table->string('name')->nullable(false)->unique(); // Cria uma coluna para o nome
            $table->timestamps(); // Cria colunas created_at e updated_at
        });
    }

    public function down()
    {
        Schema::dropIfExists('status');
    }
}

