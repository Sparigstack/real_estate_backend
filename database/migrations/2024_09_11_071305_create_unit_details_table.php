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
        Schema::create('unit_details', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_property_id');
            $table->unsignedBigInteger('wing_id');
            $table->unsignedBigInteger('floor_id');
            $table->string('name')->nullable();
            $table->string('status_id')->nullable();
            $table->float('square_feet')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('unit_details');
    }
};
