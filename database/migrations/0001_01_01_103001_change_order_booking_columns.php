<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
  /**
   * Run the migrations.
   */
  public function up(): void
  {
    Schema::table('bookings', function (Blueprint $table) {
      $table->foreignId('order_id')
        ->nullable()
        ->constrained('orders')
        ->nullOnDelete();
    });
    Schema::table('orders', function (Blueprint $table) {
      $table->dropUnique(['item_id', 'prepare_time']);
      $table->dropColumn('prepare_time');
      $table->date('order_date')->nullable();
      $table->unique(['item_id', 'order_date']);
      $table->dropColumn('amount_des_usage');
      $table->dropColumn('amount_des_changed');
      $table->dropColumn('log');
    });
    DB::table('orders')->delete();
  }

  /**
   * Reverse the migrations.
   */
  public function down(): void
  {
    Schema::table('bookings', function (Blueprint $table) {
      $table->dropConstrainedForeignId('order_id');
    });
    Schema::table('orders', function (Blueprint $table) {
      $table->dropUnique(['item_id', 'order_date']);
      $table->dropColumn('order_date');
      $table->integer('prepare_time')->default(0);
      $table->unique(['item_id', 'prepare_time']);
      $table->integer('amount_des_usage');
      $table->integer('amount_des_changed');
      $table->json('log');
    });
  }
};
