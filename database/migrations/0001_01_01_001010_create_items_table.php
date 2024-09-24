<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('items', function (Blueprint $table) {
            $table->id();
            $table->timestamps();

            $table->string('name');
            $table->string('search_altnames')->nullable();
            $table->string('search_tags')->nullable();

            $table->foreignId('demand_id')->constrained('demands');

            $table->json('location');

            $table->integer('min_stock')->default(0);
            $table->integer('max_stock')->default(0);

            $table->date('current_expiry')->nullable();
            $table->integer('current_quantity')->default(0);

            $table->unique(['name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('items');
    }
};
