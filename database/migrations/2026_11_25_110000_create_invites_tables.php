<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            // Lookups use only the SHA-256 hash; the token itself is stored
            // encrypted (APP_KEY) so a poster can be printed again.
            $table->char('token_hash', 64)->unique();
            $table->text('token');
            $table->string('kind', 20);
            $table->string('label', 150)->nullable();
            $table->string('email', 191)->nullable()->index();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('role_name', 50)->nullable();
            $table->unsignedInteger('max_uses')->nullable();
            $table->unsignedInteger('uses_count')->default(0);
            $table->boolean('allow_self_registration')->default(false);
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->foreignId('revoked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['school_id', 'revoked_at', 'expires_at']);
        });

        Schema::create('invite_group', function (Blueprint $table) {
            $table->foreignId('invite_id')->constrained()->cascadeOnDelete();
            $table->foreignId('group_id')->constrained()->cascadeOnDelete();
            $table->string('group_role', 20)->default('member');

            $table->primary(['invite_id', 'group_id']);
        });

        Schema::create('invite_redemptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invite_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->boolean('created_account')->default(false);
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->timestamp('redeemed_at')->useCurrent();

            $table->unique(['invite_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invite_redemptions');
        Schema::dropIfExists('invite_group');
        Schema::dropIfExists('invites');
    }
};
