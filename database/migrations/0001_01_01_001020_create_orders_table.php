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
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->integer('prepare_time');
            $table->foreignId('item_id')->constrained('items');
            $table->integer('amount_desired');
            $table->integer('amount_delivered');
            $table->boolean('is_order_open')->default(true);
            $table->json('log');
            
            $table->unique(['item_id', 'prepare_time']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
