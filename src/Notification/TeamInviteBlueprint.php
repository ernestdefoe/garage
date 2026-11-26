<?php

namespace ErnestDefoe\Garage\Notification;

use ErnestDefoe\Garage\Member;
use Flarum\Database\AbstractModel;
use Flarum\Notification\AlertableInterface;
use Flarum\Notification\Blueprint\BlueprintInterface;
use Flarum\User\User;

/**
 * You were invited to a build's team: sent to the invitee, from the owner.
 * The subject is the Member row (it has its own API resource); the build's
 * id, slug and title ride in the data, so the alert links without a query.
 */
class TeamInviteBlueprint implements BlueprintInterface, AlertableInterface
{
    public function __construct(public Member $member, public ?User $from)
    {
    }

    public function getFromUser(): ?User
    {
        return $this->from;
    }

    public function getSubject(): ?AbstractModel
    {
        return $this->member;
    }

    public function getData(): mixed
    {
        $build = $this->member->build;

        return ['build' => $build?->id, 'slug' => $build?->slug, 'title' => $build?->title, 'role' => $this->member->role];
    }

    public static function getType(): string
    {
        return 'garageTeamInvite';
    }

    public static function getSubjectModel(): string
    {
        return Member::class;
    }
}
