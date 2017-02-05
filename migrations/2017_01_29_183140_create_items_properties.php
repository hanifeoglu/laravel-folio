<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateItemsProperties extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
     public function up() {

       Schema::create('space_item_properties', function (Blueprint $table) {
               $table->increments('id');
               $table->integer('item_id')->nullable();
               $table->timestamps();
               $table->string('name')->nullable();
               $table->string('value')->nullable();
               $table->string('label')->nullable();
       });



     }

     /**
      * Reverse the migrations.
      *
      * @return void
      */
     public function down()
     {
         Schema::drop('space_item_properties');
     }
 }