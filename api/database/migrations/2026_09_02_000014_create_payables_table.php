<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payables', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('financial_categories')->nullOnDelete();
            $table->foreignId('recurrence_id')->nullable()->constrained('financial_recurrences')->nullOnDelete();
            $table->string('installment_group_uuid')->nullable();
            $table->unsignedInteger('installment_number')->nullable();
            $table->unsignedInteger('installment_total')->nullable();
            $table->date('issue_date')->nullable();
            $table->date('due_date');
            $table->string('description');
            $table->decimal('value', 12, 2);
            $table->string('status')->default('pendente');
            $table->date('paid_at')->nullable();
            $table->decimal('paid_value', 12, 2)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'due_date']);
            $table->index(['installment_group_uuid']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payables');
    }
};
