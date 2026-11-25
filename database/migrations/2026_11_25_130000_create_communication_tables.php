<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Step 3 — communication centre: targeted announcements, the shared audience
 * engine (also used by alerts and events later) and system-wide mentions.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('announcements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title', 200);
            $table->longText('body');
            $table->string('priority', 10)->default('normal');
            $table->boolean('requires_confirmation')->default(false);
            $table->boolean('is_public')->default(false);
            $table->dateTime('published_at')->nullable();
            $table->dateTime('expires_at')->nullable();
            $table->dateTime('pinned_until')->nullable();
            $table->unsignedInteger('recipients_count')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['school_id', 'published_at']);
            $table->index(['is_public', 'published_at']);
        });

        Schema::create('audience_targets', function (Blueprint $table) {
            $table->id();
            $table->morphs('owner');
            $table->string('target_type', 20);
            $table->string('target_id', 40)->nullable();
            $table->boolean('include_subgroups')->default(false);
        });

        Schema::create('announcement_recipients', function (Blueprint $table) {
            $table->foreignId('announcement_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('reason', 10)->default('audience');
            $table->dateTime('notified_at')->nullable();
            $table->dateTime('read_at')->nullable();
            $table->dateTime('confirmed_at')->nullable();

            $table->primary(['announcement_id', 'user_id']);
            $table->index(['user_id', 'read_at']);
        });

        Schema::create('mentions', function (Blueprint $table) {
            $table->id();
            $table->morphs('source');
            $table->morphs('mentionable');
            $table->foreignId('school_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mentions');
        Schema::dropIfExists('announcement_recipients');
        Schema::dropIfExists('audience_targets');
        Schema::dropIfExists('announcements');
    }
};
