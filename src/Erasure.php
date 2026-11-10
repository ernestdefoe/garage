<?php

namespace ErnestDefoe\Garage;

use Flarum\User\User;
use Illuminate\Database\ConnectionInterface;

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
    public function __construct(
        private readonly Counts $counts,
        private readonly ConnectionInterface $db,
    ) {
    }

    public function forget(User $user): void
    {
        $id = (int) $user->id;

        // Other members' builds and entries whose counts this member is part of.
        $builds = array_unique([
            ...Comment::query()->where('user_id', $id)->pluck('build_id')->all(),
            ...$this->db->table('garage_likes')->where('user_id', $id)->pluck('build_id')->all(),
            ...$this->db->table('garage_follows')->where('user_id', $id)->pluck('build_id')->all(),
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
        $this->db->table('garage_likes')->where('user_id', $id)->delete();
        $this->db->table('garage_follows')->where('user_id', $id)->delete();

        foreach (array_diff($builds, $gone) as $buildId) {
            Build::query()->whereKey($buildId)->update([
                'comment_count' => Comment::query()->where('build_id', $buildId)->count(),
                'like_count' => $this->db->table('garage_likes')->where('build_id', $buildId)->count(),
                'follower_count' => $this->db->table('garage_follows')->where('build_id', $buildId)->count(),
            ]);
        }
        foreach ($entries as $entryId) {
            Entry::query()->whereKey($entryId)->update(['comment_count' => Comment::query()->where('entry_id', $entryId)->count()]);
        }

        $this->counts->categories($categories);
    }
}
