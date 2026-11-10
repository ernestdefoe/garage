<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;

// flarum/gdpr's anonymization sets every users column it doesn't know to null: a NOT NULL
// count made erasing a member fail on any forum running both.
return [
    'up' => function (Builder $schema) {
        $schema->table('users', function (Blueprint $table) {
            $table->unsignedInteger('garage_build_count')->nullable()->default(0)->change();
        });
    },
    'down' => function (Builder $schema) {
    },
];
