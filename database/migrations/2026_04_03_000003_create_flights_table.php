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
        Schema::create('flights', function (Blueprint $table) {
            $table->id();

            $table->time('time_start_up')->nullable();
            $table->time('time_shutdown')->nullable();
            $table->time('time_block_off')->nullable();
            $table->time('time_block_on')->nullable();
            $table->time('time_airborne')->nullable();
            $table->time('time_touchdown')->nullable();

            $table->text('addressees')->nullable();
            $table->string('originator')->nullable();
            $table->date('date_of_filing')->nullable();
            $table->date('date_of_flight')->nullable();
            $table->string('aircraft_identification')->nullable();
            $table->string('flight_rules')->nullable();
            $table->string('type_of_flight')->nullable();
            $table->string('number')->nullable();
            $table->string('type_of_aircraft')->nullable();
            $table->string('wake_turbulence_cat')->nullable();
            $table->string('equipment_10a')->nullable();
            $table->string('equipment_10b')->nullable();
            $table->string('departure_aerodrome')->nullable();
            $table->time('proposed_time')->nullable();
            $table->string('cruising_speed')->nullable();
            $table->string('level')->nullable();
            $table->text('route')->nullable();
            $table->string('flight_crew_and_passengers')->nullable();
            $table->string('destination_aerodrome')->nullable();
            $table->time('total_eet')->nullable();
            $table->string('altn_aerodrome_1')->nullable();
            $table->string('altn_aerodrome_2')->nullable();

            $table->text('other_info')->nullable();
            $table->text('other_information')->nullable();
            $table->string('other_info_rmk')->nullable();
            $table->string('other_info_pbn')->nullable();
            $table->string('other_info_route')->nullable();
            $table->string('other_info_dep')->nullable();
            $table->string('other_info_dest')->nullable();
            $table->string('other_info_typ')->nullable();
            $table->string('other_info_reg')->nullable();
            $table->string('other_info_altn_1')->nullable();
            $table->string('other_info_altn_2')->nullable();
            $table->string('other_info_opr')->nullable();
            $table->string('other_info_airworthiness')->nullable();
            $table->date('other_info_expiry_date_to_operate')->nullable();
            $table->date('other_info_dof')->nullable();

            $table->time('endurance')->nullable();
            $table->unsignedInteger('persons_on_board')->nullable();

            $table->boolean('emergency_radio_uhf')->default(true);
            $table->boolean('emergency_radio_vhf')->default(true);
            $table->boolean('emergency_radio_elt')->default(true);

            $table->boolean('survival_equipment_polar')->default(true);
            $table->boolean('survival_equipment_desert')->default(true);
            $table->boolean('survival_equipment_maritime')->default(true);
            $table->boolean('survival_equipment_jungle')->default(true);

            $table->boolean('jackets_light')->default(true);
            $table->boolean('jackets_fluores')->default(true);
            $table->boolean('jackets_uhf')->default(true);
            $table->boolean('jackets_vhf')->default(true);

            $table->boolean('dinghies_enabled')->default(false);
            $table->unsignedInteger('dinghies_number')->nullable();
            $table->unsignedInteger('dinghies_capacity')->nullable();
            $table->string('dinghies_cover')->nullable();
            $table->string('dinghies_color')->nullable();

            $table->string('aircraft_colour_and_markings')->nullable();
            $table->text('remarks')->nullable();

            $table->string('pilot_in_command')->nullable();
            $table->string('filed_by_name')->nullable();
            $table->string('filed_by_signature')->nullable();
            $table->string('pilot_license_no')->nullable();
            $table->string('pilot_ratings')->nullable();
            $table->date('license_expiry_date')->nullable();

            $table->boolean('authorized_representative_enabled')->default(false);
            $table->string('authorized_representative_name')->nullable();
            $table->string('authorized_representative_role')->nullable();
            $table->string('authorized_representative_id_license')->nullable();
            $table->date('authorized_representative_expiry_date')->nullable();

            $table->string('received_by')->nullable();
            $table->date('received_date')->nullable();
            $table->time('received_time')->nullable();
            $table->string('received_facility')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('flights');
    }
};
