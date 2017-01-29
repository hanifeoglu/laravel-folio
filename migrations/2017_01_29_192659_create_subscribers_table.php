<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateSubscribersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
     public function up() {

       Schema::create('space_subscribers', function (Blueprint $table) {
               $table->increments('id');
               $table->timestamps();
               $table->softDeletes();
               $table->string('email')->nullable();
               $table->string('name')->nullable();
               $table->string('source')->nullable()->default('web');
       });

       $subscriber = new Subscriber();
       $subscriber->name = "Nono Martinez Alonso";
       $subscriber->email = "mail@domain.com";
       $subscriber->source = "Teaser";
       $subscriber->save();

     }

     /**
      * Reverse the migrations.
      *
      * @return void
      */
     public function down()
     {
         Schema::drop('space_subscribers');
     }
 }
