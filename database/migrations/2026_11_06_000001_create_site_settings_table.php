<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The brand's own words and links, edited in /admin → Site settings.
     * Branch addresses, hours and phones live on each branch. The Google
     * OAuth client for customer sign-in is typed in by an owner; the secret
     * is stored encrypted.
     */
    public function up(): void
    {
        Schema::create('site_settings', function (Blueprint $table) {
            $table->id();
            $table->string('tagline')->nullable();
            $table->text('intro')->nullable();
            $table->string('hero_image')->nullable();
            $table->string('og_image')->nullable();
            $table->string('phone')->nullable();
            $table->string('contact_email')->nullable();
            $table->string('facebook_url')->nullable();
            $table->string('instagram_url')->nullable();
            $table->string('messenger_url')->nullable();
            $table->string('google_client_id')->nullable();
            $table->text('google_client_secret')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_settings');
    }
};
