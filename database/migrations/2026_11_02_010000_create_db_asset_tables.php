<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateDbAssetTables extends Migration
{
    /**
     * Site logo, favicon and per-user custom backgrounds are stored in the
     * database on deployments with a read-only filesystem (Vercel). Uploaded
     * theme ZIPs are unpacked into theme_files, one row per file, so a theme
     * can be served without ever writing to the container filesystem.
     *
     * Local installations keep using the original files on disk.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('site_assets')) {
            Schema::create('site_assets', function (Blueprint $table) {
                $table->string('key', 191)->primary();
                $table->text('mime');
                $table->text('extension')->nullable();
                $table->text('data'); // base64 encoded bytes
                $table->timestampsTz();
            });
        }

        if (!Schema::hasTable('theme_files')) {
            Schema::create('theme_files', function (Blueprint $table) {
                $table->string('theme', 100);
                $table->string('path', 500);
                $table->text('mime');
                $table->integer('size')->default(0);
                $table->text('data'); // base64 encoded bytes
                $table->timestampsTz();
                $table->primary(['theme', 'path']);
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
        Schema::dropIfExists('theme_files');
        Schema::dropIfExists('site_assets');
    }
}
