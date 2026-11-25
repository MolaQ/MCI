<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('groups')->nullOnDelete();
            $table->string('name', 150);
            $table->string('slug', 150)->unique();
            $table->string('type', 30)->index();
            $table->text('description')->nullable();
            $table->string('color', 7)->nullable();
            $table->boolean('is_system')->default(false);
            $table->string('membership_mode', 20)->default('managed');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('group_user', function (Blueprint $table) {
            $table->foreignId('group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role', 20)->default('member');
            $table->string('joined_via', 20)->default('admin');
            // FK to invites is added in Step 2 together with the invites table.
            $table->unsignedBigInteger('invite_id')->nullable();
            $table->timestamps();

            $table->primary(['group_id', 'user_id']);
            $table->index(['user_id', 'role']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('group_user');
        Schema::dropIfExists('groups');
    }
};
