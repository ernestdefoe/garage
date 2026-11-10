<?php

namespace ErnestDefoe\Garage\Api;

use Carbon\Carbon;
use ErnestDefoe\Garage\Build;
use ErnestDefoe\Garage\Comment;
use ErnestDefoe\Garage\Entry;
use ErnestDefoe\Garage\Notification\CommentBlueprint;
use ErnestDefoe\Garage\Settings;
use Flarum\Api\Context;
use Flarum\Api\Endpoint;
use Flarum\Api\Resource\AbstractDatabaseResource;
use Flarum\Api\Schema;
use Flarum\Api\Sort\SortColumn;
use Flarum\Foundation\ValidationException;
use Flarum\Locale\TranslatorInterface;
use Flarum\Notification\Notification;
use Flarum\Notification\NotificationSyncer;
use Illuminate\Database\Eloquent\Builder;
use Tobyz\JsonApiServer\Context as OriginalContext;

/**
 * Comments on a build, or on one of its log entries (entryId). Plain text:
 * the page turns links and line breaks into markup itself, never HTML from
 * the server. The build's owner is told about each one.
 *
 * @extends AbstractDatabaseResource<Comment>
 */
class CommentResource extends AbstractDatabaseResource
{
    public function __construct(private readonly TranslatorInterface $translator)
    {
    }

    public function type(): string
    {
        return 'garage-comments';
    }

    public function model(): string
    {
        return Comment::class;
    }

    public function scope(Builder $query, OriginalContext $context): void
    {
        $query->whereVisibleTo($context->getActor());
    }

    public function endpoints(): array
    {
        return [
            Endpoint\Index::make()
                ->paginate(50, 200)
                ->defaultInclude(['user'])
                ->defaultSort('createdAt')
                ->eagerLoad(['build'])
                ->eagerLoadWhenIncluded(['user' => ['user.groups']]),
            Endpoint\Create::make()
                ->authenticated()
                ->defaultInclude(['user']),
            Endpoint\Update::make()
                ->authenticated()
                ->can('update')
                ->defaultInclude(['user']),
            Endpoint\Delete::make()
                ->authenticated()
                ->can('delete'),
        ];
    }

    public function creating(object $model, OriginalContext $context): ?object
    {
        /** @var Comment $model */
        $actor = $context->getActor();
        $actor->assertCan('comment', $model->build);

        // A conversation, not a flood: 5 a minute, 200 a day.
        if (! $actor->isAdmin() && ! RateLimit::spend("garage.comments.$actor->id", 5, 200)) {
            throw new ValidationException(['body' => $this->translator->trans('ernestdefoe-garage.api.too_many_comments')]);
        }

        if ($model->entry_id && ! Entry::query()->whereKey($model->entry_id)->where('build_id', $model->build_id)->exists()) {
            $model->entry_id = null;
        }

        $model->user_id = (int) $actor->id;
        $model->created_at = Carbon::now();

        return $model;
    }

    public function updating(object $model, OriginalContext $context): ?object
    {
        /** @var Comment $model */
        if ($model->isDirty('body')) {
            $model->edited_at = Carbon::now();
        }

        return $model;
    }

    public function created(object $model, OriginalContext $context): ?object
    {
        /** @var Comment $model */
        $this->recount($model);

        $build = $model->build;
        if ($build && $build->user_id !== $model->user_id && $build->user) {
            resolve(NotificationSyncer::class)->sync(new CommentBlueprint($model, $context->getActor()), [$build->user]);
        }

        return $model;
    }

    public function deleted(object $model, OriginalContext $context): void
    {
        /** @var Comment $model */
        $this->recount($model);

        // The owner's "commented on your build" for this comment goes with it.
        Notification::query()->where('type', CommentBlueprint::getType())->where('subject_id', $model->build_id)->get(['id', 'data'])
            ->filter(fn (Notification $n) => (int) (((array) $n->data)['comment'] ?? 0) === $model->id)
            ->each(fn (Notification $n) => $n->delete());
    }

    private function recount(Comment $comment): void
    {
        Build::query()->whereKey($comment->build_id)->update(['comment_count' => Comment::query()->where('build_id', $comment->build_id)->count()]);

        if ($comment->entry_id) {
            Entry::query()->whereKey($comment->entry_id)->update(['comment_count' => Comment::query()->where('entry_id', $comment->entry_id)->count()]);
        }
    }

    public function fields(): array
    {
        return [
            Schema\Str::make('body')
                ->writable(fn (Comment $c, Context $context) => ! $c->exists || $context->getActor()->can('update', $c))
                ->requiredOnCreate()
                ->rule('max:'.Comment::MAX_LENGTH)
                ->rule('regex:/\S/'),
            Schema\Integer::make('entryId')
                ->property('entry_id')
                ->nullable()
                ->writableOnCreate(),
            Schema\DateTime::make('createdAt')->property('created_at'),
            Schema\DateTime::make('editedAt')->property('edited_at'),
            Schema\Boolean::make('canEdit')
                ->get(fn (Comment $c, Context $context) => $context->getActor()->can('update', $c)),
            Schema\Boolean::make('canDelete')
                ->get(fn (Comment $c, Context $context) => $context->getActor()->can('delete', $c)),

            Schema\Relationship\ToOne::make('user')
                ->type('users')
                ->includable(),
            Schema\Relationship\ToOne::make('build')
                ->type('garage-builds')
                ->writableOnCreate()
                ->requiredOnCreate(),
        ];
    }

    public function sorts(): array
    {
        return [SortColumn::make('createdAt')];
    }
}
