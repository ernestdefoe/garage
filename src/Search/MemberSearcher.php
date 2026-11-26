<?php

namespace ErnestDefoe\Garage\Search;

use ErnestDefoe\Garage\Member;
use Flarum\Search\Database\AbstractSearcher;
use Flarum\User\User;
use Illuminate\Database\Eloquent\Builder;

class MemberSearcher extends AbstractSearcher
{
    /** @return Builder<Member> */
    public function getQuery(User $actor): Builder
    {
        return Member::query()->select('garage_members.*')->whereVisibleTo($actor);
    }
}
