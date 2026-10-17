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
        Schema::create('flight_manifests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('flight_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('created_by_user_id')->constrained('users')->restrictOnDelete();
            $table->string('aircraft_owner_name')->nullable();
            $table->string('aircraft_owner_contact_number')->nullable();
            $table->string('home_base_hangar')->nullable();
            $table->date('coa_expiry_date')->nullable();
            $table->string('cargo_type')->nullable();
            $table->string('cargo_quantity')->nullable();
            $table->decimal('total_cargo_weight', 12, 3)->nullable();
            $table->text('remarks')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('flight_manifests');
    }
};
