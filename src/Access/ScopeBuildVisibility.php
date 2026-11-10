<?php

namespace ErnestDefoe\Garage\Access;

use ErnestDefoe\Garage\Settings;
use Flarum\User\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Who sees a build: moderators all of them; everyone else needs the view
 * permission, then sees published builds by members they can see, and always
 * their own (drafts included).
 */
class ScopeBuildVisibility
{
    public function __invoke(User $actor, Builder $query): void
    {
        if ($actor->hasPermission(Settings::MODERATE)) {
            return;
        }

        if (! $actor->hasPermission(Settings::VIEW)) {
            $query->whereRaw('1 = 0');

            return;
        }

        $table = $query->getModel()->getTable();

        $query->where(function (Builder $q) use ($actor, $table) {
            $q->where(function (Builder $q) use ($actor, $table) {
                $q->whereNotNull("$table.published_at")
                    ->whereIn("$table.user_id", User::query()->whereVisibleTo($actor)->select('users.id'));
            });

            if (! $actor->isGuest()) {
                $q->orWhere("$table.user_id", $actor->id);
            }
        });
    }
}
