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
        Schema::create('floor_details', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_property_id');
            $table->unsignedBigInteger('wing_id');
            $table->string('floor_size')->nullable();
            $table->integer('pent_house_status')->nullable()->default(0);
            $table->timestamps();

            $table->foreign('wing_id')->references('id')->on('wing_details')->onDelete('cascade');
            $table->foreign('user_property_id')->references('id')->on('user_properties')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('floor_details');
    }
};
