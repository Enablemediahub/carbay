<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plate_scans', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('manager_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('job_id')->nullable()->constrained()->nullOnDelete();
            $table->string('image_path');
            $table->string('ocr_raw', 32)->nullable();
            $table->decimal('ocr_confidence', 6, 5)->default(0);
            $table->string('corrected_value', 32)->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'created_at']);
        });

        Schema::table('tenants', function (Blueprint $table): void {
            $table->timestamp('billing_anchor_at')->nullable();
            $table->timestamp('grace_period_ends_at')->nullable();
            $table->boolean('paystack_transfers_enabled')->default(false);
        });

        Schema::table('payouts', function (Blueprint $table): void {
            $table->json('gateway_response_json')->nullable();
        });
        Schema::table('subscriptions_invoices', function (Blueprint $table): void {
            $table->unique(
                ['tenant_id', 'period_starts_at', 'period_ends_at'],
                'subscription_invoice_period_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table): void {
            $table->dropColumn(['billing_anchor_at', 'grace_period_ends_at', 'paystack_transfers_enabled']);
        });
        Schema::table('payouts', function (Blueprint $table): void {
            $table->dropColumn('gateway_response_json');
        });
        Schema::table('subscriptions_invoices', function (Blueprint $table): void {
            $table->dropUnique('subscription_invoice_period_unique');
        });

        Schema::dropIfExists('plate_scans');
    }
};
