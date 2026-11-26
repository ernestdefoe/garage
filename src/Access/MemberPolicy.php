<?php

namespace ErnestDefoe\Garage\Access;

use ErnestDefoe\Garage\Member;
use Flarum\User\Access\AbstractPolicy;
use Flarum\User\User;

/**
 * The build's owner and moderators manage its team (invite, set roles,
 * remove: the build's `update` ability). The invitee accepts; anyone on the
 * team, or invited to it, may leave or decline.
 */
class MemberPolicy extends AbstractPolicy
{
    public function update(User $actor, Member $member): ?string
    {
        return $member->build && $actor->can('update', $member->build) ? $this->allow() : $this->deny();
    }

    public function accept(User $actor, Member $member): ?string
    {
        return ! $member->isAccepted() && $member->user_id === (int) $actor->id ? $this->allow() : $this->deny();
    }

    /** Any change through the API: a role (the owner) or accepting (the invitee). */
    public function change(User $actor, Member $member): ?string
    {
        return $actor->can('update', $member) || $actor->can('accept', $member) ? $this->allow() : $this->deny();
    }

    public function delete(User $actor, Member $member): ?string
    {
        return $member->user_id === (int) $actor->id || ($member->build && $actor->can('update', $member->build)) ? $this->allow() : $this->deny();
    }
}
