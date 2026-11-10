<?php

namespace ErnestDefoe\Garage;

use Carbon\Carbon;
use Flarum\Database\AbstractModel;
use Flarum\Database\ScopeVisibilityTrait;
use Flarum\User\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $build_id
 * @property int|null $entry_id
 * @property int $user_id
 * @property string $body
 * @property Carbon $created_at
 * @property Carbon|null $edited_at
 * @property Build|null $build
 * @property User|null $user
 */
class Comment extends AbstractModel
{
    use ScopeVisibilityTrait;

    public const MAX_LENGTH = 2000;

    protected $table = 'garage_comments';

    protected $casts = [
        'build_id' => 'integer',
        'entry_id' => 'integer',
        'user_id' => 'integer',
        'created_at' => 'datetime',
        'edited_at' => 'datetime',
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
}
