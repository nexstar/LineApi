<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateSocialsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('socials', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('user_id'); // 關聯user
            $table->string('provider'); // Line
            $table->string('provider_user_id'); // Line user id
            $table->text('picture'); // line user picture
            $table->text('access_token'); // Line user access_token
            $table->text('refresh_token'); // Line user refresh_token
            $table->bigInteger('expires_in'); // Line user access_token expires_in
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('socials');
    }
}
