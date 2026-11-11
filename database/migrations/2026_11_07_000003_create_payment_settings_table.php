<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The reservation fee: whether one is asked at all, how much (a fixed
     * amount, a percent of the estimate, or the whole estimate), how long a
     * slot is held while the customer pays, and the main GCash and Maya
     * accounts (a branch can have its own instead).
     */
    public function up(): void
    {
        Schema::create('payment_settings', function (Blueprint $table) {
            $table->id();
            $table->boolean('gcash_enabled')->default(false);
            $table->string('gcash_qr')->nullable();
            $table->string('gcash_name')->nullable();
            $table->string('gcash_number')->nullable();
            $table->boolean('maya_enabled')->default(false);
            $table->string('maya_qr')->nullable();
            $table->string('maya_name')->nullable();
            $table->string('maya_number')->nullable();
            $table->boolean('reservation_fee_enabled')->default(false);
            // How long a slot is held for the customer to send the screenshot
            $table->unsignedSmallInteger('hold_minutes')->default(30);
            // fixed (₱ amount), percent (of the estimate) or full
            $table->string('amount_rule')->default('fixed');
            $table->unsignedInteger('amount_value')->nullable();
            $table->text('instructions')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_settings');
    }
};
