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
        Schema::create('wing_details', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_property_id');
            $table->string('name')->nullable();
            $table->string('total_floors')->nullable();
            $table->timestamps();

            $table->foreign('user_property_id')->references('id')->on('user_properties')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('wing_details');
    }
};
