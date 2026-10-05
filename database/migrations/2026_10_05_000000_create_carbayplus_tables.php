<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('features', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('packages', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->decimal('price', 10, 2)->default(0);
            $table->string('billing_cycle')->default('monthly');
            $table->decimal('branch_addon_price', 10, 2)->default(0);
            $table->unsignedInteger('worker_limit')->default(0);
            $table->unsignedInteger('sms_credits')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('package_features', function (Blueprint $table) {
            $table->id();
            $table->foreignId('package_id')->constrained()->cascadeOnDelete();
            $table->foreignId('feature_id')->constrained()->cascadeOnDelete();
            $table->boolean('enabled')->default(false);
            $table->unsignedInteger('limit_value')->nullable();
            $table->timestamps();
            $table->unique(['package_id', 'feature_id']);
        });

        Schema::create('tenants', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('logo')->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('email')->unique();
            $table->foreignId('package_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('main_branch_id')->nullable();
            $table->string('status')->default('trial');
            $table->timestamp('trial_ends_at')->nullable();
            $table->string('momo_number', 30)->nullable();
            $table->string('momo_name')->nullable();
            $table->string('paystack_public_key')->nullable();
            $table->text('paystack_secret_key')->nullable();
            $table->boolean('cash_enabled')->default(true);
            $table->boolean('momo_enabled')->default(false);
            $table->boolean('paystack_enabled')->default(false);
            $table->timestamps();
        });

        Schema::create('branches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('address')->nullable();
            $table->string('phone', 30)->nullable();
            $table->unsignedBigInteger('manager_id')->nullable();
            $table->boolean('is_main')->default(false);
            $table->string('status')->default('active');
            $table->boolean('is_addon_paid')->default(false);
            $table->timestamps();
            $table->index(['tenant_id', 'status']);
        });

        Schema::table('tenants', function (Blueprint $table) {
            $table->foreign('main_branch_id')->references('id')->on('branches')->nullOnDelete();
        });

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('role', ['super_admin', 'ceo', 'manager'])->default('ceo');
            $table->string('name');
            $table->string('phone', 30)->nullable();
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->string('pin')->nullable();
            $table->string('status')->default('active');
            $table->rememberToken();
            $table->timestamps();
            $table->index(['tenant_id', 'branch_id', 'status']);
        });

        Schema::table('branches', function (Blueprint $table) {
            $table->foreign('manager_id')->references('id')->on('users')->nullOnDelete();
        });

        Schema::create('workers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('phone', 30)->nullable();
            $table->string('pin');
            $table->enum('type', ['permanent', 'casual'])->default('permanent');
            $table->decimal('default_share_pct', 5, 2)->default(0);
            $table->string('status')->default('active');
            $table->timestamps();
            $table->index(['tenant_id', 'branch_id', 'status']);
        });

        Schema::create('tenant_features', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('feature_id')->constrained()->cascadeOnDelete();
            $table->boolean('enabled')->default(false);
            $table->unsignedInteger('limit_value')->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'feature_id']);
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('tenant_features');
        Schema::dropIfExists('workers');
        Schema::table('branches', function (Blueprint $table) {
            $table->dropForeign(['manager_id']);
        });
        Schema::dropIfExists('users');
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropForeign(['main_branch_id']);
        });
        Schema::dropIfExists('branches');
        Schema::dropIfExists('tenants');
        Schema::dropIfExists('package_features');
        Schema::dropIfExists('packages');
        Schema::dropIfExists('features');
    }
};
