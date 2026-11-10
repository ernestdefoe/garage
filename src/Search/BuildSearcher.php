<?php

namespace ErnestDefoe\Garage\Search;

use ErnestDefoe\Garage\Build;
use Flarum\Search\Database\AbstractSearcher;
use Flarum\User\User;
use Illuminate\Database\Eloquent\Builder;

class BuildSearcher extends AbstractSearcher
{
    /** @return Builder<Build> */
    public function getQuery(User $actor): Builder
    {
        return Build::query()->select('garage_builds.*')->whereVisibleTo($actor);
    }
}
