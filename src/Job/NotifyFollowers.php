<?php

namespace ErnestDefoe\Garage\Job;

use ErnestDefoe\Garage\Build;
use ErnestDefoe\Garage\Entry;
use ErnestDefoe\Garage\Follow;
use ErnestDefoe\Garage\Notification\NewEntryBlueprint;
use Flarum\Notification\NotificationSyncer;
use Flarum\Queue\AbstractJob;
use Flarum\User\Guest;
use Flarum\User\User;
use Illuminate\Contracts\Queue\Queue;

/**
 * A new log entry: tell the build's followers who can still see it. One
 * notification per entry; the owner is never told about their own.
 *
 * Visibility is asked ONCE, as a guest: if guests can see the build, every
 * follower can (members are granted at least what guests are), so a page of
 * followers costs one query and no per-user check. A member-only narrowing
 * scope from another extension is not asked on that path, which is accepted:
 * a follower chose to follow the build, so they have already seen it. Only when guests can't see
 * it is each follower asked, as core's flarum/subscriptions does for a
 * discussion's followers: whereVisibleTo then runs every extension's user
 * scopes (groups, blocks, hidden profiles), which no single query answers for
 * many people at once.
 *
 * Either way the work is split: each job takes one page of followers and hands
 * the next page, with the ids that passed, to a job of its own. However many
 * followers a build has, no job runs long, and the last one sends the
 * notification once, to everyone who passed (NotificationSyncer::sync()
 * replaces the recipients, so it is called once).
 */
class NotifyFollowers extends AbstractJob
{
    /** Followers checked per job. */
    public static int $page = 200;

    /** @param list<int> $recipients */
    public function __construct(public int $entryId, public int $after = 0, public array $recipients = [])
    {
        parent::__construct();
    }

    public function handle(NotificationSyncer $notifications, Queue $queue): void
    {
        $entry = Entry::query()->find($this->entryId);
        $build = $entry ? Build::query()->find($entry->build_id) : null;

        if (! $entry || ! $build || ! $build->published_at) {
            return;
        }

        $ids = Follow::query()
            ->where('build_id', $build->id)
            ->where('user_id', '>', $this->after)
            ->where('user_id', '!=', $build->user_id)
            ->orderBy('user_id')
            ->limit(self::$page)
            ->pluck('user_id')
            ->all();

        $recipients = [...$this->recipients, ...$this->visibleTo($build, $ids)];

        if (count($ids) === self::$page) {
            $queue->push(new self($this->entryId, (int) end($ids), $recipients));

            return;
        }

        if ($recipients) {
            $notifications->sync(
                new NewEntryBlueprint($entry, $build, User::query()->find($build->user_id)),
                User::query()->whereIn('id', $recipients)->get()->all(),
            );
        }
    }

    /**
     * The followers among $ids who can see the build.
     *
     * @param list<int> $ids
     * @return list<int>
     */
    private function visibleTo(Build $build, array $ids): array
    {
        if (! $ids || Build::query()->whereVisibleTo(new Guest())->whereKey($build->id)->exists()) {
            return array_map('intval', $ids);
        }

        $visible = [];
        foreach (User::query()->with('groups')->whereIn('id', $ids)->get() as $user) {
            if (Build::query()->whereVisibleTo($user)->whereKey($build->id)->exists()) {
                $visible[] = (int) $user->id;
            }
        }

        return $visible;
    }
}
