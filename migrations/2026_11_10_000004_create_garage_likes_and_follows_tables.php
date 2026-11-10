<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;

return [
    'up' => function (Builder $schema) {
        foreach (['garage_likes', 'garage_follows'] as $name) {
            $schema->create($name, function (Blueprint $table) {
                $table->unsignedInteger('build_id');
                $table->unsignedInteger('user_id');
                $table->dateTime('created_at');

                $table->primary(['build_id', 'user_id']);
                $table->index('user_id');
                $table->foreign('build_id')->references('id')->on('garage_builds')->cascadeOnDelete();
                $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            });
        }
    },
    'down' => function (Builder $schema) {
        $schema->dropIfExists('garage_follows');
        $schema->dropIfExists('garage_likes');
    },
];
