<?php

namespace ErnestDefoe\Garage\Search\Filter;

use ErnestDefoe\Garage\Build;
use Flarum\Search\Database\DatabaseSearchState;
use Flarum\Search\Filter\FilterInterface;
use Flarum\Search\SearchState;
use Flarum\Search\ValidateFilterTrait;

/**
 * @implements FilterInterface<DatabaseSearchState>
 */
class StageFilter implements FilterInterface
{
    use ValidateFilterTrait;

    public function getFilterKey(): string
    {
        return 'stage';
    }

    public function filter(SearchState $state, string|array $value, bool $negate): void
    {
        /** @var DatabaseSearchState $state */
        $stages = array_values(array_intersect($this->asStringArray($value), Build::STAGES));
        $state->getQuery()->{$negate ? 'whereNotIn' : 'whereIn'}('garage_builds.stage', $stages);
    }
}
