<?php

namespace ErnestDefoe\Garage\Api;

use Carbon\Carbon;
use ErnestDefoe\Garage\Build;
use ErnestDefoe\Garage\Category;
use ErnestDefoe\Garage\Counts;
use ErnestDefoe\Garage\Entry;
use ErnestDefoe\Garage\Settings;
use ErnestDefoe\Garage\Storage\Photos;
use Flarum\Api\Context;
use Flarum\Api\Endpoint;
use Flarum\Api\Resource\AbstractDatabaseResource;
use Flarum\Api\Schema;
use Flarum\Api\Sort\SortColumn;
use Flarum\Foundation\ValidationException;
use Flarum\Locale\TranslatorInterface;
use Flarum\User\User;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Tobyz\JsonApiServer\Context as OriginalContext;

/**
 * A member's build. Likes, follows and "show in my signature" are writable
 * attributes, so the page toggles them with the same PATCH it saves with;
 * photos have their own endpoints (ManagesPhotos).
 *
 * @extends AbstractDatabaseResource<Build>
 */
class BuildResource extends AbstractDatabaseResource
{
    use ManagesPhotos;

    /** Build ids whose owner and category counts need working out again after the save. */
    private array $recount = [];

    public function __construct(
        private readonly Photos $photos,
        private readonly ViewerState $viewer,
        private readonly TranslatorInterface $translator,
        private readonly ConnectionInterface $db,
    ) {
    }

    public function type(): string
    {
        return 'garage-builds';
    }

    public function model(): string
    {
        return Build::class;
    }

    public function scope(Builder $query, OriginalContext $context): void
    {
        $query->whereVisibleTo($context->getActor());
    }

    protected function maxPhotos(): int
    {
        return Build::MAX_PHOTOS;
    }

    protected function photoFolder(): string
    {
        return 'builds';
    }

    protected function photosChanged(Build|Entry $model): void
    {
        /** @var Build $model */
        if ($model->published_at) {
            $model->touchActivity();
        }
    }

    public function endpoints(): array
    {
        return [
            Endpoint\Index::make()
                ->paginate(24, 48)
                ->defaultInclude(['user', 'category', 'lastEntry'])
                // The category is always loaded with the list: specs are cleaned against it as they are read.
                ->eagerLoad(['category'])
                // The owners' groups (staff see them) in one query, not one per owner.
                ->eagerLoadWhenIncluded(['user' => ['user.groups']])
                ->defaultSort('-lastActivityAt')
                // The latest entry's canEdit asks its build's policy: hand it the build it belongs to
                // instead of letting each entry load it again.
                ->beforeSerialization(function (Context $context, array $results) {
                    foreach ($results['models'] as $build) {
                        if ($build->relationLoaded('lastEntry') && $build->lastEntry) {
                            $build->lastEntry->setRelation('build', $build);
                        }
                    }
                }),
            Endpoint\Show::make()
                ->defaultInclude(['user', 'category'])
                ->eagerLoad(['category'])
                ->eagerLoadWhenIncluded(['user' => ['user.groups']]),
            Endpoint\Create::make()
                ->authenticated()
                ->can(Settings::CREATE)
                ->defaultInclude(['user', 'category']),
            Endpoint\Update::make()
                ->authenticated()
                ->defaultInclude(['user', 'category']),
            Endpoint\Delete::make()
                ->authenticated()
                ->can('delete'),
            ...$this->photoEndpoints('garage.builds'),
        ];
    }

    public function creating(object $model, OriginalContext $context): ?object
    {
        /** @var Build $model */
        $actor = $context->getActor();

        if (! $actor->isAdmin() && Build::query()->where('user_id', $actor->id)->count() >= Build::MAX_PER_USER) {
            throw new ValidationException(['title' => $this->translator->trans('ernestdefoe-garage.api.too_many_builds', ['count' => Build::MAX_PER_USER])]);
        }

        $model->user_id = (int) $actor->id;
        // Flarum's models do not take Eloquent's $attributes defaults, so a new row states its own.
        $model->stage ??= 'in_progress';
        $model->entry_count = $model->like_count = $model->comment_count = $model->follower_count = 0;
        $model->last_activity_at = Carbon::now();

        return $model;
    }

