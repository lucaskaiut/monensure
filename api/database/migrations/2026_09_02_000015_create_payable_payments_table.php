<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payable_payments', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('payable_id')->constrained('payables')->cascadeOnDelete();
            $table->date('paid_at');
            $table->decimal('paid_value', 12, 2);
            $table->decimal('difference', 12, 2)->default(0);
            $table->string('notes')->nullable();
            $table->timestamps();

            $table->index(['payable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payable_payments');
    }
};
