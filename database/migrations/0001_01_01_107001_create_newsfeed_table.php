<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('newsfeed', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('message');
            $table->dateTime('from')->nullable();
            $table->dateTime('until')->nullable();
            $table->json('data')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('newsfeed');
    }
};
