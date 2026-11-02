<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateUserAvatarsTable extends Migration
{
    /**
     * Profile pictures are stored in the database on deployments with a
     * read-only filesystem (Vercel). Local installations keep using
     * assets/img, but the table is harmless there too.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('user_avatars')) {
            Schema::create('user_avatars', function (Blueprint $table) {
                $table->bigInteger('user_id')->primary();
                $table->text('mime');
                $table->text('extension');
                $table->text('data'); // base64 encoded image bytes
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('user_avatars');
    }
}
