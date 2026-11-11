<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Resort guests who signed in with Google, kept apart from the admin's
     * users so a guest account can never open /admin.
     */
    public function up(): void
    {
        Schema::create('guests', function (Blueprint $table) {
            $table->id();
            $table->string('google_id')->unique();
            $table->string('name');
            $table->string('email')->index();
            $table->string('avatar_url', 500)->nullable();
            $table->string('phone')->nullable();
            $table->rememberToken();
            $table->timestamp('last_signed_in_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guests');
    }
};