    public function saving(object $model, OriginalContext $context): ?object
    {
        /** @var Build $model */
        if ($model->isDirty('title')) {
            $model->slug = Str::slug($model->title) ?: 'build';
        }

        // Only the fields the build's category has, and values that fit them.
        if ($model->isDirty('specs') || $model->isDirty('category_id')) {
            $category = Category::query()->find($model->category_id);
            $model->specs = $category?->cleanSpecs($model->specs) ?: null;
        }

        // When it reached Finished: kept while it is for sale or retired, cleared if it goes back to work.
        if ($model->isDirty('stage')) {
            if ($model->stage === 'complete') {
                $model->finished_at ??= Carbon::now();
            } elseif (in_array($model->stage, ['planning', 'in_progress'], true)) {
                $model->finished_at = null;
            }
        }

        if (! $model->exists || $model->isDirty('published_at') || $model->isDirty('category_id')) {
            $this->recount[spl_object_id($model)] = $model->getOriginal('category_id');
        }

        return $model;
    }

    public function saved(object $model, OriginalContext $context): ?object
    {
        /** @var Build $model */
        if (array_key_exists($key = spl_object_id($model), $this->recount)) {
            $previous = $this->recount[$key];
            unset($this->recount[$key]);
            // Resolved here, not injected: the constructor runs on every API request, this only on a save.
            resolve(Counts::class)->refresh($model->user_id, array_filter([$model->category_id, $previous]));
        }

        return $model;
    }

    public function deleting(object $model, OriginalContext $context): void
    {
        /** @var Build $model */
        resolve(Counts::class)->forget($model);
    }

    public function deleted(object $model, OriginalContext $context): void
    {
        /** @var Build $model */
        resolve(Counts::class)->refresh($model->user_id, [$model->category_id]);
    }

