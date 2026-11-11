<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mail_settings', function (Blueprint $table) {
            $table->id();
            $table->string('address')->nullable();
            $table->text('password')->nullable();
            $table->string('from_name')->nullable();
            // Where booking requests are emailed; the mailbox itself when blank
            $table->string('notify_email')->nullable();
            $table->string('smtp_host')->default('smtp.hostinger.com');
            $table->unsignedSmallInteger('smtp_port')->default(465);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mail_settings');
    }
};
