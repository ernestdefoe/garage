<?php

namespace ErnestDefoe\Garage\Listener;

use ErnestDefoe\Garage\Build;
use ErnestDefoe\Garage\Comment;
use ErnestDefoe\Garage\Counts;
use Flarum\User\Event\Deleted;

/**
 * A deleted member's builds go with them, photos included. The foreign keys
 * do the rows where the database enforces them (SQLite may not).
 */
class ForgetDeletedUser
{
    public function __construct(private readonly Counts $counts)
    {
    }

    public function handle(Deleted $event): void
    {
        $categories = [];

        Build::query()->where('user_id', $event->user->id)->each(function (Build $build) use (&$categories) {
            $categories[] = $build->category_id;
            $this->counts->forget($build);
            $build->delete();
        });

        // Their comments on other members' builds, and those builds' counts.
        $commented = Comment::query()->where('user_id', $event->user->id)->distinct()->pluck('build_id');
        Comment::query()->where('user_id', $event->user->id)->delete();
        foreach ($commented as $buildId) {
            Build::query()->whereKey($buildId)->update(['comment_count' => Comment::query()->where('build_id', $buildId)->count()]);
        }

        $this->counts->categories($categories);
    }
}
