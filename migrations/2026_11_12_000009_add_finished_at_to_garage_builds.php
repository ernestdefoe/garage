<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;

// When a build reached Finished. Builds already finished take their latest
// log entry's date, or the day they were published (or added, for a draft).
return [
    'up' => function (Builder $schema) {
        $schema->table('garage_builds', function (Blueprint $table) {
            $table->dateTime('finished_at')->nullable();
        });

        $db = $schema->getConnection();
        $db->table('garage_builds')->where('stage', 'complete')->select(['id', 'published_at', 'created_at'])->orderBy('id')
            ->chunk(500, function ($builds) use ($db) {
                $latest = $db->table('garage_entries')->whereIn('build_id', $builds->pluck('id'))
                    ->groupBy('build_id')->selectRaw('build_id, max(happened_on) as day')->pluck('day', 'build_id');

                foreach ($builds as $build) {
                    $day = $latest[$build->id] ?? null;
                    $db->table('garage_builds')->where('id', $build->id)->update([
                        'finished_at' => $day ? substr((string) $day, 0, 10).' 00:00:00' : ($build->published_at ?? $build->created_at),
                    ]);
                }
            });
    },
    'down' => function (Builder $schema) {
        $schema->table('garage_builds', function (Blueprint $table) {
            $table->dropColumn('finished_at');
        });
    },
];
