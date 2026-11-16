<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained()->restrictOnDelete();
            $table->string('name', 80);
            $table->unsignedInteger('price_cents');
            $table->boolean('has_daily_name')->default(false);
            $table->string('daily_name', 60)->nullable();
            $table->date('daily_name_date')->nullable();
            $table->date('sold_out_date')->nullable();
            $table->string('image_path')->nullable();
            $table->boolean('active')->default(true);
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
