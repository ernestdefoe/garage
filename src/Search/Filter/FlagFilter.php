<?php

namespace ErnestDefoe\Garage\Search\Filter;

use Flarum\Search\Database\DatabaseSearchState;
use Flarum\Search\Filter\FilterInterface;
use Flarum\Search\SearchState;
use Flarum\Search\ValidateFilterTrait;

/**
 * `featured` (picked by a moderator) and `published` (not a draft): a build
 * where the date is set.
 *
 * @implements FilterInterface<DatabaseSearchState>
 */
abstract class FlagFilter implements FilterInterface
{
    use ValidateFilterTrait;

    abstract protected function column(): string;

    public function filter(SearchState $state, string|array $value, bool $negate): void
    {
        /** @var DatabaseSearchState $state */
        $on = $this->asBool($value) !== $negate;
        $state->getQuery()->{$on ? 'whereNotNull' : 'whereNull'}($this->column());
    }
}
