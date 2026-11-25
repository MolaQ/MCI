<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Step 5 — To-Do: personal and assigned tasks (docs/ARCHITECTURE.md §3.6).
 * One table for personal To-Do and team tasks; assignees are people, groups
 * (teams) or roles of the task's school. Checklist items are rows of their
 * own; images reuse the media table (owner = task).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title', 200);
            $table->longText('description')->nullable();
            $table->boolean('is_personal')->default(true);
            $table->unsignedTinyInteger('priority')->default(2);
            $table->string('status', 20)->default('todo');
            $table->dateTime('due_at')->nullable();
            $table->boolean('all_day')->default(false);
            $table->unsignedInteger('remind_before_minutes')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'due_at']);
            $table->index(['school_id', 'status', 'due_at']);
            $table->index(['created_by', 'is_personal']);
        });

        Schema::create('task_assignees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            // Exactly one of user_id / group_id / role is set (checked in TaskGuard).
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('group_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('role', 40)->nullable();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('assigned_at')->nullable();

            $table->index(['user_id', 'task_id']);
            $table->index(['group_id', 'task_id']);
            $table->index(['role', 'task_id']);
        });

        Schema::create('task_checklist_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            $table->string('body', 200);
            $table->boolean('is_done')->default(false);
            $table->foreignId('done_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('done_at')->nullable();
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();

            $table->index(['task_id', 'position']);
        });

        // Idempotency log: the scheduler never sends the same reminder twice a day.
        Schema::create('task_reminders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20);
            $table->date('sent_on');
            $table->dateTime('sent_at');

            $table->unique(['task_id', 'user_id', 'type', 'sent_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_reminders');
        Schema::dropIfExists('task_checklist_items');
        Schema::dropIfExists('task_assignees');
        Schema::dropIfExists('tasks');
    }
};
