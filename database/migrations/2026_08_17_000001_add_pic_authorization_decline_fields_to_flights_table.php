<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('flights', function (Blueprint $table): void {
            $table->string('pic_authorization_status')->nullable()->after('pic_authorized_revision');
            $table->foreignId('pic_authorization_declined_by_user_id')
                ->nullable()
                ->after('pic_authorization_status')
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('pic_authorization_declined_at')->nullable()->after('pic_authorization_declined_by_user_id');
            $table->text('pic_authorization_decline_reason')->nullable()->after('pic_authorization_declined_at');
        });
    }

    public function down(): void
    {
        Schema::table('flights', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('pic_authorization_declined_by_user_id');
            $table->dropColumn([
                'pic_authorization_status',
                'pic_authorization_declined_at',
                'pic_authorization_decline_reason',
            ]);
        });
    }
};
