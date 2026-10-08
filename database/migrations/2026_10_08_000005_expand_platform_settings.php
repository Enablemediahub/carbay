<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('platform_settings', function (Blueprint $table): void {
            foreach (['light_logo_path', 'dark_logo_path', 'app_icon_path', 'icon_192_path', 'icon_512_path', 'hero_image_path', 'legal_email', 'legal_address'] as $column) {
                $table->string($column)->nullable();
            }
            $table->string('brand_name')->default('Carbay+');
            $table->string('landing_title')->default('Where would you like to go?');
            $table->string('landing_subtitle')->default('Choose your workspace to sign in and get straight to work.');
            $table->string('hero_mode')->default('default');
            $table->string('hero_gradient_start', 7)->default('#07518e');
            $table->string('hero_gradient_end', 7)->default('#052746');
            $table->unsignedTinyInteger('hero_overlay')->default(60);
            $table->string('theme_color', 7)->default('#0096FF');
            $table->boolean('install_popup_enabled')->default(true);
            $table->unsignedTinyInteger('install_popup_delay')->default(2);
            $table->boolean('legal_reviewed')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('platform_settings', fn (Blueprint $table) => $table->dropColumn([
            'light_logo_path', 'dark_logo_path', 'app_icon_path', 'icon_192_path', 'icon_512_path', 'hero_image_path', 'legal_email', 'legal_address',
            'brand_name', 'landing_title', 'landing_subtitle', 'hero_mode', 'hero_gradient_start', 'hero_gradient_end', 'hero_overlay', 'theme_color',
            'install_popup_enabled', 'install_popup_delay', 'legal_reviewed',
        ]));
    }
};
