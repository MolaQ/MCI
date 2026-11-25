<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('school_id')->nullable()->constrained()->cascadeOnDelete();
            $table->nullableMorphs('owner');
            $table->string('collection', 20);
            $table->string('path', 255);
            $table->string('mime', 50);
            $table->unsignedInteger('size');
            $table->unsignedSmallInteger('width');
            $table->unsignedSmallInteger('height');
            $table->boolean('is_public')->default(false);
            $table->unsignedSmallInteger('position')->default(0);
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::table('schools', function (Blueprint $table) {
            $table->foreignId('logo_media_id')->nullable()->after('logo_path')->constrained('media')->nullOnDelete();
        });
        Schema::table('groups', function (Blueprint $table) {
            $table->foreignId('logo_media_id')->nullable()->after('color')->constrained('media')->nullOnDelete();
        });
        Schema::table('events', function (Blueprint $table) {
            $table->foreignId('cover_media_id')->nullable()->after('location')->constrained('media')->nullOnDelete();
        });
    }

    public function down(): void
    {
        foreach (['schools' => 'logo_media_id', 'groups' => 'logo_media_id', 'events' => 'cover_media_id'] as $table => $column) {
            Schema::table($table, function (Blueprint $t) use ($column) {
                $t->dropConstrainedForeignId($column);
            });
        }
        Schema::dropIfExists('media');
    }
};
