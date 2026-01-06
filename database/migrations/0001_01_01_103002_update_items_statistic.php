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
        Schema::create('itemstats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->constrained('items')->cascadeOnDelete();
            $table->date('week_start')->nullable();

            $table->integer('consumption_total')->default(0);
            $table->integer('consumption_max')->default(0);

            $table->integer('adjustment_total')->default(0);

            $table->integer('booking_max')->default(0);
            $table->integer('booking_count')->default(0);

            $table->timestamp('aggregated_at')->useCurrent();

            // Constraints + indexes
            $table->unique(['item_id', 'week_start']);
            $table->index(['item_id', 'week_start']);
            $table->index(['week_start']);

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('itemstats');
    }
};
