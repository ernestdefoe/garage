<?php

namespace ErnestDefoe\Garage\Search\Filter;

use Flarum\Search\Database\DatabaseSearchState;
use Flarum\Search\Filter\FilterInterface;
use Flarum\Search\SearchState;
use Flarum\Search\ValidateFilterTrait;
use Illuminate\Database\Eloquent\Builder;

/**
 * `filter[text]=turbo`: builds whose title or tagline holds the words, in any case. Each
 * word must match; LIKE wildcards in what was typed are matched literally.
 *
 * @implements FilterInterface<DatabaseSearchState>
 */
class TextFilter implements FilterInterface
{
    use ValidateFilterTrait;

    public function getFilterKey(): string
    {
        return 'text';
    }

    public function filter(SearchState $state, string|array $value, bool $negate): void
    {
        /** @var DatabaseSearchState $state */
        $words = array_slice(preg_split('/\s+/', mb_substr(trim($this->asString($value)), 0, 100), -1, PREG_SPLIT_NO_EMPTY) ?: [], 0, 6);

        foreach ($words as $word) {
            $like = '%'.addcslashes($word, '%_\\').'%';
            $state->getQuery()->where(function (Builder $q) use ($like) {
                // whereLike, not 'like': case-insensitive on every database (PostgreSQL's LIKE is not).
                $q->whereLike('garage_builds.title', $like)->orWhereLike('garage_builds.tagline', $like);
            }, null, null, $negate ? 'and not' : 'and');
        }
    }
}
