<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table): void {
            $table->boolean('is_complimentary')->default(false)->after('cancelled_at');
            $table->timestamp('complimentary_ends_at')->nullable()->after('is_complimentary');
        });

        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE subscriptions MODIFY next_billing_at TIMESTAMP NULL');
        }
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table): void {
            $table->dropColumn(['is_complimentary', 'complimentary_ends_at']);
        });

        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE subscriptions MODIFY next_billing_at TIMESTAMP NOT NULL');
        }
    }
};
