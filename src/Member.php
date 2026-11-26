<?php

namespace ErnestDefoe\Garage;

use Carbon\Carbon;
use Flarum\Database\AbstractModel;
use Flarum\Database\ScopeVisibilityTrait;
use Flarum\User\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A member of a build's team. The owner invites (pending); the invitee
 * accepts (accepted) or declines (the row goes). Only accepted members are
 * the team; the role is free text the owner gives them.
 *
 * @property int $id
 * @property int $build_id
 * @property int $user_id
 * @property string|null $role
 * @property string $status
 * @property int|null $invited_by
 * @property Carbon $created_at
 * @property Carbon|null $accepted_at
 * @property Build|null $build
 * @property User|null $user
 */
class Member extends AbstractModel
{
    use ScopeVisibilityTrait;

    public const PENDING = 'pending';

    public const ACCEPTED = 'accepted';

    /** Members a team may have, pending invites included. */
    public const MAX_TEAM = 20;

    public const MAX_ROLE = 60;

    protected $table = 'garage_members';

    public $timestamps = false;

    protected $casts = [
        'build_id' => 'integer',
        'user_id' => 'integer',
        'invited_by' => 'integer',
        'created_at' => 'datetime',
        'accepted_at' => 'datetime',
    ];

    /** @return BelongsTo<Build, $this> */
    public function build(): BelongsTo
    {
        return $this->belongsTo(Build::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isAccepted(): bool
    {
        return $this->status === self::ACCEPTED;
    }
}
