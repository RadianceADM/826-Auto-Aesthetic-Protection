<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 826's branches, each with its own bays, hours, services, home-service
     * areas, staff and (optionally) its own GCash and Maya accounts. A
     * branch stays a draft, hidden from the site, until it's filled in.
     */
    public function up(): void
    {
        Schema::create('branches', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            // draft (hidden), coming_soon (listed, no booking) or open
            $table->string('status')->default('draft')->index();
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('city')->nullable();
            $table->text('address')->nullable();
            $table->string('map_url')->nullable();
            $table->decimal('map_lat', 10, 7)->nullable();
            $table->decimal('map_lng', 10, 7)->nullable();
            $table->string('phone')->nullable();
            $table->string('messenger_url')->nullable();
            $table->string('facebook_url')->nullable();
            // Where new bookings for this branch are emailed
            $table->string('alert_email')->nullable();
            // How many cars the shop works on at once (0: no onsite bookings)
            $table->unsignedTinyInteger('bays')->default(0);
            // {"1": {"open": "08:00", "close": "18:00", "last_dropoff": "16:00"}, "7": null}
            $table->json('weekly_hours')->nullable();
            $table->unsignedSmallInteger('step_minutes')->default(60);
            $table->unsignedSmallInteger('lead_hours')->default(2);
            $table->unsignedTinyInteger('months_ahead')->default(3);
            // Optional: multi-day jobs must be dropped off by this time
            $table->string('multiday_dropoff_by', 5)->nullable();
            $table->boolean('offers_home_service')->default(false);
            $table->json('home_service_areas')->nullable();
            // The branch's own accounts; when none is on, the main ones are used
            $table->boolean('gcash_enabled')->default(false);
            $table->string('gcash_qr')->nullable();
            $table->string('gcash_name')->nullable();
            $table->string('gcash_number')->nullable();
            $table->boolean('maya_enabled')->default(false);
            $table->string('maya_qr')->nullable();
            $table->string('maya_name')->nullable();
            $table->string('maya_number')->nullable();
            $table->timestamps();
        });

        Schema::create('branch_service', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();
            $table->unique(['branch_id', 'service_id']);
        });

        // Branch staff see only their branches; owners see every branch
        Schema::create('branch_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unique(['branch_id', 'user_id']);
        });

        Schema::create('branch_blocks', function (Blueprint $table) {
            $table->id();
            // Null: every branch (Christmas, Holy Week)
            $table->foreignId('branch_id')->nullable()->constrained()->cascadeOnDelete();
            // closed: the shop is shut (no drop-offs, jobs pause); bays: some bays are out
            $table->string('kind')->default('closed');
            // For kind=bays: how many are out (null: all of them)
            $table->unsignedTinyInteger('bays')->nullable();
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->string('title');
            $table->text('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['branch_id', 'starts_at', 'ends_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('branch_blocks');
        Schema::dropIfExists('branch_user');
        Schema::dropIfExists('branch_service');
        Schema::dropIfExists('branches');
    }
};
