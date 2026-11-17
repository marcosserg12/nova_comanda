<?php

use App\Models\CustomerName;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_names', function (Blueprint $table) {
            $table->id();
            $table->string('name', 60);
            $table->string('search_key', 60)->unique();
            $table->unsignedInteger('uses')->default(0);
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();

            $table->index(['uses', 'last_used_at']);
        });

        CustomerName::seedCommonNames();
        CustomerName::backfillFromOrders();
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_names');
    }
};
