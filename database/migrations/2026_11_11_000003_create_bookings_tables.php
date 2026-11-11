<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Reservations (onsite, booked straight from the site) and home-service
     * requests (scheduled by the branch). Each keeps a copy of its services'
     * names, prices and durations as they were when it was made, and its own
     * start and ready-by times, so later edits never move a booking.
     */
    public function up(): void
    {
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            // "826-7K3QX": for the counter and the phone
            $table->string('reference')->unique();
            $table->string('access_key', 64);
            // onsite or home
            $table->string('kind')->default('onsite');
            // site, admin, walk_in, phone
            $table->string('source')->default('site');
            $table->string('status')->index();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('guest_id')->nullable()->constrained()->nullOnDelete();
            // The bay is held from drop-off until ready-by (home: once scheduled)
            $table->dateTime('starts_at')->nullable();
            $table->dateTime('ends_at')->nullable();
            // Home service: what the customer asked for
            $table->date('preferred_date')->nullable();
            $table->string('preferred_window')->nullable();
            $table->foreignId('vehicle_size_id')->nullable()->constrained()->nullOnDelete();
            $table->string('vehicle_size_name')->nullable();
            $table->string('vehicle_model')->nullable();
            $table->string('plate_number')->nullable()->index();
            $table->string('name');
            $table->string('phone');
            $table->string('email')->nullable();
            $table->text('address')->nullable();
            $table->string('area')->nullable();
            $table->string('landmark')->nullable();
            $table->boolean('has_water')->nullable();
            $table->boolean('has_power')->nullable();
            $table->boolean('has_shade')->nullable();
            $table->text('message')->nullable();
            $table->text('admin_note')->nullable();
            $table->unsignedInteger('total_price')->nullable();
            $table->unsignedSmallInteger('total_days')->default(0);
            $table->unsignedInteger('total_minutes')->default(0);
            // The reservation fee
            $table->unsignedInteger('amount_due')->nullable();
            $table->string('amount_kind')->nullable();
            $table->dateTime('hold_expires_at')->nullable();
            $table->string('payment_method')->nullable();
            $table->string('payment_proof')->nullable();
            $table->timestamp('payment_submitted_at')->nullable();
            $table->string('payment_rejected_reason')->nullable();
            $table->string('payment_note')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('checked_in_at')->nullable();
            $table->timestamp('ready_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            // guest or staff
            $table->string('cancelled_by')->nullable();
            $table->string('cancel_reason')->nullable();
            $table->unsignedTinyInteger('reschedule_count')->default(0);
            $table->timestamp('reminded_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
            $table->index(['branch_id', 'kind', 'starts_at', 'ends_at']);
            $table->index(['status', 'hold_expires_at']);
        });

        Schema::create('booking_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_id')->nullable()->constrained()->nullOnDelete();
            $table->string('service_name');
            $table->string('category_name')->nullable();
            $table->unsignedInteger('price')->nullable();
            $table->unsignedInteger('duration_minutes')->default(0);
            $table->unsignedSmallInteger('duration_days')->default(0);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('booking_settings', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('cancel_cutoff_hours')->default(24);
            $table->boolean('reschedule_enabled')->default(true);
            $table->unsignedSmallInteger('reschedule_cutoff_hours')->default(24);
            $table->unsignedTinyInteger('max_reschedules')->default(1);
            $table->unsignedTinyInteger('max_unpaid_holds_per_guest')->default(1);
            $table->unsignedTinyInteger('max_upcoming_per_guest')->default(3);
            // The owner's terms (refunds, late pickup); blank until they give them
            $table->text('policy_text')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_settings');
        Schema::dropIfExists('booking_items');
        Schema::dropIfExists('bookings');
    }
};
