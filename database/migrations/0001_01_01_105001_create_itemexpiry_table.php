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
        Schema::create('itemexpiry', function (Blueprint $table) {

            $table->id();
            $table->timestamps();

            $table->foreignId('item_id')->constrained('items');
            $table->foreignId('usage_id')->nullable()->constrained('usages');

            $table->date('expiryAt')->nullable();
            $table->integer('expiryQuantity');

            $table->string('status');
            $table->string('note')->nullable();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('itemexpiry');
    }
};
