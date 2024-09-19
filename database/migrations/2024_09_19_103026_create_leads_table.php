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
        Schema::create('leads', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('property_id');
            $table->string('name', 255);
            $table->string('email', 255);
            $table->string('phone', 20);
            $table->string('source', 100);  // manual, csv, web form
            // $table->enum('status', ['new', 'contacted', 'converted'])->default('new');
            $table->timestamps();
            
            // If you have a properties table, you can set up a foreign key
            $table->foreign('property_id')->references('id')->on('properties')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('leads');
    }
};
