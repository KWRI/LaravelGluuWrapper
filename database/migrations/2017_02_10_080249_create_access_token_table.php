<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateAccessTokenTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('access_tokens', function (Blueprint $table) {
            $table->increments('id');
            $table->string('access_token');
            $table->integer('expiry_in');
            $table->string('refresh_token');

            $table->string('client_id');

            $table->string('uid')->nullable();
            $table->string('email')->nullable();
            $table->string('company')->nullable();
            $table->string('app_name')->nullable();
            $table->string('api_key')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('access_tokens');
    }
}
