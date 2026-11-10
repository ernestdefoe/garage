<?php

namespace ErnestDefoe\Garage\Search\Filter;

class PublishedFilter extends FlagFilter
{
    public function getFilterKey(): string
    {
        return 'published';
    }

    protected function column(): string
    {
        return 'garage_builds.published_at';
    }
}
