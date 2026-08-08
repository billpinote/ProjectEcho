<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('profile_update_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('status', 32)->default('pending');
            $table->json('requested_changes');
            $table->text('reason')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('reviewed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('reviewer_remarks')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['status', 'submitted_at']);
        });

        Schema::create('profile_update_request_documents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('profile_update_request_id')
                ->constrained('profile_update_requests')
                ->cascadeOnDelete();
            $table->string('original_filename');
            $table->string('stored_path');
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->timestamp('uploaded_at')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index('profile_update_request_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('profile_update_request_documents');
        Schema::dropIfExists('profile_update_requests');
    }
};
