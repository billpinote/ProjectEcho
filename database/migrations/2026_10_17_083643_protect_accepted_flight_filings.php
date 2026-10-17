<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            return;
        }

        $accepted = "(f.accepted_by_user_id IS NOT NULL OR f.status IN ('accepted', 'active', 'completed') OR EXISTS (SELECT 1 FROM flight_plan_events e WHERE e.flight_id = f.id AND e.event_type = 'atc_accepted'))";
        foreach (['INSERT', 'UPDATE', 'DELETE'] as $operation) {
            $references = $operation === 'INSERT' ? ['NEW'] : ($operation === 'DELETE' ? ['OLD'] : ['OLD', 'NEW']);
            $manifestChecks = [];
            $personChecks = [];
            foreach ($references as $reference) {
                $manifestChecks[] = "EXISTS (SELECT 1 FROM flights f WHERE f.id = {$reference}.flight_id AND {$accepted})";
                $personChecks[] = "EXISTS (SELECT 1 FROM flight_manifests m JOIN flights f ON f.id = m.flight_id WHERE m.id = {$reference}.flight_manifest_id AND {$accepted})";
            }
            foreach (['flight_manifests' => $manifestChecks, 'manifest_persons' => $personChecks] as $table => $checks) {
                $name = $table.'_accepted_'.strtolower($operation);
                $when = implode(' OR ', $checks);
                DB::unprepared("CREATE TRIGGER {$name} BEFORE {$operation} ON {$table} WHEN {$when} BEGIN SELECT RAISE(ABORT, 'Accepted manifests are immutable; create a new flight plan.'); END");
            }
        }

        $operational = ['status', 'updated_at', 'reviewed_at', 'revised_eobt', 'cancelled_at', 'cancelled_by_user_id', 'time_start_up', 'time_shutdown', 'time_block_off', 'time_block_on', 'time_airborne', 'time_touchdown'];
        $filedColumns = array_diff(Schema::getColumnListing('flights'), $operational);
        $changes = implode(' OR ', array_map(fn (string $column): string => 'OLD."'.$column.'" IS NOT NEW."'.$column.'"', $filedColumns));
        $locked = str_replace('f.', 'OLD.', $accepted);
        DB::unprepared("CREATE TRIGGER flights_accepted_update BEFORE UPDATE ON flights WHEN {$locked} AND ({$changes}) BEGIN SELECT RAISE(ABORT, 'Accepted flight-plan contents are immutable.'); END");
        DB::unprepared("CREATE TRIGGER flights_accepted_delete BEFORE DELETE ON flights WHEN {$locked} BEGIN SELECT RAISE(ABORT, 'Accepted filings must be preserved.'); END");
        DB::unprepared("CREATE TRIGGER manifests_parent_update BEFORE UPDATE OF flight_id, created_by_user_id ON flight_manifests WHEN OLD.flight_id IS NOT NEW.flight_id OR OLD.created_by_user_id IS NOT NEW.created_by_user_id BEGIN SELECT RAISE(ABORT, 'Manifest parent and creator cannot change.'); END");
        DB::unprepared("CREATE TRIGGER persons_parent_update BEFORE UPDATE OF flight_manifest_id ON manifest_persons WHEN OLD.flight_manifest_id IS NOT NEW.flight_manifest_id BEGIN SELECT RAISE(ABORT, 'Manifest person parent cannot change.'); END");
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            return;
        }
        foreach (['flight_manifests', 'manifest_persons'] as $table) {
            foreach (['insert', 'update', 'delete'] as $operation) {
                DB::unprepared('DROP TRIGGER IF EXISTS '.$table.'_accepted_'.$operation);
            }
        }
        foreach (['flights_accepted_update', 'flights_accepted_delete', 'manifests_parent_update', 'persons_parent_update'] as $name) {
            DB::unprepared('DROP TRIGGER IF EXISTS '.$name);
        }
    }
};
