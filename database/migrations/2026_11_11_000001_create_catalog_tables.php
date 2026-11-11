<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * What 826 sells: vehicle sizes, service categories, services, and a
     * price and duration for each service and size. Everything is edited
     * in the admin; a service can only be booked for a size once both its
     * price and duration are set.
     */
    public function up(): void
    {
        Schema::create('vehicle_sizes', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            // "Vios, City, Mirage": helps customers pick the right size
            $table->string('examples')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('service_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_category_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('summary')->nullable();
            $table->text('description')->nullable();
            $table->json('inclusions')->nullable();
            $table->string('image')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            // PPF and tint need the shop; detailing can be done at home
            $table->boolean('home_service_ok')->default(false);
            $table->timestamps();
        });

        Schema::create('service_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vehicle_size_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('price')->nullable();
            // One or the other: a job of so many hours (in minutes), or so many open days
            $table->unsignedInteger('duration_minutes')->nullable();
            $table->unsignedSmallInteger('duration_days')->nullable();
            $table->timestamps();
            $table->unique(['service_id', 'vehicle_size_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_rates');
        Schema::dropIfExists('services');
        Schema::dropIfExists('service_categories');
        Schema::dropIfExists('vehicle_sizes');
    }
};
