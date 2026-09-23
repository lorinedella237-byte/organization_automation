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
         Schema::create('social_media_auth', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 50);
            $table->string('provider_user_id', 190);
            $table->string('provider_email', 190)->nullable();
            $table->text('access_token_encrypted')->nullable();
            $table->text('refresh_token_encrypted')->nullable();
            $table->dateTime('token_expires_at')->nullable();
            $table->text('scopes')->nullable();
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
