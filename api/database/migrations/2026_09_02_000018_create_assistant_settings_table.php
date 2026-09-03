<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assistant_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->unique()->constrained('tenants')->cascadeOnDelete();
            $table->boolean('enabled')->default(true);
            $table->string('endpoint')->nullable();
            $table->text('api_key')->nullable();
            $table->string('model')->nullable();
            $table->decimal('temperature', 4, 2)->nullable();
            $table->unsignedInteger('max_tokens')->nullable();
            $table->text('additional_prompt')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assistant_settings');
    }
};
