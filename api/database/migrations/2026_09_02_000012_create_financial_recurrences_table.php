<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('financial_recurrences', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('description');
            $table->decimal('default_value', 12, 2);
            $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('financial_categories')->nullOnDelete();
            $table->unsignedTinyInteger('due_day');
            $table->string('frequency');
            $table->boolean('active')->default(true);
            $table->boolean('generate_automatically')->default(true);
            $table->date('last_generated_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('financial_recurrences');
    }
};
