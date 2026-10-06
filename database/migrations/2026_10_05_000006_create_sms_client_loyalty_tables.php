<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->date('birthday')->nullable()->after('phone');
            $table->json('preferences_json')->nullable()->after('email');
            $table->unsignedInteger('loyalty_points')->default(0)->after('preferences_json');
            $table->index(['tenant_id', 'birthday']);
        });

        Schema::table('tenants', function (Blueprint $table) {
            $table->unsignedInteger('loyalty_points_per_10')->default(1);
            $table->unsignedInteger('loyalty_reward_points')->default(50);
            $table->decimal('loyalty_reward_value', 10, 2)->default(20);
            $table->decimal('fraud_discount_threshold_pct', 5, 2)->default(20);
        });

        Schema::table('jobs', function (Blueprint $table) {
            $table->decimal('discount_amount', 10, 2)->default(0)->after('total_amount');
        });

        Schema::table('fraud_flags', function (Blueprint $table) {
            $table->foreignId('job_id')->nullable()->after('wash_sale_id')->constrained()->cascadeOnDelete();
            $table->foreignId('cash_reconciliation_id')->nullable()->after('job_id')->constrained()->cascadeOnDelete();
            $table->foreignId('wash_sale_id')->nullable()->change();
            $table->unique(['job_id', 'flag_type'], 'fraud_flags_job_type_unique');
            $table->unique(['cash_reconciliation_id', 'flag_type'], 'fraud_flags_reconciliation_type_unique');
        });

        Schema::create('sms_credit_balances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->unique()->constrained()->cascadeOnDelete();
            $table->unsignedInteger('credits_remaining')->default(0);
            $table->date('period_starts_at');
            $table->date('period_ends_at');
            $table->timestamps();
        });

        Schema::create('sms_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('client_id')->nullable()->constrained()->nullOnDelete();
            $table->string('recipient', 30);
            $table->string('purpose', 80);
            $table->string('campaign_key')->nullable();
            $table->text('message');
            $table->unsignedInteger('credits_charged')->default(1);
            $table->date('credits_period_start')->nullable();
            $table->string('status', 20)->default('queued');
            $table->boolean('credit_refunded')->default(false);
            $table->json('provider_response_json')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'campaign_key', 'recipient'], 'sms_logs_tenant_campaign_recipient_unique');
            $table->index(['tenant_id', 'status', 'created_at']);
        });

        Schema::create('client_otp_challenges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('phone', 30);
            $table->string('purpose', 20);
            $table->string('code_hash');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->json('registration_data')->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'phone', 'purpose', 'expires_at']);
        });

        Schema::create('client_bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->enum('service_type', ['bay', 'home']);
            $table->string('address')->nullable();
            $table->timestamp('requested_for');
            $table->json('service_ids');
            $table->decimal('estimated_amount', 10, 2);
            $table->decimal('discount_amount', 10, 2)->default(0);
            $table->unsignedInteger('loyalty_points_redeemed')->default(0);
            $table->string('status', 30)->default('pending');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'branch_id', 'requested_for']);
        });

        Schema::create('loyalty_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('job_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('booking_id')->nullable()->constrained('client_bookings')->cascadeOnDelete();
            $table->enum('type', ['earn', 'redeem']);
            $table->unsignedInteger('points');
            $table->string('description');
            $table->timestamps();
            $table->unique(['job_id', 'type']);
            $table->unique(['booking_id', 'type']);
            $table->index(['tenant_id', 'client_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loyalty_transactions');
        Schema::dropIfExists('client_bookings');
        Schema::dropIfExists('client_otp_challenges');
        Schema::dropIfExists('sms_logs');
        Schema::dropIfExists('sms_credit_balances');

        Schema::table('fraud_flags', function (Blueprint $table) {
            $table->dropUnique('fraud_flags_job_type_unique');
            $table->dropUnique('fraud_flags_reconciliation_type_unique');
            $table->dropConstrainedForeignId('cash_reconciliation_id');
            $table->dropConstrainedForeignId('job_id');
            $table->foreignId('wash_sale_id')->nullable(false)->change();
        });

        Schema::table('jobs', fn (Blueprint $table) => $table->dropColumn('discount_amount'));
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn([
                'loyalty_points_per_10',
                'loyalty_reward_points',
                'loyalty_reward_value',
                'fraud_discount_threshold_pct',
            ]);
        });
        Schema::table('clients', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'birthday']);
            $table->dropColumn(['birthday', 'preferences_json', 'loyalty_points']);
        });
    }
};
