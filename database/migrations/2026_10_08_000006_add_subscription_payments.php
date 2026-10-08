<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('platform_settings', function (Blueprint $table): void {
            $table->string('billing_momo_number')->nullable();
            $table->string('billing_momo_name')->nullable();
            $table->string('billing_momo_network')->nullable();
            $table->boolean('billing_paystack_enabled')->default(false);
            $table->text('billing_paystack_secret_key')->nullable();
        });
        Schema::create('subscription_payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained();
            $table->foreignId('subscription_invoice_id')->constrained('subscriptions_invoices');
            $table->string('reference')->unique();
            $table->string('receipt_reference')->nullable();
            $table->string('checkout_url')->nullable();
            $table->string('method');
            $table->decimal('amount', 12, 2);
            $table->string('currency', 3)->default('GHS');
            $table->string('status')->default('pending');
            $table->foreignId('recorded_by')->nullable()->constrained('users');
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
            $table->unique(['method', 'receipt_reference']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_payments');
        Schema::table('platform_settings', fn (Blueprint $table) => $table->dropColumn(['billing_momo_number', 'billing_momo_name', 'billing_momo_network', 'billing_paystack_enabled', 'billing_paystack_secret_key']));
    }
};
