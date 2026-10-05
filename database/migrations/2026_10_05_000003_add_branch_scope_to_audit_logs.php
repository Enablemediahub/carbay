<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->foreignId('branch_id')
                ->nullable()
                ->after('tenant_id')
                ->constrained()
                ->nullOnDelete();

            $table->index(['tenant_id', 'branch_id', 'created_at'], 'audit_logs_tenant_branch_created_index');
        });
    }

    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropIndex('audit_logs_tenant_branch_created_index');
            $table->dropConstrainedForeignId('branch_id');
        });
    }
};
