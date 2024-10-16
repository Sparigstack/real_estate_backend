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
        Schema::create('payment_transactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('customer_id');
            $table->unsignedBigInteger('unit_id')->nullable();
            $table->unsignedBigInteger('property_id')->nullable();
            $table->integer('payment_type')->nullable()->default(0); //0 manual, 1 cheque scan 
            $table->decimal('amount', 10, 2);
            // $table->string('cheque_image_path')->nullable();
            $table->text('transaction_notes')->nullable();


            // Foreign keys
            $table->foreign('customer_id')->references('id')->on('customers')->onDelete('cascade');
            $table->foreign('unit_id')->references('id')->on('unit_details')->onDelete('cascade');
            $table->foreign('property_id')->references('id')->on('user_properties')->onDelete('cascade');
     
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_transactions');
    }
};
