<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('flights', function (Blueprint $table) {
            $table->string('accepted_by_wiresign')->nullable()->after('accepted_by_user_id');
            $table->string('rejected_by_wiresign')->nullable()->after('accepted_by_wiresign');
        });

        DB::table('flights')
            ->where('status', 'accepted')
            ->whereNotNull('received_by')
            ->update([
                'accepted_by_wiresign' => DB::raw('received_by'),
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('flights', function (Blueprint $table) {
            $table->dropColumn(['accepted_by_wiresign', 'rejected_by_wiresign']);
        });
    }
};
