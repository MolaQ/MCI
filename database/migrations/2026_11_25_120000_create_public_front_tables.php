<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Step 3 — public front: school public profiles, educational offers and the
 * core of events (single dated events with the four visibility levels;
 * stages, tasks and members come with the Project Manager step).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schools', function (Blueprint $table) {
            $table->string('tagline', 200)->nullable()->after('city');
            $table->text('description')->nullable()->after('tagline');
            $table->string('address', 200)->nullable()->after('description');
            $table->string('phone', 32)->nullable()->after('address');
            $table->string('public_email', 191)->nullable()->after('phone');
            $table->string('website', 191)->nullable()->after('public_email');
            $table->boolean('is_listed')->default(true)->after('is_active');
        });

        Schema::create('school_offers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 30);
            $table->string('name', 150);
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('position')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['school_id', 'is_active', 'position']);
        });

        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title', 200);
            $table->text('description')->nullable();
            $table->text('public_description')->nullable();
            $table->string('category', 30)->default('other');
            $table->dateTime('starts_at');
            $table->dateTime('ends_at')->nullable();
            $table->boolean('all_day')->default(false);
            $table->string('location', 150)->nullable();
            $table->string('visibility', 20)->default('school_only');
            $table->string('status', 20)->default('planned');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['visibility', 'starts_at']);
            $table->index(['school_id', 'starts_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('events');
        Schema::dropIfExists('school_offers');
        Schema::table('schools', function (Blueprint $table) {
            $table->dropColumn(['tagline', 'description', 'address', 'phone', 'public_email', 'website', 'is_listed']);
        });
    }
};
