<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', fn (Blueprint $table) => $table->string('service_pricing_mode')->default('standard'));
        Schema::table('service_prices', fn (Blueprint $table) => $table->string('pricing_system')->default('standard')->index());
        Schema::table('vehicle_categories', function (Blueprint $table): void {
            $table->dropUnique('vehicle_categories_name_unique');
            $table->unique(['tenant_id', 'name']);
        });
        Schema::table('services', function (Blueprint $table): void {
            $table->dropUnique('services_name_is_global_unique');
            $table->unique(['tenant_id', 'name', 'is_global']);
        });
    }

    public function down(): void
    {
        // Company-specific service and vehicle names may now repeat across companies.
        // Keep those scoped indexes when removing the pricing-mode feature.
        Schema::table('service_prices', fn (Blueprint $table) => $table->dropColumn('pricing_system'));
        Schema::table('tenants', fn (Blueprint $table) => $table->dropColumn('service_pricing_mode'));
    }
};
