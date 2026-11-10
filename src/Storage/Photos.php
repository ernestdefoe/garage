<?php

namespace ErnestDefoe\Garage\Storage;

use ErnestDefoe\Garage\Settings;
use Flarum\Extension\ExtensionManager;
use Flarum\Foundation\ValidationException;
use Flarum\Locale\TranslatorInterface;
use Flarum\Settings\SettingsRepositoryInterface;
use Flarum\User\User;
use Illuminate\Contracts\Filesystem\Cloud;
use Illuminate\Contracts\Filesystem\Factory;
use Illuminate\Support\Str;
use Intervention\Image\ImageManager;
use Psr\Http\Message\UploadedFileInterface;
use RuntimeException;
use Throwable;

/**
 * Where build and log photos live. A stored photo is one of two shapes:
 *
 *  - Garage's own disk (public/assets/garage unless a storage driver says
 *    otherwise): {"path": "builds/x.webp", "w": 1600, "h": 1200}. Every image
 *    is re-encoded as WebP twice, full size and small, which also drops
 *    whatever metadata the original carried (a phone photo's location
 *    included).
 *  - FoF Upload, when it is enabled and the admin chose it: the member uploads
 *    there first and Garage keeps the file's URLs: {"url", "small", "fof", "w",
 *    "h"}. Removing it from a build leaves the file to FoF Upload's media
 *    manager.
 */
class Photos
{
    public const DISK = 'ernestdefoe-garage';

    private const FULL = 1920;

    private const SMALL = 640;

    private const MAX_HEIGHT = 4096;

    public function __construct(
        private readonly Factory $filesystems,
        private readonly SettingsRepositoryInterface $settings,
        private readonly ExtensionManager $extensions,
        private readonly TranslatorInterface $translator,
    ) {
    }

    /** Whether this member's photos go through FoF Upload. */
    public function viaFof(User $actor): bool
    {
        return (bool) $this->settings->get(Settings::USE_FOF_UPLOAD)
            && $this->extensions->isEnabled('fof-upload')
            && $actor->can('fof-upload.upload');
    }

    /**
     * A photo from the request: a multipart "photo" for Garage's own disk, or
     * {"fofUuid": "…"} for a file the member has just uploaded to FoF Upload.
     *
     * @return array<string, mixed>
     */
    public function fromRequest(?UploadedFileInterface $file, mixed $fofUuid, User $actor, string $folder): array
    {
        if (is_string($fofUuid) && $fofUuid !== '') {
            return $this->fromFof($fofUuid, $actor);
        }

        if (! $file || $file->getError() !== UPLOAD_ERR_OK) {
            throw new ValidationException(['photo' => $this->translator->trans('validation.required', ['attribute' => 'photo'])]);
        }

        resolve(PhotoValidator::class)->assertImageValid('photo', $file);

        return $this->store((string) $file->getStream()->getMetadata('uri'), $folder);
    }

    /**
     * Store an image from a local path or its bytes on Garage's own disk.
     *
     * @return array{path: string, w: int, h: int}
     */
    public function store(string $source, string $folder): array
    {
        // Scaled to full size first, so the small copy is made from that, not from the original.
        $image = resolve(ImageManager::class)->read($source)->scaleDown(width: self::FULL, height: self::MAX_HEIGHT);
        $name = $folder.'/'.Str::random(24).'.webp';
        $size = [$image->width(), $image->height()];
        $written = [];

        try {
            foreach ([$name => $image, self::small($name) => (clone $image)->scaleDown(width: self::SMALL, height: self::MAX_HEIGHT)] as $target => $copy) {
                $encoded = (string) $copy->toWebp(quality: 82);

                if ($encoded === '') {
                    throw new RuntimeException('The image could not be encoded.');
                }

                $this->disk()->put($target, $encoded);
                $written[] = $target;
            }
        } catch (Throwable $e) {
            $this->disk()->delete($written);

            throw $e;
        }

        return ['path' => $name, 'w' => $size[0], 'h' => $size[1]];
    }

    /** @return array<string, mixed> */
    private function fromFof(string $uuid, User $actor): array
    {
        $file = null;
        if ($this->viaFof($actor) && class_exists(\FoF\Upload\File::class)) {
            $file = \FoF\Upload\File::query()->where('uuid', $uuid)->where('actor_id', $actor->id)->first();
        }

        if (! $file || ! str_starts_with((string) $file->type, 'image/') || ! preg_match('~^https?://~i', (string) $file->url)) {
            throw new ValidationException(['photo' => $this->translator->trans('ernestdefoe-garage.api.photo_not_found')]);
        }

        return array_filter([
            'url' => $file->url,
            'small' => $file->thumbnail_url ?: null,
            'fof' => $file->uuid,
            'w' => $file->image_width ?: null,
            'h' => $file->image_height ?: null,
        ]);
    }

    /** A photo's full-size bytes, when Garage stores it (a data export); null for FoF Upload's. */
    public function contents(mixed $photo): ?string
    {
        return is_array($photo) && ! empty($photo['path']) && $this->disk()->exists($photo['path']) ? $this->disk()->get($photo['path']) : null;
    }

    /** Delete a photo's files, when they are Garage's own. */
    public function delete(mixed $photo): void
    {
        if (is_array($photo) && ! empty($photo['path'])) {
            $this->disk()->delete([$photo['path'], self::small($photo['path'])]);
        }
    }

    /**
     * What the API sends for a photo.
     *
     * @return array{url: string, small: string, w: int|null, h: int|null}|null
     */
    public function present(mixed $photo): ?array
    {
        if (! is_array($photo)) {
            return null;
        }

        if (! empty($photo['path'])) {
            $url = $this->disk()->url($photo['path']);
            $small = $this->disk()->url(self::small($photo['path']));
        } elseif (! empty($photo['url'])) {
            $url = (string) $photo['url'];
            $small = (string) ($photo['small'] ?? $url);
        } else {
            return null;
        }

        return ['url' => $url, 'small' => $small, 'w' => isset($photo['w']) ? (int) $photo['w'] : null, 'h' => isset($photo['h']) ? (int) $photo['h'] : null];
    }

    /**
     * @param list<mixed>|null $photos
     * @return list<array{url: string, small: string, w: int|null, h: int|null}>
     */
    public function presentAll(?array $photos): array
    {
        return array_values(array_filter(array_map($this->present(...), $photos ?? [])));
    }

    public static function small(string $name): string
    {
        return substr($name, 0, -5).'-sm.webp';
    }

    private function disk(): Cloud
    {
        /** @var Cloud */
        return $this->filesystems->disk(self::DISK);
    }
}
