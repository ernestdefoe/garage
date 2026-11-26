<?php

use Flarum\Database\Migration;
use Illuminate\Database\Schema\Blueprint;

// A build's team: members its owner invited. Pending until the invitee accepts.
return Migration::createTable('garage_members', function (Blueprint $table) {
    $table->increments('id');
    $table->unsignedInteger('build_id');
    $table->unsignedInteger('user_id');
    $table->string('role', 60)->nullable();
    $table->string('status', 10)->default('pending');
    $table->unsignedInteger('invited_by')->nullable();
    $table->dateTime('created_at');
    $table->dateTime('accepted_at')->nullable();

    $table->unique(['build_id', 'user_id']);
    $table->index(['user_id', 'status']);
    $table->foreign('build_id')->references('id')->on('garage_builds')->cascadeOnDelete();
    $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
    $table->foreign('invited_by')->references('id')->on('users')->nullOnDelete();
});
