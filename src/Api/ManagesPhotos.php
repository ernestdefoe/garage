<?php

namespace ErnestDefoe\Garage\Api;

use ErnestDefoe\Garage\Build;
use ErnestDefoe\Garage\Entry;
use ErnestDefoe\Garage\Storage\Photos;
use Flarum\Api\Context;
use Flarum\Api\Endpoint;
use Flarum\Foundation\ValidationException;
use Flarum\Locale\TranslatorInterface;
use Illuminate\Support\Arr;

/**
 * Photos on a build or a log entry, one at a time: add (multipart "photo",
 * or {"fofUuid"} after an upload to FoF Upload), remove by index, and put in
 * a new order (the first is the cover).
 */
trait ManagesPhotos
{
    abstract protected function maxPhotos(): int;

    abstract protected function photoFolder(): string;

    /** @return list<Endpoint\Endpoint> */
    protected function photoEndpoints(string $name): array
    {
        return [
            Endpoint\Endpoint::make("$name.photos.add")
                ->route('POST', '/{id}/photos')
                ->authenticated()
                ->can('update')
                ->action(fn (Context $context) => $this->addPhoto($context)),
            // {"url": "…"}: the photo's url as the API sent it. By url, not position, so a list
            // reordered in another tab can never take the wrong photo.
            Endpoint\Endpoint::make("$name.photos.remove")
                ->route('POST', '/{id}/photos/remove')
                ->authenticated()
                ->can('update')
                ->action(fn (Context $context) => $this->removePhoto($context->model, (string) ($context->body()['url'] ?? ''))),
            // {"order": [2, 0, 1]}: the photos' current indexes in their new order.
            Endpoint\Endpoint::make("$name.photos.order")
                ->route('POST', '/{id}/photos/order')
                ->authenticated()
                ->can('update')
                ->action(fn (Context $context) => $this->orderPhotos($context->model, (array) ($context->body()['order'] ?? []))),
        ];
    }

    private function addPhoto(Context $context): Build|Entry
    {
        /** @var Build|Entry $model */
        $model = $context->model;
        $actor = $context->getActor();
        $translator = resolve(TranslatorInterface::class);

        // Each photo is decoded and encoded twice: members get 20 a minute and 300 a day.
        if (! $actor->isAdmin() && ! RateLimit::spend("garage.photos.$actor->id", 20, 300)) {
            throw new ValidationException(['photo' => $translator->trans('ernestdefoe-garage.api.too_many_uploads')]);
        }

        if (count($model->photos ?? []) >= $this->maxPhotos()) {
            throw new ValidationException(['photo' => $translator->trans('ernestdefoe-garage.api.too_many_photos', ['count' => $this->maxPhotos()])]);
        }

        // Resolved here, not injected: the constructor runs on every API request.
        $photo = resolve(Photos::class)->fromRequest(
            Arr::get($context->request->getUploadedFiles(), 'photo'),
            $context->body()['fofUuid'] ?? null,
            $actor,
            $this->photoFolder(),
        );

        $model->photos = [...($model->photos ?? []), $photo];
        $this->photosChanged($model);
        $model->save();

        return $model;
    }

    private function removePhoto(Build|Entry $model, string $url): Build|Entry
    {
        $storage = resolve(Photos::class);
        $photos = $model->photos ?? [];

        foreach ($photos as $index => $photo) {
            if ($url !== '' && ($storage->present($photo)['url'] ?? null) === $url) {
                $storage->delete($photo);
                array_splice($photos, $index, 1);
                $model->photos = $photos ?: null;
                $model->save();
                break;
            }
        }

        return $model;
    }

    /** @param array<mixed> $order */
    private function orderPhotos(Build|Entry $model, array $order): Build|Entry
    {
        $photos = $model->photos ?? [];
        $sorted = [];

        foreach ($order as $index) {
            if (is_numeric($index) && isset($photos[(int) $index])) {
                $sorted[(int) $index] = $photos[(int) $index];
            }
        }
        // Any left out keep their place at the end, so nothing is lost to a stale list.
        $model->photos = array_values($sorted + $photos);
        $model->save();

        return $model;
    }

    protected function photosChanged(Build|Entry $model): void
    {
    }
}
