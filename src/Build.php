<?php

namespace ErnestDefoe\Garage;

use Carbon\Carbon;
use Flarum\Database\AbstractModel;
use Flarum\Database\ScopeVisibilityTrait;
use Flarum\User\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property int $category_id
 * @property string $title
 * @property string $slug
 * @property string|null $tagline
 * @property string|null $story
 * @property string $stage
 * @property array<string, string|float|int>|null $specs
 * @property list<array<string, mixed>>|null $photos
 * @property int|null $last_entry_id
 * @property int $entry_count
 * @property int $like_count
 * @property int $comment_count
 * @property int $follower_count
 * @property string|null $spent
 * @property Carbon|null $published_at
 * @property Carbon|null $featured_at
 * @property Carbon|null $finished_at
 * @property Carbon|null $last_activity_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property User|null $user
 * @property Category|null $category
 * @property Entry|null $lastEntry
 */
class Build extends AbstractModel
{
    use ScopeVisibilityTrait;

    public const STAGES = ['planning', 'in_progress', 'complete', 'for_sale', 'retired'];

    public const MAX_PHOTOS = 24;

    public const MAX_PER_USER = 50;

    protected $table = 'garage_builds';

    public $timestamps = true;

    protected $casts = [
        'user_id' => 'integer',
        'category_id' => 'integer',
        'last_entry_id' => 'integer',
        'specs' => 'array',
        'photos' => 'array',
        'entry_count' => 'integer',
        'like_count' => 'integer',
        'comment_count' => 'integer',
        'follower_count' => 'integer',
        'published_at' => 'datetime',
        'featured_at' => 'datetime',
        'finished_at' => 'datetime',
        'last_activity_at' => 'datetime',
    ];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Category, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /** @return BelongsTo<Entry, $this> */
    public function lastEntry(): BelongsTo
    {
        return $this->belongsTo(Entry::class, 'last_entry_id');
    }

    public function isOwnedBy(User $user): bool
    {
        return ! $user->isGuest() && $this->user_id === (int) $user->id;
    }

    public function touchActivity(): void
    {
        $this->last_activity_at = Carbon::now();
    }

    /** Counts and the latest entry, worked out again from the rows. */
    public function refreshLog(): void
    {
        $this->entry_count = Entry::query()->where('build_id', $this->id)->count();
        $this->last_entry_id = Entry::query()->where('build_id', $this->id)->orderByDesc('happened_on')->orderByDesc('id')->value('id');
        $spent = Entry::query()->where('build_id', $this->id)->sum('cost');
        $this->spent = (float) $spent > 0 ? (string) $spent : null;
    }
}
