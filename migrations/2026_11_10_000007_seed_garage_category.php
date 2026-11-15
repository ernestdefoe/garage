<?php

use Carbon\Carbon;
use Illuminate\Database\Schema\Builder;

// One category, so members can add a build before the admin has set any up.
return [
    'up' => function (Builder $schema) {
        $db = $schema->getConnection();
        if (! $db->table('garage_categories')->exists()) {
            $db->table('garage_categories')->insert([
                'name' => 'Builds',
                'slug' => 'builds',
                'icon' => 'fas fa-screwdriver-wrench',
                'color' => '#c2410c',
                'fields' => json_encode([]),
                'position' => 0,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ]);
        }
    },
    // The seeded category goes only while nothing has been filed under it.
    'down' => function (Builder $schema) {
        $db = $schema->getConnection();
        $id = $db->table('garage_categories')->where('slug', 'builds')->value('id');
        if ($id && ! $db->table('garage_builds')->where('category_id', $id)->exists()) {
            $db->table('garage_categories')->where('id', $id)->delete();
        }
    },
];
