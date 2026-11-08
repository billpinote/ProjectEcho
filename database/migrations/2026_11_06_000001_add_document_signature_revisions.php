<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('flights', fn (Blueprint $table) => $table->unsignedInteger('flight_document_revision')->default(1));
        Schema::table('flight_manifests', fn (Blueprint $table) => $table->unsignedInteger('document_revision')->default(1));
        Schema::table('filing_authorizations', fn (Blueprint $table) => $table->json('signed_document_types')->nullable());
        if (DB::getDriverName() === 'sqlite') {
            DB::unprepared("CREATE TRIGGER filing_authorizations_immutable_document_types BEFORE UPDATE OF signed_document_types ON filing_authorizations WHEN OLD.signed_document_types IS NOT NEW.signed_document_types BEGIN SELECT RAISE(ABORT, 'Signed document membership is immutable.'); END");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            DB::unprepared('DROP TRIGGER IF EXISTS filing_authorizations_immutable_document_types');
        }
        Schema::table('filing_authorizations', fn (Blueprint $table) => $table->dropColumn('signed_document_types'));
        Schema::table('flight_manifests', fn (Blueprint $table) => $table->dropColumn('document_revision'));
        Schema::table('flights', fn (Blueprint $table) => $table->dropColumn('flight_document_revision'));
    }
};
