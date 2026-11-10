<?php

namespace ErnestDefoe\Garage\Api;

use ErnestDefoe\Garage\Build;
use ErnestDefoe\Garage\Category;
use ErnestDefoe\Garage\Counts;
use Flarum\Api\Context;
use Flarum\Api\Endpoint;
use Flarum\Api\Resource\AbstractDatabaseResource;
use Flarum\Api\Schema;
use Flarum\Api\Sort\SortColumn;
use Flarum\Foundation\ValidationException;
use Flarum\Locale\TranslatorInterface;
use Illuminate\Support\Str;
use Laminas\Diactoros\Response\EmptyResponse;
use Tobyz\JsonApiServer\Context as OriginalContext;

/**
 * The admin's categories and their spec sheets. Everyone may list them (the
 * page's sidebar and the build form need them); only admins change them.
 *
 * @extends AbstractDatabaseResource<Category>
 */
class CategoryResource extends AbstractDatabaseResource
{
    public function __construct(private readonly TranslatorInterface $translator)
    {
    }

    public function type(): string
    {
        return 'garage-categories';
    }

    public function model(): string
    {
        return Category::class;
    }

    public function endpoints(): array
    {
        return [
            Endpoint\Index::make()
                ->defaultSort('position'),
            Endpoint\Show::make(),
            Endpoint\Create::make()->admin(),
            Endpoint\Update::make()->admin(),
            Endpoint\Delete::make()->admin(),
            // {"ids": [3, 1, 2]}: every category in its new order.
            Endpoint\Endpoint::make('garage.categories.order')
                ->route('POST', '/order')
                ->admin()
                ->action(function (Context $context) {
                    foreach (array_values((array) ($context->body()['ids'] ?? [])) as $position => $id) {
                        Category::query()->whereKey((int) $id)->update(['position' => $position]);
                    }

                    return [];
                })
                ->response(fn () => new EmptyResponse(204)),
        ];
    }

    public function creating(object $model, OriginalContext $context): ?object
    {
        /** @var Category $model */
        $model->position = (int) Category::query()->max('position') + 1;
        $model->fields ??= [];

        return $model;
    }

    public function saving(object $model, OriginalContext $context): ?object
    {
        /** @var Category $model */
        if ($model->isDirty('name')) {
            $model->slug = Str::slug($model->name) ?: 'category';
        }

        return $model;
    }

    /**
     * Builds are never deleted with their category: they move to the first
     * other one. The last category can't go while it has builds.
     */
    public function deleting(object $model, OriginalContext $context): void
    {
        /** @var Category $model */
        if (! Build::query()->where('category_id', $model->id)->exists()) {
            return;
        }

        $to = Category::query()->whereKeyNot($model->id)->orderBy('position')->first();
        if (! $to) {
            throw new ValidationException(['category' => $this->translator->trans('ernestdefoe-garage.api.last_category')]);
        }

        // Each build keeps only the values the new category's spec sheet takes (a link stays a link).
        Build::query()->where('category_id', $model->id)->each(function (Build $build) use ($to) {
            $build->category_id = $to->id;
            $build->specs = $to->cleanSpecs($build->specs) ?: null;
            $build->timestamps = false;
            $build->save();
        });
        resolve(Counts::class)->categories([$to->id]);
    }

    public function fields(): array
    {
        return [
            Schema\Str::make('name')
                ->writable()
                ->requiredOnCreate()
                ->rule('max:80')
                ->rule('regex:/\S/'),
            Schema\Str::make('slug'),
            Schema\Str::make('description')
                ->nullable()
                ->writable()
                ->rule('max:300'),
            Schema\Str::make('icon')
                ->nullable()
                ->writable()
                ->rule('max:60')
                ->rule('regex:/^[a-z0-9 -]*$/'),
            Schema\Str::make('color')
                ->nullable()
                ->writable()
                ->rule('regex:/^(#[0-9a-fA-F]{3,8})?$/'),
            // The spec sheet: [{key, label, type, options?, unit?, card, filter?}].
            Schema\Arr::make('fields')
                ->get(fn (Category $c) => $c->fields ?? [])
                ->writable()
                ->set(fn (Category $c, $value) => $c->fields = Category::cleanFields($value)),
            Schema\Integer::make('position'),
            Schema\Integer::make('buildCount')->property('build_count'),
        ];
    }

    public function sorts(): array
    {
        return [SortColumn::make('position')];
    }
}
