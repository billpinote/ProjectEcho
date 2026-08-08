<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_audit_logs', function (Blueprint $table): void {
            $table->string('source')->nullable()->after('action');
            $table->string('field')->nullable()->after('source');
            $table->text('old_value')->nullable()->after('field');
            $table->text('new_value')->nullable()->after('old_value');
            $table->foreignId('profile_update_request_id')
                ->nullable()
                ->after('auditable_id')
                ->constrained('profile_update_requests')
                ->nullOnDelete();
            $table->text('remarks')->nullable()->after('description');
            $table->string('ip_address', 45)->nullable()->after('remarks');
            $table->text('user_agent')->nullable()->after('ip_address');

            $table->index(['user_id', 'source']);
            $table->index(['user_id', 'field']);
        });
    }

    public function down(): void
    {
        Schema::table('user_audit_logs', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('profile_update_request_id');
            $table->dropIndex(['user_id', 'source']);
            $table->dropIndex(['user_id', 'field']);
            $table->dropColumn([
                'source',
                'field',
                'old_value',
                'new_value',
                'remarks',
                'ip_address',
                'user_agent',
            ]);
        });
    }
};
