<?php

namespace ErnestDefoe\Garage\Api;

use Carbon\Carbon;
use ErnestDefoe\Garage\Build;
use ErnestDefoe\Garage\Comment;
use ErnestDefoe\Garage\Entry;
use ErnestDefoe\Garage\Job\NotifyFollowers;
use ErnestDefoe\Garage\Storage\Photos;
use Flarum\Api\Context;
use Flarum\Api\Endpoint;
use Flarum\Api\Resource\AbstractDatabaseResource;
use Flarum\Api\Schema;
use Flarum\Api\Sort\SortColumn;
use Flarum\Foundation\ValidationException;
use Flarum\Locale\TranslatorInterface;
use Illuminate\Contracts\Queue\Queue;
use Illuminate\Database\Eloquent\Builder;
use Tobyz\JsonApiServer\Context as OriginalContext;

/**
 * The build log. Whoever may edit a build writes its log; a new entry on a
 * published build tells the build's followers (queued).
 *
 * @extends AbstractDatabaseResource<Entry>
 */
class EntryResource extends AbstractDatabaseResource
{
    use ManagesPhotos;

    /** @var array<int, bool> Entries created in this request, by object id: their followers are told once saved. */
    private array $fresh = [];

    public function __construct(private readonly Photos $photos, private readonly TranslatorInterface $translator)
    {
    }

    public function type(): string
    {
        return 'garage-entries';
    }

    public function model(): string
    {
        return Entry::class;
    }

    public function scope(Builder $query, OriginalContext $context): void
    {
        $query->whereVisibleTo($context->getActor());
    }

    protected function maxPhotos(): int
    {
        return Entry::MAX_PHOTOS;
    }

    protected function photoFolder(): string
    {
        return 'log';
    }

    protected function photosChanged(Build|Entry $model): void
    {
        /** @var Entry $model */
        $this->bumpBuild($model);
    }

    public function endpoints(): array
    {
        return [
            Endpoint\Index::make()
                ->paginate(20, 100)
                ->defaultSort('-happenedOn')
                // canEdit asks the build's policy: one query for the page, not one per entry.
                ->eagerLoad(['build']),
            Endpoint\Show::make()
                ->eagerLoad(['build']),
            Endpoint\Create::make()
                ->authenticated(),
            Endpoint\Update::make()
                ->authenticated()
                ->can('update'),
            Endpoint\Delete::make()
                ->authenticated()
                ->can('update'),
            ...$this->photoEndpoints('garage.entries'),
        ];
    }

    public function creating(object $model, OriginalContext $context): ?object
    {
        /** @var Entry $model */
        $build = $model->build;
        $actor = $context->getActor();
        $actor->assertCan('update', $build);

        // Each new entry notifies the build's followers: 10 a minute and 60 a day.
        if (! $actor->isAdmin() && ! RateLimit::spend("garage.entries.$actor->id", 10, 60)) {
            throw new ValidationException(['title' => $this->translator->trans('ernestdefoe-garage.api.too_many_updates')]);
        }

        if (Entry::query()->where('build_id', $build->id)->count() >= Entry::MAX_PER_BUILD) {
            throw new ValidationException(['title' => $this->translator->trans('ernestdefoe-garage.api.too_many_entries', ['count' => Entry::MAX_PER_BUILD])]);
        }

        // The log is the build's, even when a moderator helps out.
        $model->user_id = $build->user_id;
        $model->happened_on ??= Carbon::today();
        $model->comment_count = 0;
        $this->fresh[spl_object_id($model)] = true;

        return $model;
    }

    public function saved(object $model, OriginalContext $context): ?object
    {
        /** @var Entry $model */
        $fresh = isset($this->fresh[spl_object_id($model)]);
        unset($this->fresh[spl_object_id($model)]);

        if ($fresh || $model->wasChanged(['happened_on', 'cost'])) {
            $build = $this->bumpBuild($model, $fresh);

            if ($fresh && $build->published_at) {
                $this->queue()->push(new NotifyFollowers($model->id));
            }
        }

        return $model;
    }

    public function deleting(object $model, OriginalContext $context): void
    {
        /** @var Entry $model */
        $photos = $model->photos ?? [];
        foreach ($photos as $photo) {
            $this->photos->delete($photo);
        }
    }

    public function deleted(object $model, OriginalContext $context): void
    {
        /** @var Entry $model */
        // Its comments go too (where the database doesn't cascade), and the build's comment count with them.
        Comment::query()->where('entry_id', $model->id)->delete();

        $build = Build::query()->find($model->build_id);
        if ($build) {
            $build->refreshLog();
            $build->comment_count = Comment::query()->where('build_id', $build->id)->count();
            $build->save();
        }
    }

    /** Resolved when used, not injected: the constructor runs on every API request. */
    private function queue(): Queue
    {
        return resolve(Queue::class);
    }

    /** The build's log counts and latest entry, and (for news) its place in "Recently updated". */
    private function bumpBuild(Entry $entry, bool $news = true): Build
    {
        $build = $entry->build()->firstOrFail();
        $build->refreshLog();
        if ($news && $build->published_at) {
            $build->touchActivity();
        }
        $build->save();

        return $build;
    }

    public function fields(): array
    {
        $editable = fn (Entry $e, Context $context) => ! $e->exists || $context->getActor()->can('update', $e);

        return [
            Schema\Str::make('title')
                ->writable($editable)
                ->requiredOnCreate()
                ->rule('max:120')
                ->rule('regex:/\S/'),
            Schema\Str::make('body')
                ->nullable()
                ->writable($editable)
                ->rule('max:20000'),
            Schema\Number::make('cost')
                ->nullable()
                ->writable($editable)
                ->rule('numeric')
                ->rule('min:0')
                ->rule('max:9999999999')
                ->get(fn (Entry $e) => $e->cost !== null ? (float) $e->cost : null),
            Schema\Date::make('happenedOn')
                ->property('happened_on')
                ->writable($editable)
                ->rule('before_or_equal:'.Carbon::tomorrow()->toDateString())
                ->rule('after:1900-01-01'),
            Schema\Arr::make('photos')
                ->get(fn (Entry $e) => $this->photos->presentAll($e->photos)),
            Schema\Integer::make('commentCount')->property('comment_count'),
            Schema\DateTime::make('createdAt')->property('created_at'),
            Schema\Boolean::make('canEdit')
                ->get(fn (Entry $e, Context $context) => $context->getActor()->can('update', $e)),

            Schema\Relationship\ToOne::make('build')
                ->type('garage-builds')
                ->includable()
                ->writableOnCreate()
                ->requiredOnCreate(),
        ];
    }

    public function sorts(): array
    {
        return [
            SortColumn::make('happenedOn'),
            SortColumn::make('createdAt'),
        ];
    }
}
