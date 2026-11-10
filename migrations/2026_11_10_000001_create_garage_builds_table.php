<?php

use Flarum\Database\Migration;
use Illuminate\Database\Schema\Blueprint;

return Migration::createTable('garage_builds', function (Blueprint $table) {
    $table->increments('id');
    $table->unsignedInteger('user_id');
    $table->unsignedInteger('category_id');
    $table->string('title', 120);
    $table->string('slug', 140);
    $table->string('tagline', 160)->nullable();
    $table->text('story')->nullable();
    $table->string('stage', 20)->default('in_progress');
    $table->json('specs')->nullable();
    $table->json('photos')->nullable();
    $table->unsignedInteger('last_entry_id')->nullable();
    $table->unsignedInteger('entry_count')->default(0);
    $table->unsignedInteger('like_count')->default(0);
    $table->unsignedInteger('comment_count')->default(0);
    $table->unsignedInteger('follower_count')->default(0);
    $table->decimal('spent', 12, 2)->nullable();
    $table->dateTime('published_at')->nullable();
    $table->dateTime('featured_at')->nullable();
    $table->dateTime('last_activity_at')->nullable();
    $table->timestamps();

    $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
    $table->foreign('category_id')->references('id')->on('garage_categories')->cascadeOnDelete();
    $table->index(['published_at', 'last_activity_at']);
    $table->index('user_id');
    $table->index('category_id');
});
