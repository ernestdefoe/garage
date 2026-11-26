<?php

namespace ErnestDefoe\Garage\Search\Filter;

use ErnestDefoe\Garage\Member;
use Flarum\Search\Database\DatabaseSearchState;
use Flarum\Search\Filter\FilterInterface;
use Flarum\Search\SearchState;
use Flarum\Search\ValidateFilterTrait;

/**
 * Builds a member is on the team of (accepted), not their own: the profile's
 * "Team builds".
 *
 * @implements FilterInterface<DatabaseSearchState>
 */
class TeamFilter implements FilterInterface
{
    use ValidateFilterTrait;

    public function getFilterKey(): string
    {
        return 'team';
    }

    public function filter(SearchState $state, string|array $value, bool $negate): void
    {
        /** @var DatabaseSearchState $state */
        $members = Member::query()->select('build_id')->where('user_id', $this->asInt($value))->where('status', Member::ACCEPTED);

        $state->getQuery()->{$negate ? 'whereNotIn' : 'whereIn'}('garage_builds.id', $members);
    }
}
