<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
         Schema::create('permission', function (Blueprint $table) {
            $table->id();
            $table->string('code', 120)->unique();
    $table->string('module', 60);
    $table->string('action', 60);
    $table->string('resource', 100);
    $table->enum('scope', ['own', 'department', 'all'])
          ->default('own');
    $table->string('name', 120);
    $table->string('description', 255)->nullable();
    $table->enum('status', ['Active', 'Inactive'])
          ->default('Active');
    $table->timestamps();


         });
        //
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        //
    }
};
