<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payout_orders', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('payable_id')->constrained('payables')->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            // boleto | pix_qr_code | pix_key
            $table->string('method');
            $table->string('status');

            // Valor máximo aprovado pelo usuário.
            $table->decimal('value', 12, 2);
            $table->decimal('paid_value', 12, 2)->nullable();

            // Instrumento de pagamento (linha digitável, copia-e-cola ou chave PIX).
            $table->text('payment_code');
            $table->string('pix_key_type')->nullable();

            // Beneficiário retornado pelo Asaas na validação (snapshot aprovado).
            $table->string('beneficiary_name')->nullable();
            $table->string('beneficiary_document', 20)->nullable();
            $table->json('preview')->nullable();

            $table->timestamp('scheduled_for');
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('authorized_at')->nullable();
            $table->timestamp('settled_at')->nullable();

            $table->string('external_id')->nullable();
            $table->string('external_transfer_id')->nullable();
            $table->string('external_status')->nullable();
            $table->string('receipt_url')->nullable();
            $table->text('failure_reason')->nullable();

            // Preenchido com o payable_id enquanto a ordem está ativa: o índice
            // único garante no máximo uma ordem ativa por conta.
            $table->unsignedBigInteger('active_lock')->nullable()->unique();

            $table->timestamps();

            $table->index(['tenant_id', 'status']);
            $table->index(['status', 'scheduled_for']);
            $table->index('external_id');
            $table->index('external_transfer_id');
        });

        Schema::create('payout_order_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payout_order_id')->constrained('payout_orders')->cascadeOnDelete();
            $table->string('type');
            $table->string('message');
            $table->json('data')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index('payout_order_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payout_order_events');
        Schema::dropIfExists('payout_orders');
    }
};
