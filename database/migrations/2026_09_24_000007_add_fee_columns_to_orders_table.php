<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->unsignedTinyInteger('fee_percent')->nullable()->after('amount_cents');
            $table->unsignedBigInteger('fee_amount_cents')->nullable()->after('fee_percent');
            $table->unsignedBigInteger('payout_cents')->nullable()->after('fee_amount_cents');
            $table->string('stripe_checkout_id')->nullable()->after('provider_tx_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['fee_percent', 'fee_amount_cents', 'payout_cents', 'stripe_checkout_id']);
        });
    }
};
