<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;

// Which stories and log updates were written in Scribe: those hold the
// formatter's XML, the rest plain text (see RichText).
return [
    'up' => function (Builder $schema) {
        $schema->table('garage_builds', function (Blueprint $table) {
            $table->boolean('story_formatted')->default(false);
        });
        $schema->table('garage_entries', function (Blueprint $table) {
            $table->boolean('body_formatted')->default(false);
        });
    },
    'down' => function (Builder $schema) {
        $schema->table('garage_builds', function (Blueprint $table) {
            $table->dropColumn('story_formatted');
        });
        $schema->table('garage_entries', function (Blueprint $table) {
            $table->dropColumn('body_formatted');
        });
    },
];
