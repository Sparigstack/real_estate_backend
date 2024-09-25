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
            $table->string('contact_no', 20);
            $table->integer('source_id')->nullable(); // 1-refernce,2-social media,3-call,4-in person
            $table->integer('budget')->nullable()->default(0); 
            $table->integer('status')->nullable()->default(0); // 0-new,1-negotiation,2-in contact,3-highly interested,4-closed
            $table->integer('type')->nullable()->default(0); // 0-manual, 1-csv, 2-web form
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
