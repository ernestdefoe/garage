<?php

namespace ErnestDefoe\Garage\Search\Filter;

use Flarum\Search\Database\DatabaseSearchState;
use Flarum\Search\Filter\FilterInterface;
use Flarum\Search\SearchState;
use Flarum\Search\ValidateFilterTrait;

/**
 * `filter[spec]=drivetrain:AWD`: builds whose spec sheet has that value (the
 * select fields an admin marked as filters). A JSON path, which Laravel
 * compiles per database with the table prefix in place; never
 * whereJsonContains, which breaks on a prefixed SQLite.
 *
 * @implements FilterInterface<DatabaseSearchState>
 */
class SpecFilter implements FilterInterface
{
    use ValidateFilterTrait;

    public function getFilterKey(): string
    {
        return 'spec';
    }

    public function filter(SearchState $state, string|array $value, bool $negate): void
    {
        /** @var DatabaseSearchState $state */
        $query = $state->getQuery();

        // One pair: a select option may hold a comma, which a list would split.
        foreach ([is_array($value) ? (string) reset($value) : $value] as $pair) {
            [$key, $wanted] = array_pad(explode(':', $pair, 2), 2, '');
            if (! preg_match('/^[a-z0-9_]{1,40}$/', $key) || $wanted === '') {
                continue;
            }

            $query->where("garage_builds.specs->$key", $negate ? '!=' : '=', $wanted);
        }
    }
}
