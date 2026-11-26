<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Step 7: Project Manager. A project belongs to one school and may be shared
 * with partner schools; its team is people, groups or roles (like task
 * assignees); milestones carry deadlines; To-Do tasks and workflow requests
 * can be linked to it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('name', 200);
            $table->string('kind', 30)->default('other');
            $table->longText('description')->nullable();
            $table->string('status', 20)->default('planned');
            // school = every member of the participating schools; team = only the team
            $table->string('visibility', 10)->default('school');
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->foreignId('coordinator_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('budget_notes')->nullable();
            $table->foreignId('cover_media_id')->nullable()->constrained('media')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['school_id', 'status']);
        });

        Schema::create('project_schools', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->unique(['project_id', 'school_id']);
        });

        Schema::create('project_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            // The school a group or role is resolved in (people: their school in the project).
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('group_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('role', 40)->nullable();
            $table->timestamps();

            $table->index(['project_id', 'user_id']);
            $table->index('group_id');
        });

        Schema::create('project_milestones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('title', 200);
            $table->text('description')->nullable();
            $table->dateTime('due_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedSmallInteger('position')->default(0);
            // Reminder log: one "due soon" and one "overdue" notice per deadline.
            $table->timestamp('reminded_at')->nullable();
            $table->timestamp('overdue_notified_at')->nullable();
            $table->timestamps();

            $table->index(['project_id', 'position']);
            $table->index(['completed_at', 'due_at']);
        });

        Schema::table('tasks', function (Blueprint $table) {
            $table->foreignId('project_id')->nullable()->after('workflow_request_id')->constrained()->nullOnDelete();
            $table->foreignId('project_milestone_id')->nullable()->after('project_id')->constrained()->nullOnDelete();
        });

        Schema::table('workflow_requests', function (Blueprint $table) {
            $table->foreignId('project_id')->nullable()->after('template_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('workflow_requests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('project_id');
        });
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropConstrainedForeignId('project_milestone_id');
            $table->dropConstrainedForeignId('project_id');
        });
        Schema::dropIfExists('project_milestones');
        Schema::dropIfExists('project_members');
        Schema::dropIfExists('project_schools');
        Schema::dropIfExists('projects');
    }
};
