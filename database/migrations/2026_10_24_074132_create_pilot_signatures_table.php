<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('pilot_signatures', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('profile_update_request_id')->unique()->constrained()->restrictOnDelete();
            $table->unsignedInteger('version');
            $table->string('status', 24)->default('pending');
            $table->foreignId('active_user_id')->nullable()->unique()->constrained('users')->restrictOnDelete();
            $table->string('stored_path')->unique();
            $table->string('sha256', 64);
            $table->string('source', 16);
            $table->unsignedInteger('width');
            $table->unsignedInteger('height');
            $table->unsignedInteger('file_size');
            $table->timestamp('submitted_at');
            $table->foreignId('reviewed_by_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'version']);
            $table->index(['user_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pilot_signatures');
    }
};
