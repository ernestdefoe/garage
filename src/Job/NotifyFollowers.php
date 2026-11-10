<?php

namespace ErnestDefoe\Garage\Job;

use ErnestDefoe\Garage\Build;
use ErnestDefoe\Garage\Entry;
use ErnestDefoe\Garage\Notification\NewEntryBlueprint;
use Flarum\Notification\NotificationSyncer;
use Flarum\Queue\AbstractJob;
use Flarum\User\User;

/**
 * A new log entry: tell the build's followers who can still see it. One
 * notification per entry; the owner is never told about their own.
 */
class NotifyFollowers extends AbstractJob
{
    public function __construct(public int $entryId)
    {
        parent::__construct();
    }

    public function handle(NotificationSyncer $notifications): void
    {
        $entry = Entry::query()->find($this->entryId);
        $build = $entry ? Build::query()->find($entry->build_id) : null;

        if (! $entry || ! $build || ! $build->published_at) {
            return;
        }

        $followers = User::query()
            ->whereIn('id', fn ($q) => $q->select('user_id')->from('garage_follows')->where('build_id', $build->id))
            ->where('id', '!=', $build->user_id)
            ->get()
            // One visibility query per person, on purpose: whereVisibleTo runs every
            // extension's user scopes (blocks, hidden profiles, groups), which PHP
            // cannot repeat. It runs in the queue, never in a request.
            ->filter(fn (User $user) => Build::query()->whereVisibleTo($user)->whereKey($build->id)->exists());

        if ($followers->isNotEmpty()) {
            $notifications->sync(new NewEntryBlueprint($entry, $build, User::query()->find($build->user_id)), $followers->values()->all());
        }
    }
}
