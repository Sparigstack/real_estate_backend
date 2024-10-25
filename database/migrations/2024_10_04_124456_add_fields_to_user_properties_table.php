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
        Schema::table('user_properties', function (Blueprint $table) {
            //
            $table->unsignedBigInteger('state_id')->nullable()->after('pincode');
            $table->unsignedBigInteger('city_id')->nullable();
            $table->string('area')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_properties', function (Blueprint $table) {
            //
            $table->dropColumn('state_id');
            $table->dropColumn('city_id');
            $table->dropColumn('area');
        });
    }
};
