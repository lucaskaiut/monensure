<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payable_installment_groups', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('description');
            $table->decimal('total_value', 12, 2);
            $table->unsignedInteger('total_installments');
            $table->unsignedInteger('first_installment_number')->default(1);
            $table->date('first_due_date');
            $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('financial_categories')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payable_installment_groups');
    }
};
