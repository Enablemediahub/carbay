<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['users', 'workers'] as $tableName) {
            Schema::table($tableName, fn (Blueprint $table) => $table->string('photo_path')->nullable());
        }
    }

    public function down(): void
    {
        foreach (['users', 'workers'] as $tableName) {
            Schema::table($tableName, fn (Blueprint $table) => $table->dropColumn('photo_path'));
        }
    }
};
