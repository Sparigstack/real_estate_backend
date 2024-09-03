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
        Schema::table('users', function (Blueprint $table) {
            // Make fields nullable
            $table->string('name')->nullable()->change();
            $table->string('contact_no')->nullable()->change();
            $table->string('otp')->nullable(false)->change();

        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Revert fields back to non-nullable
            $table->string('name')->nullable(false)->change();
            $table->string('contact_no')->nullable(false)->change();
            $table->string('otp')->nullable()->change();

        });
    }
};
