<?php

namespace ErnestDefoe\Garage\Listener;

use ErnestDefoe\Garage\Erasure;
use Flarum\User\Event\Deleting;

/** A member being deleted takes their builds, comments, likes and follows with them (Erasure). */
class ForgetDeletedUser
{
    public function __construct(private readonly Erasure $erasure)
    {
    }

    public function handle(Deleting $event): void
    {
        $this->erasure->forget($event->user);
    }
}
