<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->date('business_date');
            $table->unsignedSmallInteger('number');
            $table->string('customer_name', 60);
            $table->boolean('takeaway')->default(false);
            $table->string('status', 12)->index();
            $table->string('source', 10)->default('balcao');
            $table->unsignedInteger('total_cents')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('ready_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('canceled_at')->nullable();
            $table->timestamp('edited_at')->nullable();
            $table->string('cancel_reason', 120)->nullable();
            $table->timestamps();

            $table->unique(['business_date', 'number']);
            $table->index(['business_date', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
