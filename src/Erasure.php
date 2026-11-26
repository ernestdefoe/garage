<?php

namespace ErnestDefoe\Garage;

use Flarum\User\User;

/**
 * Everything Garage keeps for a member, removed: their builds (photos, log
 * and the log's photos included), their comments, likes and follows. The
 * counts on what is left (other members' builds, their log entries, the
 * categories) are worked out again.
 *
 * 🚨 Runs on `Deleting`, before the user row goes: by `Deleted` the foreign
 * keys have already removed the rows, and with them the photo paths and the
 * ids whose counts need fixing.
 */
class Erasure
{
    public function __construct(private readonly Counts $counts)
    {
    }

    public function forget(User $user): void
    {
        $id = (int) $user->id;

        // Other members' builds and entries whose counts this member is part of.
        $builds = array_unique([
            ...Comment::query()->where('user_id', $id)->pluck('build_id')->all(),
            ...Like::query()->where('user_id', $id)->pluck('build_id')->all(),
            ...Follow::query()->where('user_id', $id)->pluck('build_id')->all(),
        ]);
        $entries = Comment::query()->where('user_id', $id)->whereNotNull('entry_id')->distinct()->pluck('entry_id')->all();

        $categories = [];
        $gone = [];
        Build::query()->where('user_id', $id)->each(function (Build $build) use (&$categories, &$gone) {
            $categories[] = $build->category_id;
            $gone[] = $build->id;
            $this->counts->forget($build);
            $build->delete();
        });

        Comment::query()->where('user_id', $id)->delete();
        Like::query()->where('user_id', $id)->delete();
        Follow::query()->where('user_id', $id)->delete();
        Member::query()->where('user_id', $id)->delete();

        // What is left, worked out again in one statement per table: each count is a correlated
        // subquery (selectRaw('count(*)'): the query builder has no expression for one).
        if ($builds = array_values(array_diff($builds, $gone))) {
            Build::query()->whereIn('id', $builds)->update([
                'comment_count' => Comment::query()->selectRaw('count(*)')->whereColumn('garage_comments.build_id', 'garage_builds.id'),
                'like_count' => Like::query()->selectRaw('count(*)')->whereColumn('garage_likes.build_id', 'garage_builds.id'),
                'follower_count' => Follow::query()->selectRaw('count(*)')->whereColumn('garage_follows.build_id', 'garage_builds.id'),
            ]);
        }
        if ($entries) {
            Entry::query()->whereIn('id', $entries)->update([
                'comment_count' => Comment::query()->selectRaw('count(*)')->whereColumn('garage_comments.entry_id', 'garage_entries.id'),
            ]);
        }

        $this->counts->categories($categories);
    }
}
