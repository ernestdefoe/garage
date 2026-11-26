<?php

namespace ErnestDefoe\Garage;

use Carbon\Carbon;
use Flarum\Database\AbstractModel;
use Flarum\Database\ScopeVisibilityTrait;
use Flarum\User\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $user_id
 * @property int $category_id
 * @property string $title
 * @property string $slug
 * @property string|null $tagline
 * @property string|null $story
 * @property bool $story_formatted
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
 * @property \Illuminate\Database\Eloquent\Collection<int, Member> $team
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
        'story_formatted' => 'boolean',
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

    /**
     * The build's team: its accepted members, in the order they joined (the
     * owner is not a row). Pending invites are Member rows too, never here.
     *
     * @return HasMany<Member, $this>
     */
    public function team(): HasMany
    {
        return $this->hasMany(Member::class)->chaperone('build')->where('garage_members.status', Member::ACCEPTED)->orderBy('garage_members.accepted_at')->orderBy('garage_members.id');
    }

    /**
     * Builds a member owns or is on the team of (accepted). Other extensions
     * (Dossier) list a member's builds with this; pair it with whereVisibleTo.
     *
     * @param Builder<Build> $query
     */
    public function scopeFeaturing(Builder $query, User|int $user): void
    {
        $id = $user instanceof User ? (int) $user->id : $user;

        $query->where(function (Builder $q) use ($id) {
            $q->where('garage_builds.user_id', $id)
                ->orWhereIn('garage_builds.id', Member::query()->select('build_id')->where('user_id', $id)->where('status', Member::ACCEPTED));
        });
    }

    /**
     * The member's role on this build: null when they own it or have no role.
     * Reads the loaded `team` (eager-load it for lists: no query per build).
     */
    public function roleOf(User|int $user): ?string
    {
        $id = $user instanceof User ? (int) $user->id : $user;

        return $this->team->firstWhere('user_id', $id)?->role;
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
