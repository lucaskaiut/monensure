<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('financial_categories', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('financial_categories')->nullOnDelete();
            $table->string('name');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'parent_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('financial_categories');
    }
};
