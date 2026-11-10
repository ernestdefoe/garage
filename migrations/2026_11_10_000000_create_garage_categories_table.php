<?php

use Flarum\Database\Migration;
use Illuminate\Database\Schema\Blueprint;

// What a forum's builds are sorted into. `fields` is the spec sheet the admin designs for it.
return Migration::createTable('garage_categories', function (Blueprint $table) {
    $table->increments('id');
    $table->string('name', 80);
    $table->string('slug', 100);
    $table->string('description', 300)->nullable();
    $table->string('icon', 60)->nullable();
    $table->string('color', 20)->nullable();
    $table->json('fields')->nullable();
    $table->unsignedInteger('position')->default(0);
    $table->unsignedInteger('build_count')->default(0);
    $table->timestamps();

    $table->index('position');
});
