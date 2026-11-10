<?php

namespace ErnestDefoe\Garage\Search;

use ErnestDefoe\Garage\Entry;
use Flarum\Search\Database\AbstractSearcher;
use Flarum\User\User;
use Illuminate\Database\Eloquent\Builder;

class EntrySearcher extends AbstractSearcher
{
    /** @return Builder<Entry> */
    public function getQuery(User $actor): Builder
    {
        return Entry::query()->select('garage_entries.*')->whereVisibleTo($actor);
    }
}
