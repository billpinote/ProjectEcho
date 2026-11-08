<?php

use App\Domain\FlightPlans\Support\FilingDocumentRegistry;
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
        if (! in_array(DB::getDriverName(), ['sqlite', 'mysql', 'mariadb'], true)) {
            throw new RuntimeException('AVSEC database guards require SQLite or MySQL/MariaDB.');
        }
        Schema::create('avsec_verifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('flight_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('verifier_user_id')->constrained('users')->restrictOnDelete();
            $table->string('verifier_name');
            $table->string('station', 4);
            $table->timestamp('verified_at');
            $table->unsignedInteger('flight_revision');
            $table->char('content_sha256', 64);
            $table->longText('approved_content');
            $table->string('pnp_reference_number')->nullable();
            $table->index(['station', 'verified_at']);
        });
        foreach ($this->guards() as [$name, $table, $operation, $condition]) {
            if (DB::getDriverName() === 'sqlite') {
                DB::unprepared("CREATE TRIGGER {$name} BEFORE {$operation} ON {$table} WHEN {$condition} BEGIN SELECT RAISE(ABORT, 'AVSEC verified submissions are immutable.'); END");
            } else {
                DB::unprepared("CREATE TRIGGER {$name} BEFORE {$operation} ON {$table} FOR EACH ROW BEGIN IF {$condition} THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'AVSEC verified submissions are immutable.'; END IF; END");
            }
        }
    }

    private function guards(): array
    {
        $guards = [];
        // A locking read sees newly committed confirmations even in an older MySQL repeatable-read transaction.
        $currentRead = DB::getDriverName() === 'sqlite' ? '' : ' LOCK IN SHARE MODE';
        $frozen = fn (string $id): string => "EXISTS (SELECT 1 FROM avsec_verifications v WHERE v.flight_id = {$id}{$currentRead})";
        $different = fn (string $column): string => DB::getDriverName() === 'sqlite'
            ? 'OLD."'.$column.'" IS NOT NEW."'.$column.'"'
            : 'NOT (OLD.`'.$column.'` <=> NEW.`'.$column.'`)';
        $columns = array_diff(Schema::getColumnListing('flights'), [...FilingDocumentRegistry::OPERATIONAL_FIELDS, 'updated_at']);
        $changes = implode(' OR ', array_map($different, $columns));
        $guards[] = ['avsec_flight_update', 'flights', 'UPDATE', $frozen('OLD.id')." AND ({$changes})"];
        $guards[] = ['avsec_flight_delete', 'flights', 'DELETE', $frozen('OLD.id')];
        $guards[] = ['avsec_acceptance_gate', 'flights', 'UPDATE', "UPPER(TRIM(NEW.departure_aerodrome)) = 'RPUS' AND NEW.status IN ('accepted', 'active', 'completed') AND OLD.status NOT IN ('accepted', 'active', 'completed') AND NOT ".$frozen('OLD.id')];
        foreach (['flight_manifests', 'manifest_persons', 'filing_authorizations'] as $table) {
            foreach (['INSERT', 'UPDATE', 'DELETE'] as $operation) {
                $references = $operation === 'INSERT' ? ['NEW'] : ($operation === 'DELETE' ? ['OLD'] : ['OLD', 'NEW']);
                $checks = [];
                foreach ($references as $ref) {
                    $checks[] = $table === 'manifest_persons'
                        ? "EXISTS (SELECT 1 FROM flight_manifests m JOIN avsec_verifications v ON v.flight_id = m.flight_id WHERE m.id = {$ref}.flight_manifest_id{$currentRead})"
                        : $frozen("{$ref}.flight_id");
                }
                $guards[] = ['avsec_'.$table.'_'.strtolower($operation), $table, $operation, implode(' OR ', $checks)];
            }
        }
        foreach (['UPDATE', 'DELETE'] as $operation) {
            $guards[] = ['avsec_evidence_'.strtolower($operation), 'avsec_verifications', $operation, '1 = 1'];
            $guards[] = ['avsec_audit_'.strtolower($operation), 'flight_plan_events', $operation, $frozen('OLD.flight_id')];
        }
        // MySQL does not execute child-table triggers for foreign-key SET NULL actions.
        $userColumns = ['user_id', 'filed_by_user_id', 'prepared_by_user_id', 'pilot_id', 'pilot_in_command_user_id',
            'pic_authorized_by_user_id', 'pic_authorization_declined_by_user_id'];
        $userReferences = implode(' OR ', array_map(fn (string $column): string => "f.{$column} = OLD.id", $userColumns));
        $guards[] = ['avsec_user_delete', 'users', 'DELETE',
            "EXISTS (SELECT 1 FROM flights f JOIN avsec_verifications v ON v.flight_id = f.id WHERE ({$userReferences}){$currentRead})"
            ." OR EXISTS (SELECT 1 FROM flight_plan_events e JOIN avsec_verifications v ON v.flight_id = e.flight_id WHERE e.actor_user_id = OLD.id{$currentRead})"
            ." OR EXISTS (SELECT 1 FROM flight_manifests m JOIN avsec_verifications v ON v.flight_id = m.flight_id WHERE m.created_by_user_id = OLD.id{$currentRead})",
        ];
        $guards[] = ['avsec_operator_delete', 'operators', 'DELETE',
            "EXISTS (SELECT 1 FROM flights f JOIN avsec_verifications v ON v.flight_id = f.id WHERE f.operator_id = OLD.id{$currentRead})",
        ];

        return $guards;
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach ($this->guards() as [$name]) {
            DB::unprepared('DROP TRIGGER IF EXISTS '.$name);
        }
        Schema::dropIfExists('avsec_verifications');
    }
};
