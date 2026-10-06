<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('jobs') && ! Schema::hasTable('queue_jobs')) {
            Schema::rename('jobs', 'queue_jobs');
        }

        Schema::table('workers', function (Blueprint $table) {
            $table->enum('payout_mode', ['instant', 'daily', 'weekly', 'monthly'])->default('daily')->after('default_share_pct');
        });

        Schema::table('vehicle_categories', function (Blueprint $table) {
            $table->foreignId('tenant_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
        });

        Schema::table('vehicle_makes', function (Blueprint $table) {
            $table->foreignId('tenant_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
        });

        Schema::table('vehicle_models', function (Blueprint $table) {
            $table->foreignId('tenant_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
        });

        Schema::table('services', function (Blueprint $table) {
            $table->foreignId('tenant_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
        });

        Schema::create('clients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('phone', 30)->nullable();
            $table->string('email')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'phone']);
        });

        Schema::create('jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('manager_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('client_id')->nullable()->constrained()->nullOnDelete();
            $table->string('plate', 20);
            $table->foreignId('vehicle_category_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('make_id')->nullable()->constrained('vehicle_makes')->nullOnDelete();
            $table->foreignId('model_id')->nullable()->constrained('vehicle_models')->nullOnDelete();
            $table->decimal('total_amount', 10, 2);
            $table->enum('payment_method', ['cash', 'momo', 'paystack']);
            $table->string('payment_ref')->nullable();
            $table->enum('payment_status', ['pending', 'paid', 'failed'])->default('pending');
            $table->enum('status', ['open', 'completed', 'cancelled'])->default('open');
            $table->text('notes')->nullable();
            $table->string('photo_path')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'branch_id', 'created_at']);
            $table->index(['tenant_id', 'plate']);
        });

        Schema::create('job_services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('job_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_id')->nullable()->constrained()->nullOnDelete();
            $table->string('service_name');
            $table->unsignedInteger('quantity')->default(1);
            $table->decimal('unit_price', 10, 2);
            $table->decimal('total_amount', 10, 2);
            $table->decimal('company_share', 10, 2)->default(0);
            $table->decimal('worker_share', 10, 2)->default(0);
            $table->decimal('company_pct', 5, 2)->default(100);
            $table->decimal('worker_pct', 5, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('job_workers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('job_id')->constrained()->cascadeOnDelete();
            $table->foreignId('worker_id')->constrained()->cascadeOnDelete();
            $table->decimal('share_amount', 10, 2);
            $table->enum('payout_mode', ['instant', 'daily', 'weekly', 'monthly']);
            $table->timestamps();
            $table->unique(['job_id', 'worker_id']);
        });

        Schema::create('wallets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('worker_id')->unique()->constrained()->cascadeOnDelete();
            $table->decimal('available_balance', 12, 2)->default(0);
            $table->decimal('pending_balance', 12, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('payouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('worker_id')->constrained()->cascadeOnDelete();
            $table->foreignId('job_worker_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('amount', 10, 2);
            $table->enum('payout_mode', ['instant', 'daily', 'weekly', 'monthly']);
            $table->enum('method', ['cash', 'momo'])->nullable();
            $table->string('reference')->nullable();
            $table->enum('status', ['queued', 'pending', 'paid', 'rejected'])->default('pending');
            $table->timestamp('available_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'branch_id', 'status']);
        });

        Schema::create('wallet_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('wallet_id')->constrained()->cascadeOnDelete();
            $table->foreignId('worker_id')->constrained()->cascadeOnDelete();
            $table->foreignId('job_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('job_worker_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->foreignId('payout_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('type', ['credit', 'debit', 'payout']);
            $table->decimal('amount', 10, 2);
            $table->enum('status', ['pending', 'paid', 'failed'])->default('pending');
            $table->enum('payout_mode', ['instant', 'daily', 'weekly', 'monthly'])->nullable();
            $table->string('reference')->nullable();
            $table->timestamp('available_at')->nullable();
            $table->timestamps();
            $table->index(['worker_id', 'status', 'created_at']);
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('job_id')->constrained()->cascadeOnDelete();
            $table->foreignId('manager_id')->constrained('users')->cascadeOnDelete();
            $table->decimal('amount', 10, 2);
            $table->enum('method', ['cash', 'momo_manual', 'paystack']);
            $table->string('reference')->nullable()->index();
            $table->enum('status', ['pending', 'paid', 'failed'])->default('pending');
            $table->json('gateway_response_json')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'branch_id', 'status']);
        });

        Schema::create('cash_reconciliations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('manager_id')->constrained('users')->cascadeOnDelete();
            $table->date('business_date');
            $table->decimal('expected_amount', 10, 2)->default(0);
            $table->decimal('entered_amount', 10, 2);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['manager_id', 'business_date']);
        });

        Schema::create('worker_check_ins', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('worker_id')->constrained()->cascadeOnDelete();
            $table->foreignId('recorded_by')->constrained('users')->cascadeOnDelete();
            $table->date('work_date');
            $table->timestamp('checked_in_at')->useCurrent();
            $table->timestamp('checked_out_at')->nullable();
            $table->timestamps();
            $table->unique(['worker_id', 'work_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('worker_check_ins');
        Schema::dropIfExists('cash_reconciliations');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('wallet_transactions');
        Schema::dropIfExists('payouts');
        Schema::dropIfExists('wallets');
        Schema::dropIfExists('job_workers');
        Schema::dropIfExists('job_services');
        Schema::dropIfExists('jobs');
        Schema::dropIfExists('clients');

        Schema::table('services', fn (Blueprint $table) => $table->dropConstrainedForeignId('tenant_id'));
        Schema::table('vehicle_models', fn (Blueprint $table) => $table->dropConstrainedForeignId('tenant_id'));
        Schema::table('vehicle_makes', fn (Blueprint $table) => $table->dropConstrainedForeignId('tenant_id'));
        Schema::table('vehicle_categories', fn (Blueprint $table) => $table->dropConstrainedForeignId('tenant_id'));
        Schema::table('workers', fn (Blueprint $table) => $table->dropColumn('payout_mode'));
    }
};
