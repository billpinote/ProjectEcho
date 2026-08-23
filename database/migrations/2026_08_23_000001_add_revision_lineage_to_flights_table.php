<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('flights', function (Blueprint $table): void {
            $table->foreignId('revision_of_id')->nullable()->after('revision_number')->constrained('flights')->nullOnDelete();
            $table->index(['revision_of_id', 'revision_number']);
        });
    }

    public function down(): void
    {
        Schema::table('flights', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('revision_of_id');
        });
    }
};
