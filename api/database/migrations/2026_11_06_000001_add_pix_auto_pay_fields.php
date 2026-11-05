<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            $table->string('pix_key')->nullable()->after('email');
            $table->string('pix_key_type')->nullable()->after('pix_key');
        });

        Schema::table('financial_recurrences', function (Blueprint $table) {
            // Pagamento automático via PIX (Asaas). A chave e o valor máximo são
            // um snapshot aprovado com senha: mudar o fornecedor não altera.
            $table->boolean('auto_pay_enabled')->default(false)->after('generate_automatically');
            $table->string('auto_pay_pix_key')->nullable()->after('auto_pay_enabled');
            $table->string('auto_pay_pix_key_type')->nullable()->after('auto_pay_pix_key');
            $table->string('auto_pay_time', 5)->default('09:00')->after('auto_pay_pix_key_type');
            $table->decimal('auto_pay_max_value', 12, 2)->nullable()->after('auto_pay_time');
            $table->foreignId('auto_pay_approved_by')->nullable()->after('auto_pay_max_value')->constrained('users')->nullOnDelete();
            $table->timestamp('auto_pay_approved_at')->nullable()->after('auto_pay_approved_by');
        });

        Schema::table('payout_orders', function (Blueprint $table) {
            // Recorrência que criou a ordem automaticamente (null = ordem manual).
            $table->foreignId('recurrence_id')->nullable()->after('payable_id')->constrained('financial_recurrences')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('payout_orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('recurrence_id');
        });

        Schema::table('financial_recurrences', function (Blueprint $table) {
            $table->dropConstrainedForeignId('auto_pay_approved_by');
            $table->dropColumn([
                'auto_pay_enabled',
                'auto_pay_pix_key',
                'auto_pay_pix_key_type',
                'auto_pay_time',
                'auto_pay_max_value',
                'auto_pay_approved_at',
            ]);
        });

        Schema::table('suppliers', function (Blueprint $table) {
            $table->dropColumn(['pix_key', 'pix_key_type']);
        });
    }
};
