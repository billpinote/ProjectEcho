<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('filing_authorizations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('flight_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('flight_revision');
            $table->string('status', 24)->default('pending');
            $table->longText('documents');
            $table->string('fingerprint', 64);
            $table->longText('authorized_documents')->nullable();
            $table->longText('pdf_documents')->nullable();
            $table->string('authorized_fingerprint', 64)->nullable();
            $table->foreignId('pic_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->json('authentication')->nullable();
            $table->foreignId('pilot_signature_id')->nullable()->constrained()->restrictOnDelete();
            $table->unsignedInteger('signature_version')->nullable();
            $table->string('signature_sha256', 64)->nullable();
            $table->timestamp('requested_at');
            $table->timestamp('decided_at')->nullable();
            $table->text('reason')->nullable();
            $table->index(['flight_id', 'id']);
        });
        if (DB::getDriverName() === 'sqlite') {
            DB::unprepared("CREATE TRIGGER filing_authorizations_immutable_update BEFORE UPDATE ON filing_authorizations WHEN OLD.status != 'pending' OR NEW.status NOT IN ('authorized', 'declined') OR OLD.flight_id IS NOT NEW.flight_id OR OLD.flight_revision IS NOT NEW.flight_revision OR OLD.documents IS NOT NEW.documents OR OLD.fingerprint IS NOT NEW.fingerprint OR OLD.requested_at IS NOT NEW.requested_at BEGIN SELECT RAISE(ABORT, 'Filing authorization evidence is immutable.'); END");
            DB::unprepared("CREATE TRIGGER filing_authorizations_immutable_delete BEFORE DELETE ON filing_authorizations BEGIN SELECT RAISE(ABORT, 'Filing authorization evidence must be preserved.'); END");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            DB::unprepared('DROP TRIGGER IF EXISTS filing_authorizations_immutable_update');
            DB::unprepared('DROP TRIGGER IF EXISTS filing_authorizations_immutable_delete');
        }
        Schema::dropIfExists('filing_authorizations');
    }
};
