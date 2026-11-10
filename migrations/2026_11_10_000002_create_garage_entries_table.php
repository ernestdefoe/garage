<?php

use Flarum\Database\Migration;
use Illuminate\Database\Schema\Blueprint;

// The build log: dated updates, each with its own photos.
return Migration::createTable('garage_entries', function (Blueprint $table) {
    $table->increments('id');
    $table->unsignedInteger('build_id');
    $table->unsignedInteger('user_id');
    $table->string('title', 120);
    $table->text('body')->nullable();
    $table->json('photos')->nullable();
    $table->decimal('cost', 12, 2)->nullable();
    $table->date('happened_on');
    $table->unsignedInteger('comment_count')->default(0);
    $table->timestamps();

    $table->foreign('build_id')->references('id')->on('garage_builds')->cascadeOnDelete();
    $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
    $table->index(['build_id', 'happened_on']);
});
