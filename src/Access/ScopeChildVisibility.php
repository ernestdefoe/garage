<?php

namespace ErnestDefoe\Garage\Access;

use ErnestDefoe\Garage\Build;
use Flarum\User\User;
use Illuminate\Database\Eloquent\Builder;

/** Log entries and comments: seen by whoever sees their build. */
class ScopeChildVisibility
{
    public function __invoke(User $actor, Builder $query): void
    {
        $table = $query->getModel()->getTable();

        $query->whereIn("$table.build_id", Build::query()->whereVisibleTo($actor)->select('garage_builds.id'));
    }
}
