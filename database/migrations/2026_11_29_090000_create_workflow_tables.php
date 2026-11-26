<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Step 6 — Workflow: reusable approval templates (system-wide or per school)
 * with ordered stages, and requests that move through them. A request keeps
 * a snapshot of its stages, so editing a template never changes requests
 * already in flight. The current approver is denormalised onto the request
 * for the "Do mojej decyzji" query.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workflow_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->nullable()->constrained()->cascadeOnDelete(); // null = system-wide
            $table->string('name', 120);
            $table->text('description')->nullable();
            $table->string('icon', 40)->default('clipboard-document-check');
            $table->json('fields')->nullable();
            $table->unsignedTinyInteger('min_attachments')->default(0);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('position')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['school_id', 'is_active', 'position']);
        });

        Schema::create('workflow_stages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('template_id')->constrained('workflow_templates')->cascadeOnDelete();
            $table->unsignedSmallInteger('position')->default(0);
            $table->string('name', 120);
            $table->string('approver_type', 10); // role | group | user
            $table->string('approver_id', 40);
            $table->foreignId('school_id')->nullable()->constrained()->cascadeOnDelete(); // null = requester's school
            $table->unsignedSmallInteger('sla_hours')->nullable();
            $table->boolean('task_on_reach')->default(false);
            $table->string('followup_task', 200)->nullable();
            $table->timestamps();

            $table->index(['template_id', 'position']);
        });

        Schema::create('workflow_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('template_id')->nullable()->constrained('workflow_templates')->nullOnDelete();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('requester_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title', 200);
            $table->string('template_name', 120);
            $table->json('data')->nullable();
            $table->json('stages');
            $table->string('status', 20)->default('in_progress');
            $table->unsignedSmallInteger('current_position')->default(0);
            $table->string('approver_type', 10)->nullable();
            $table->string('approver_id', 40)->nullable();
            $table->foreignId('approver_school_id')->nullable()->constrained('schools')->nullOnDelete();
            $table->dateTime('stage_due_at')->nullable();
            $table->dateTime('reminded_at')->nullable();
            $table->dateTime('submitted_at')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'approver_type', 'approver_id']);
            $table->index(['requester_id', 'status']);
            $table->index(['school_id', 'status']);
        });

        Schema::create('workflow_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('request_id')->constrained('workflow_requests')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedSmallInteger('stage_position')->nullable();
            $table->string('action', 20); // submitted, approved, rejected, returned, resubmitted, cancelled
            $table->longText('comment')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['request_id', 'id']);
            $table->index(['user_id', 'request_id']);
        });

        Schema::table('tasks', function (Blueprint $table) {
            $table->foreignId('workflow_request_id')->nullable()->after('event_id')->constrained('workflow_requests')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropConstrainedForeignId('workflow_request_id');
        });
        Schema::dropIfExists('workflow_actions');
        Schema::dropIfExists('workflow_requests');
        Schema::dropIfExists('workflow_stages');
        Schema::dropIfExists('workflow_templates');
    }
};
