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
        Schema::table('demands', function (Blueprint $table) {
            $table->dropColumn('sp_name');
        });
        Schema::table('usages', function (Blueprint $table) {
            $table->dropColumn('is_locked');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('demands', function (Blueprint $table) {
            $table->string('sp_name');
        });
        Schema::table('usages', function (Blueprint $table) {
            $table->boolean('is_locked')->default(false);
        });
    }
};
