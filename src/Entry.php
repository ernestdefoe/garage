<?php

namespace ErnestDefoe\Garage;

use Carbon\Carbon;
use Flarum\Database\AbstractModel;
use Flarum\Database\ScopeVisibilityTrait;
use Flarum\User\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One dated update in a build's log.
 *
 * @property int $id
 * @property int $build_id
 * @property int $user_id
 * @property string $title
 * @property string|null $body
 * @property bool $body_formatted
 * @property list<array<string, mixed>>|null $photos
 * @property string|null $cost
 * @property Carbon $happened_on
 * @property int $comment_count
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Build|null $build
 * @property User|null $user
 */
class Entry extends AbstractModel
{
    use ScopeVisibilityTrait;

    public const MAX_PHOTOS = 12;

    public const MAX_PER_BUILD = 500;

    protected $table = 'garage_entries';

    public $timestamps = true;

    protected $casts = [
        'build_id' => 'integer',
        'user_id' => 'integer',
        'photos' => 'array',
        'happened_on' => 'date',
        'comment_count' => 'integer',
        'body_formatted' => 'boolean',
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
