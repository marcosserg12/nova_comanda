<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_counters', function (Blueprint $table) {
            $table->date('business_date')->primary();
            $table->unsignedSmallInteger('last_number')->default(0);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_counters');
    }
};
