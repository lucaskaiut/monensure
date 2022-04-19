<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('bills', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->uuid('user_id')->nullable();
            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');

            $table->uuid('category_id')->nullable();
            $table->foreign('category_id')->references('id')->on('categories')->onDelete('set null');

            $table->uuid('group_id');
            $table->foreign('group_id')->references('id')->on('groups')->onDelete('cascade');

            $table->uuid('supplier_id')->nullable();
            $table->foreign('supplier_id')->references('id')->on('suppliers')->onDelete('set null');

            $table->date('reference_at')->default(DB::raw('CURRENT_TIMESTAMP'));
            $table->string('description')->nullable();
            $table->double('amount', 10, 2);
            $table->date('due_at')->default(DB::raw('CURRENT_TIMESTAMP'));
            $table->date('original_due_at')->default(DB::raw('CURRENT_TIMESTAMP'));
            $table->boolean('is_paid')->default(false);
            $table->boolean('is_credit_card')->default(false);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('bills');
    }
};
