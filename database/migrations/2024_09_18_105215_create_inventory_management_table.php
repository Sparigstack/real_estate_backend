<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateInventoryManagementTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('inventory_management', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('property_id')->nullable();
            $table->string('raw_material_name', 255);
            $table->integer('current_stock')->default(0);
            $table->integer('threshold_qty')->default(0);
            $table->integer('total_qty')->default(0);
            $table->boolean('replenish_alert')->default(false);
            $table->timestamps();
            
            // Add foreign key constraint if property_id refers to a property table
            $table->foreign('property_id')->references('id')->on('properties')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('inventory_management');
    }
}
