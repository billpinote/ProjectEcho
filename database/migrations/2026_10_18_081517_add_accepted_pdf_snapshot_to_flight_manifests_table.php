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
        Schema::table('flight_manifests', function (Blueprint $table) {
            $table->longText('accepted_pdf_content')->nullable();
            $table->string('accepted_pdf_sha256', 64)->nullable();
            $table->string('accepted_pdf_template_sha256', 64)->nullable();
            $table->timestamp('accepted_pdf_created_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('flight_manifests', function (Blueprint $table) {
            $table->dropColumn(['accepted_pdf_content', 'accepted_pdf_sha256', 'accepted_pdf_template_sha256', 'accepted_pdf_created_at']);
        });
    }
};
