<?php

namespace ErnestDefoe\Garage\Search\Filter;

class FeaturedFilter extends FlagFilter
{
    public function getFilterKey(): string
    {
        return 'featured';
    }

    protected function column(): string
    {
        return 'garage_builds.featured_at';
    }
}
