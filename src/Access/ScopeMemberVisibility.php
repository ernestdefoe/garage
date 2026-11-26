<?php

namespace ErnestDefoe\Garage\Access;

use ErnestDefoe\Garage\Build;
use ErnestDefoe\Garage\Member;
use ErnestDefoe\Garage\Settings;
use Flarum\User\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * A build's team is seen by whoever sees the build. A pending invite only by
 * the invitee, the build's owner and moderators. A member always sees their
 * own row, so they can still leave a team whose build was unpublished.
 */
class ScopeMemberVisibility
{
    public function __invoke(User $actor, Builder $query): void
    {
        if ($actor->hasPermission(Settings::MODERATE)) {
            return;
        }

        $query->where(function (Builder $q) use ($actor) {
            $q->whereIn('garage_members.build_id', Build::query()->whereVisibleTo($actor)->select('garage_builds.id'))
                ->where(function (Builder $q) use ($actor) {
                    $q->where('garage_members.status', Member::ACCEPTED);

                    if (! $actor->isGuest()) {
                        $q->orWhereIn('garage_members.build_id', Build::query()->where('user_id', $actor->id)->select('id'));
                    }
                });

            if (! $actor->isGuest()) {
                $q->orWhere('garage_members.user_id', $actor->id);
            }
        });
    }
}
