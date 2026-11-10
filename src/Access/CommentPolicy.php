<?php

namespace ErnestDefoe\Garage\Access;

use ErnestDefoe\Garage\Comment;
use ErnestDefoe\Garage\Settings;
use Flarum\User\Access\AbstractPolicy;
use Flarum\User\User;

/**
 * A comment is edited by its author (while they may still comment), and
 * removed by its author, the build's owner or a moderator.
 */
class CommentPolicy extends AbstractPolicy
{
    public function update(User $actor, Comment $comment): ?string
    {
        return ($comment->user_id === (int) $actor->id && $actor->hasPermission(Settings::COMMENT)) || $actor->hasPermission(Settings::MODERATE)
            ? $this->allow() : $this->deny();
    }

    public function delete(User $actor, Comment $comment): ?string
    {
        return $comment->user_id === (int) $actor->id || $comment->build?->user_id === (int) $actor->id || $actor->hasPermission(Settings::MODERATE)
            ? $this->allow() : $this->deny();
    }
}
