<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_prices', fn (Blueprint $table) => $table->unsignedBigInteger('vehicle_category_id')->nullable()->change());
        Schema::table('jobs', function (Blueprint $table): void {
            $table->string('plate', 20)->nullable()->change();
            $table->string('job_type')->default('vehicle');
        });
    }

    public function down(): void
    {
        // Nullable vehicle fields are retained to preserve existing standalone jobs.
        Schema::table('jobs', fn (Blueprint $table) => $table->dropColumn('job_type'));
    }
};
