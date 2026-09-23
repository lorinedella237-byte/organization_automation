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
         Schema::create('mail_notifications', function (Blueprint $table) {
            $table->id();
            $table->char('uuid', 36)->unique();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('to_email', 190);
            $table->text('cc_email')->nullable();
            $table->text('bcc_email')->nullable();
            $table->string('template_code', 100);
            $table->string('subject', 255);
            $table->json('payload')->nullable();
            $table->enum('status', [
                'Queued',
                'Sending',
                'Sent',
                'Failed',
                'Cancelled'
            ])->default('Queued');
            $table->unsignedTinyInteger('priority')->default(5);
            $table->dateTime('scheduled_at')->useCurrent();
            $table->dateTime('sent_at')->nullable();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->unsignedTinyInteger('max_attempts')->default(5);
            $table->text('last_error')->nullable();
            $table->string('idempotency_key', 190)->unique();
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
