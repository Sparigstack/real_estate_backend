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
        Schema::table('floor_and_property_details_tables', function (Blueprint $table) {
            //
            Schema::table('floor_details', function (Blueprint $table) {
                $table->renameColumn('user_property_id', 'property_id');
            });
    
            // Rename column in property_details table
            Schema::table('property_details', function (Blueprint $table) {
                $table->renameColumn('user_property_id', 'property_id');
            });
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('floor_and_property_details_tables', function (Blueprint $table) {
            //
            Schema::table('floor_details', function (Blueprint $table) {
                $table->renameColumn('property_id', 'user_property_id');
            });
    
            // Revert column name changes in property_details table
            Schema::table('property_details', function (Blueprint $table) {
                $table->renameColumn('property_id', 'user_property_id');
            });
        });
    }
};
