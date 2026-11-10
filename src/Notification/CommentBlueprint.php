<?php

namespace ErnestDefoe\Garage\Notification;

use ErnestDefoe\Garage\Build;
use ErnestDefoe\Garage\Comment;
use Flarum\Database\AbstractModel;
use Flarum\Notification\AlertableInterface;
use Flarum\Notification\Blueprint\BlueprintInterface;
use Flarum\User\User;
use Illuminate\Support\Str;

/** Someone commented on your build (or on one of its log entries). */
class CommentBlueprint implements BlueprintInterface, AlertableInterface
{
    public function __construct(public Comment $comment, public User $commenter)
    {
    }

    public function getFromUser(): ?User
    {
        return $this->commenter;
    }

    public function getSubject(): ?AbstractModel
    {
        return $this->comment->build;
    }

    public function getData(): mixed
    {
        return ['comment' => $this->comment->id, 'entry' => $this->comment->entry_id, 'excerpt' => Str::limit($this->comment->body, 120)];
    }

    public static function getType(): string
    {
        return 'garageComment';
    }

    public static function getSubjectModel(): string
    {
        return Build::class;
    }
}
