<?php

namespace ErnestDefoe\Garage\Access;

use ErnestDefoe\Garage\Build;
use ErnestDefoe\Garage\Settings;
use Flarum\User\Access\AbstractPolicy;
use Flarum\User\User;

/**
 * Members change only their own builds (while they may still add builds);
 * moderators change, remove and feature anyone's. Liking and commenting need
 * their own permissions and a published build.
 */
class BuildPolicy extends AbstractPolicy
{
    public function update(User $actor, Build $build): ?string
    {
        return ($build->isOwnedBy($actor) && $actor->hasPermission(Settings::CREATE)) || $actor->hasPermission(Settings::MODERATE)
            ? $this->allow() : $this->deny();
    }

    public function delete(User $actor, Build $build): ?string
    {
        return $build->isOwnedBy($actor) || $actor->hasPermission(Settings::MODERATE) ? $this->allow() : $this->deny();
    }

    public function feature(User $actor, Build $build): ?string
    {
        return $actor->hasPermission(Settings::MODERATE) ? $this->allow() : $this->deny();
    }

    public function like(User $actor, Build $build): ?string
    {
        return $build->published_at && ! $build->isOwnedBy($actor) && $actor->hasPermission(Settings::LIKE) ? $this->allow() : $this->deny();
    }

    public function follow(User $actor, Build $build): ?string
    {
        return $build->published_at && ! $actor->isGuest() && ! $build->isOwnedBy($actor) ? $this->allow() : $this->deny();
    }

    public function comment(User $actor, Build $build): ?string
    {
        return $build->published_at && $actor->hasPermission(Settings::COMMENT) ? $this->allow() : $this->deny();
    }
}
