<?php

use Flarum\Database\Migration;
use Illuminate\Database\Schema\Blueprint;

// On a build, or on one of its log entries.
return Migration::createTable('garage_comments', function (Blueprint $table) {
    $table->increments('id');
    $table->unsignedInteger('build_id');
    $table->unsignedInteger('entry_id')->nullable();
    $table->unsignedInteger('user_id');
    $table->text('body');
    $table->dateTime('created_at');
    $table->dateTime('edited_at')->nullable();

    $table->foreign('build_id')->references('id')->on('garage_builds')->cascadeOnDelete();
    $table->foreign('entry_id')->references('id')->on('garage_entries')->cascadeOnDelete();
    $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
    $table->index(['build_id', 'created_at']);
});
