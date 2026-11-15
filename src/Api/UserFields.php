<?php

namespace ErnestDefoe\Garage\Api;

use Flarum\Api\Schema\Attribute;
use Flarum\Api\Schema\Integer;

/** `garageBuildCount` for the profile tab: a column, no query. */
class UserFields
{
    /** @return list<Attribute> */
    public function __invoke(): array
    {
        return [
            Integer::make('garageBuildCount')
                ->property('garage_build_count'),
        ];
    }
}
