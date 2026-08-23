<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('flights', function (Blueprint $table): void {
            $table->timestamp('pic_authorization_archived_at')->nullable()->after('pic_authorization_decline_reason');
            $table->index('pic_authorization_archived_at');
        });
    }

    public function down(): void
    {
        Schema::table('flights', function (Blueprint $table): void {
            $table->dropColumn('pic_authorization_archived_at');
        });
    }
};