    public function fields(): array
    {
        // Writable while creating, and afterwards by the owner or a moderator.
        $owner = fn (Build $b, Context $context) => ! $b->exists || $context->getActor()->can('update', $b);

        return [
            Schema\Str::make('title')
                ->writable($owner)
                ->requiredOnCreate()
                ->rule('string')
                ->rule('max:120')
                ->rule('regex:/\S/'),
            Schema\Str::make('slug'),
            Schema\Str::make('tagline')
                ->nullable()
                ->writable($owner)
                ->rule('max:160'),
            Schema\Str::make('story')
                ->nullable()
                ->writable($owner)
                ->rule('max:20000')
                ->visible(fn (Build $b, Context $context) => ! $context->listing(self::class)),
            Schema\Str::make('stage')
                ->writable($owner)
                ->in(Build::STAGES),
            // {fieldKey: value}; kept to the category's fields in saving().
            Schema\Arr::make('specs')
                // Cleaned again as it is read: a field's type may have changed, or the build moved
                // to another category, since its values were saved (a text value is no link).
                ->get(fn (Build $b) => (object) ($b->category ? $b->category->cleanSpecs($b->specs) : ($b->specs ?? [])))
                ->writable($owner)
                ->set(fn (Build $b, $value) => $b->specs = is_array($value) ? $value : []),
            Schema\Arr::make('photos')
                ->get(fn (Build $b) => $this->photos->presentAll($b->photos)),
            Schema\Integer::make('entryCount')->property('entry_count'),
            Schema\Integer::make('likeCount')->property('like_count'),
            Schema\Integer::make('commentCount')->property('comment_count'),
            Schema\Integer::make('followerCount')->property('follower_count'),
            Schema\Number::make('spent')
                ->nullable()
                ->get(fn (Build $b) => $b->spent !== null ? (float) $b->spent : null),
            // A draft shows only to its owner (and moderators) until it is published.
            Schema\Boolean::make('published')
                ->get(fn (Build $b) => $b->published_at !== null)
                ->writable($owner)
                ->set(function (Build $b, $value) {
                    if ($value && ! $b->published_at) {
                        $b->published_at = Carbon::now();
                        $b->touchActivity();
                    } elseif (! $value) {
                        $b->published_at = null;
                    }
                }),
            Schema\Boolean::make('featured')
                ->get(fn (Build $b) => $b->featured_at !== null)
                ->writable(fn (Build $b, Context $context) => $b->exists && $context->getActor()->can('feature', $b))
                ->set(fn (Build $b, $value) => $b->featured_at = $value ? ($b->featured_at ?? Carbon::now()) : null),
            Schema\DateTime::make('publishedAt')->property('published_at'),
            Schema\DateTime::make('finishedAt')->property('finished_at')->nullable(),
            Schema\DateTime::make('lastActivityAt')->property('last_activity_at'),
            Schema\DateTime::make('createdAt')->property('created_at'),

            Schema\Boolean::make('liked')
                ->get(fn (Build $b, Context $context) => $this->viewer->deferred($context, 'garage_likes', $b->id))
                ->writable(fn (Build $b, Context $context) => $b->exists && $context->getActor()->can('like', $b))
                ->set(fn (Build $b, $value, Context $context) => $this->toggle($b, $context, 'garage_likes', 'like_count', (bool) $value)),
            Schema\Boolean::make('followed')
                ->get(fn (Build $b, Context $context) => $this->viewer->deferred($context, 'garage_follows', $b->id))
                ->writable(fn (Build $b, Context $context) => $b->exists && $context->getActor()->can('follow', $b))
                ->set(fn (Build $b, $value, Context $context) => $this->toggle($b, $context, 'garage_follows', 'follower_count', (bool) $value)),
            // The owner's choice of the build shown under their posts (FoF Signature). Only its owner sees or sets it.
            Schema\Boolean::make('inSignature')
                ->visible(fn (Build $b, Context $context) => $b->isOwnedBy($context->getActor()))
                ->get(fn (Build $b, Context $context) => (int) $context->getActor()->garage_signature_build_id === $b->id)
                ->writable(fn (Build $b, Context $context) => $b->exists && $b->isOwnedBy($context->getActor()))
                ->set(fn (Build $b, $value, Context $context) => $this->signature($b, $context->getActor(), (bool) $value)),

            Schema\Boolean::make('canEdit')
                ->get(fn (Build $b, Context $context) => $context->getActor()->can('update', $b)),
            Schema\Boolean::make('canDelete')
                ->get(fn (Build $b, Context $context) => $context->getActor()->can('delete', $b)),
            Schema\Boolean::make('canFeature')
                ->get(fn (Build $b, Context $context) => $context->getActor()->can('feature', $b)),
            Schema\Boolean::make('canLike')
                ->get(fn (Build $b, Context $context) => $context->getActor()->can('like', $b)),
            Schema\Boolean::make('canFollow')
                ->get(fn (Build $b, Context $context) => $context->getActor()->can('follow', $b)),
            Schema\Boolean::make('canComment')
                ->get(fn (Build $b, Context $context) => $context->getActor()->can('comment', $b)),

            Schema\Relationship\ToOne::make('user')
                ->type('users')
                ->includable(),
            Schema\Relationship\ToOne::make('category')
                ->type('garage-categories')
                ->includable()
                ->writable($owner)
                ->requiredOnCreate(),
            Schema\Relationship\ToOne::make('lastEntry')
                ->type('garage-entries')
                ->includable(),
        ];
    }

    public function sorts(): array
    {
        return [
            SortColumn::make('lastActivityAt'),
            SortColumn::make('publishedAt'),
            SortColumn::make('createdAt'),
            SortColumn::make('likeCount'),
            SortColumn::make('followerCount'),
            SortColumn::make('entryCount'),
            SortColumn::make('featuredAt'),
        ];
    }

    /** A like or a follow: the row, and the build's count worked out again (without touching updated_at). */
    private function toggle(Build $build, Context $context, string $table, string $column, bool $on): void
    {
        $actor = $context->getActor();
        $rows = $this->db->table($table)->where('build_id', $build->id)->where('user_id', $actor->id);

        if ($on) {
            $this->db->table($table)->insertOrIgnore(['build_id' => $build->id, 'user_id' => $actor->id, 'created_at' => Carbon::now()]);
        } else {
            $rows->delete();
        }

        $count = $this->db->table($table)->where('build_id', $build->id)->count();
        Build::query()->whereKey($build->id)->update([$column => $count]);
        $build->setAttribute($column, $count)->syncOriginalAttribute($column);
        $this->viewer->set($context, $table, $build->id, $on);
    }

    private function signature(Build $build, User $owner, bool $on): void
    {
        $current = (int) $owner->garage_signature_build_id;
        $next = $on ? $build->id : ($current === $build->id ? null : ($current ?: null));

        User::query()->whereKey($owner->id)->update(['garage_signature_build_id' => $next]);
        $owner->setAttribute('garage_signature_build_id', $next)->syncOriginalAttribute('garage_signature_build_id');
    }
}
