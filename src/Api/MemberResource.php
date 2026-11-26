<?php

namespace ErnestDefoe\Garage\Api;

use Carbon\Carbon;
use ErnestDefoe\Garage\Build;
use ErnestDefoe\Garage\Member;
use ErnestDefoe\Garage\Notification\TeamInviteBlueprint;
use ErnestDefoe\Garage\Notification\TeamJoinedBlueprint;
use Flarum\Api\Context;
use Flarum\Api\Endpoint;
use Flarum\Api\Resource\AbstractDatabaseResource;
use Flarum\Api\Schema;
use Flarum\Foundation\ValidationException;
use Flarum\Locale\TranslatorInterface;
use Flarum\Notification\Notification;
use Flarum\Notification\NotificationSyncer;
use Flarum\User\Guest;
use Flarum\User\User;
use Illuminate\Database\Eloquent\Builder;
use Tobyz\JsonApiServer\Context as OriginalContext;

/**
 * A build's team (garage-members). The owner or a moderator invites a member
 * (create), sets their role (update) and removes them (delete); the invitee
 * accepts (update status to "accepted") or declines (delete); a member leaves
 * (delete). The invitee is told, and the owner when they accept.
 *
 * @extends AbstractDatabaseResource<Member>
 */
class MemberResource extends AbstractDatabaseResource
{
    public function __construct(
        private readonly TranslatorInterface $translator,
        private readonly RateLimit $rateLimit,
        private readonly NotificationSyncer $notifications,
    ) {
    }

    public function type(): string
    {
        return 'garage-members';
    }

    public function model(): string
    {
        return Member::class;
    }

    public function scope(Builder $query, OriginalContext $context): void
    {
        $query->whereVisibleTo($context->getActor());
    }

    public function endpoints(): array
    {
        return [
            Endpoint\Index::make()
                ->paginate(Member::MAX_TEAM, Member::MAX_TEAM)
                ->defaultInclude(['user'])
                ->eagerLoad(['build'])
                ->eagerLoadWhenIncluded(['user' => ['user.groups']]),
            Endpoint\Create::make()
                ->authenticated()
                ->defaultInclude(['user']),
            Endpoint\Update::make()
                ->authenticated()
                ->can('change')
                ->defaultInclude(['user']),
            Endpoint\Delete::make()
                ->authenticated()
                ->can('delete'),
        ];
    }

    public function creating(object $model, OriginalContext $context): ?object
    {
        /** @var Member $model */
        $actor = $context->getActor();
        $build = $model->build;
        $invitee = $model->user;

        $actor->assertCan('update', $build);

        // 20 a minute, 60 a day: enough to put a team together, not to spam invites.
        if (! $actor->isAdmin() && ! $this->rateLimit->spend("garage.invites.$actor->id", 20, 60)) {
            throw new ValidationException(['user' => $this->translator->trans('ernestdefoe-garage.api.too_many_invites')]);
        }

        $this->assertInvitable($build, $invitee);

        $model->status = Member::PENDING;
        $model->invited_by = (int) $actor->id;
        $model->created_at = Carbon::now();

        return $model;
    }

    /** A published build, a team with room, someone not on it yet who can see the build. */
    private function assertInvitable(?Build $build, ?User $invitee): void
    {
        $error = fn (string $key) => new ValidationException(['user' => $this->translator->trans("ernestdefoe-garage.api.team.$key", ['count' => Member::MAX_TEAM])]);

        if (! $build || ! $invitee) {
            throw $error('not_found');
        }
        if (! $build->published_at) {
            throw $error('draft');
        }
        if ($build->user_id === (int) $invitee->id || Member::query()->where('build_id', $build->id)->where('user_id', $invitee->id)->exists()) {
            throw $error('already');
        }
        if (Member::query()->where('build_id', $build->id)->count() >= Member::MAX_TEAM) {
            throw $error('full');
        }

        // Asked as a guest first: if guests see the build, so does every member.
        $visible = fn (User $user) => Build::query()->whereVisibleTo($user)->whereKey($build->id)->exists();
        if (! $visible(new Guest()) && ! $visible($invitee)) {
            throw $error('cannot_see');
        }
    }

    public function updating(object $model, OriginalContext $context): ?object
    {
        /** @var Member $model */
        if ($model->isDirty('status')) {
            $model->accepted_at = Carbon::now();
        }

        return $model;
    }

    public function created(object $model, OriginalContext $context): ?object
    {
        /** @var Member $model */
        if ($model->user) {
            $this->notifications->sync(new TeamInviteBlueprint($model, $context->getActor()), [$model->user]);
        }

        return $model;
    }

    public function updated(object $model, OriginalContext $context): ?object
    {
        /** @var Member $model */
        if ($model->wasChanged('status') && $model->isAccepted()) {
            $this->forgetNotifications($model, [TeamInviteBlueprint::getType()]);

            $owner = $model->build?->user;
            if ($owner) {
                $this->notifications->sync(new TeamJoinedBlueprint($model, $context->getActor()), [$owner]);
            }
        }

        return $model;
    }

    public function deleted(object $model, OriginalContext $context): void
    {
        /** @var Member $model */
        $this->forgetNotifications($model, [TeamInviteBlueprint::getType(), TeamJoinedBlueprint::getType()]);
    }

    /** @param list<string> $types */
    private function forgetNotifications(Member $member, array $types): void
    {
        Notification::query()->whereIn('type', $types)->where('subject_id', $member->id)->delete();
    }

    public function fields(): array
    {
        $manages = fn (Member $m, Context $context) => ! $m->exists || $context->getActor()->can('update', $m);

        return [
            Schema\Str::make('role')
                ->nullable()
                ->writable($manages)
                ->rule('max:'.Member::MAX_ROLE)
                ->set(function (Member $m, ?string $value) {
                    $m->role = trim((string) $value) === '' ? null : trim((string) $value);
                }),
            Schema\Str::make('status')
                // Only the invitee changes it, and only to accepted: declining deletes the row.
                ->writable(fn (Member $m, Context $context) => $m->exists && $context->getActor()->can('accept', $m))
                ->in([Member::ACCEPTED]),
            Schema\DateTime::make('createdAt')->property('created_at'),
            Schema\DateTime::make('acceptedAt')->property('accepted_at'),
            Schema\Boolean::make('canEdit')
                ->get(fn (Member $m, Context $context) => $context->getActor()->can('update', $m)),
            Schema\Boolean::make('canDelete')
                ->get(fn (Member $m, Context $context) => $context->getActor()->can('delete', $m)),

            Schema\Relationship\ToOne::make('user')
                ->type('users')
                ->includable()
                ->writableOnCreate()
                ->requiredOnCreate(),
            Schema\Relationship\ToOne::make('build')
                ->type('garage-builds')
                ->writableOnCreate()
                ->requiredOnCreate(),
        ];
    }
}
