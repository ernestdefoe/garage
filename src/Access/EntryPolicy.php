<?php

namespace ErnestDefoe\Garage\Access;

use ErnestDefoe\Garage\Entry;
use Flarum\User\Access\AbstractPolicy;
use Flarum\User\User;

/** A log entry is changed or removed by whoever may edit its build. */
class EntryPolicy extends AbstractPolicy
{
    public function update(User $actor, Entry $entry): ?string
    {
        return $entry->build && $actor->can('update', $entry->build) ? $this->allow() : $this->deny();
    }

    public function delete(User $actor, Entry $entry): ?string
    {
        return $this->update($actor, $entry);
    }
}
