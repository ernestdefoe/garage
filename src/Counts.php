<?php

namespace ErnestDefoe\Garage;

use ErnestDefoe\Garage\Storage\Photos;
use Flarum\Notification\Notification;
use Flarum\User\User;

/**
 * The counts kept on other rows: a member's published builds (their profile
 * tab) and each category's. Worked out again from the rows, never nudged by
 * one, so a missed update cannot leave them wrong for good.
 */
class Counts
{
    public function __construct(private readonly Photos $photos)
    {
    }

    /** @param array<int|null> $categoryIds */
    public function refresh(int $userId, array $categoryIds = []): void
    {
        User::query()->whereKey($userId)->update([
            'garage_build_count' => Build::query()->where('user_id', $userId)->whereNotNull('published_at')->count(),
        ]);

        $this->categories($categoryIds);
    }

    /** @param array<int|null> $categoryIds */
    public function categories(array $categoryIds): void
    {
        foreach (array_unique(array_filter($categoryIds)) as $categoryId) {
            Category::query()->whereKey($categoryId)->update([
                'build_count' => Build::query()->where('category_id', $categoryId)->whereNotNull('published_at')->count(),
            ]);
        }
    }

    /**
     * Before a build is deleted: its photos and its log's photos, and any
     * signature pointing at it. Its rows go by cascade; where the database
     * doesn't cascade (SQLite without foreign keys) they are removed here.
     */
    public function forget(Build $build): void
    {
        foreach ($build->photos ?? [] as $photo) {
            $this->photos->delete($photo);
        }

        Entry::query()->where('build_id', $build->id)->whereNotNull('photos')->select(['id', 'photos'])
            ->each(fn (Entry $entry) => array_map($this->photos->delete(...), $entry->photos ?? []));

        User::query()->where('garage_signature_build_id', $build->id)->update(['garage_signature_build_id' => null]);

        $build->afterDelete(function () use ($build) {
            Comment::query()->where('build_id', $build->id)->delete();
            Entry::query()->where('build_id', $build->id)->delete();
            $build->getConnection()->table('garage_likes')->where('build_id', $build->id)->delete();
            $build->getConnection()->table('garage_follows')->where('build_id', $build->id)->delete();
            Notification::query()->whereIn('type', ['garageNewEntry', 'garageComment'])->where('subject_id', $build->id)->delete();
        });
    }
}
