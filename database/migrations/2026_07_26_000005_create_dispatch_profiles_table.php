<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dispatch_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('dispatcher_license_number')->nullable();
            $table->string('dispatcher_certificate')->nullable();
            $table->string('department')->nullable();
            $table->string('position')->nullable();
            $table->string('office_phone')->nullable();
            $table->string('mobile_number')->nullable();
            $table->string('shift')->nullable();
            $table->text('remarks')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dispatch_profiles');
    }
};
