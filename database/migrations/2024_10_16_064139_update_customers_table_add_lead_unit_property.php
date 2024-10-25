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
        //
        Schema::table('customers', function (Blueprint $table) {
            $table->unsignedBigInteger('lead_unit_id')->nullable()->after('id');
            // $table->unsignedBigInteger('unit_id')->nullable()->after('lead_id');
            $table->unsignedBigInteger('property_id')->nullable();

            // Foreign keys
            $table->foreign('lead_unit_id')->references('id')->on('lead_unit')->onDelete('cascade');
            // $table->foreign('unit_id')->references('id')->on('unit_details')->onDelete('cascade');
            $table->foreign('property_id')->references('id')->on('user_properties')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
        Schema::table('customers', function (Blueprint $table) {
            $table->dropForeign(['lead_unit_id']);
            // $table->dropForeign(['unit_id']);
            $table->dropForeign(['property_id']);
            $table->dropColumn(['lead_unit_id', 'property_id']);
        });
    }
};
