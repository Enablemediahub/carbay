<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicle_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->boolean('is_global')->default(true);
            $table->timestamps();
        });

        Schema::create('vehicle_makes', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->boolean('is_global')->default(true);
            $table->timestamps();
        });

        Schema::create('vehicle_models', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehicle_make_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->boolean('is_global')->default(true);
            $table->timestamps();
            $table->unique(['vehicle_make_id', 'name']);
        });

        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('default_price', 10, 2)->default(0);
            $table->decimal('company_pct', 5, 2)->default(100);
            $table->decimal('worker_pct', 5, 2)->default(0);
            $table->boolean('is_global')->default(true);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['name', 'is_global']);
        });

        Schema::create('service_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vehicle_category_id')->constrained()->cascadeOnDelete();
            $table->decimal('price', 10, 2)->default(0);
            $table->decimal('company_pct', 5, 2)->default(100);
            $table->decimal('worker_pct', 5, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['tenant_id', 'service_id', 'vehicle_category_id'], 'service_prices_tenant_service_category_unique');
            $table->index(['tenant_id', 'is_active']);
        });

        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->string('category');
            $table->decimal('amount', 10, 2);
            $table->text('note')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('spent_at')->useCurrent();
            $table->timestamps();
            $table->index(['tenant_id', 'branch_id', 'spent_at']);
        });

        Schema::create('subscriptions_invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('package_id')->nullable()->constrained()->nullOnDelete();
            $table->string('invoice_number')->unique();
            $table->decimal('amount', 10, 2);
            $table->string('currency', 3)->default('GHS');
            $table->string('status')->default('pending');
            $table->date('period_starts_at')->nullable();
            $table->date('period_ends_at')->nullable();
            $table->timestamp('due_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'status']);
        });

        Schema::create('branch_addon_invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->string('invoice_number')->unique();
            $table->decimal('amount', 10, 2);
            $table->string('currency', 3)->default('GHS');
            $table->string('status')->default('pending');
            $table->timestamp('due_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'status']);
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('event');
            $table->nullableMorphs('auditable');
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'event']);
        });

        Schema::create('wash_sales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('worker_id')->nullable()->constrained()->nullOnDelete();
            $table->string('reference')->nullable()->unique();
            $table->decimal('total_amount', 10, 2);
            $table->string('status')->default('completed');
            $table->timestamp('sold_at')->useCurrent();
            $table->timestamps();
            $table->index(['tenant_id', 'branch_id', 'sold_at']);
        });

        Schema::create('wash_sale_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wash_sale_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_id')->nullable()->constrained()->nullOnDelete();
            $table->string('service_name');
            $table->unsignedInteger('quantity')->default(1);
            $table->decimal('unit_price', 10, 2);
            $table->decimal('total_amount', 10, 2);
            $table->timestamps();
            $table->index(['wash_sale_id', 'service_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wash_sale_items');
        Schema::dropIfExists('wash_sales');
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('branch_addon_invoices');
        Schema::dropIfExists('subscriptions_invoices');
        Schema::dropIfExists('expenses');
        Schema::dropIfExists('service_prices');
        Schema::dropIfExists('services');
        Schema::dropIfExists('vehicle_models');
        Schema::dropIfExists('vehicle_makes');
        Schema::dropIfExists('vehicle_categories');
    }
};
