<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('schools', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('short_name', 20);
            $table->string('slug', 100)->unique();
            $table->string('city', 100)->nullable();
            $table->json('email_domains')->nullable();
            $table->json('settings')->nullable();
            $table->string('logo_path')->nullable();
            $table->string('color', 7)->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('school_user', function (Blueprint $table) {
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('status', 20)->default('active');
            $table->string('joined_via', 20)->default('admin');
            $table->unsignedBigInteger('invite_id')->nullable();
            $table->timestamps();

            $table->primary(['school_id', 'user_id']);
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('school_user');
        Schema::dropIfExists('schools');
    }
};
