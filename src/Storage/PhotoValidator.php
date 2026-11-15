<?php

namespace ErnestDefoe\Garage\Storage;

use ErnestDefoe\Garage\Settings;
use Flarum\Foundation\AbstractImageValidator;
use Flarum\Locale\TranslatorInterface;
use Flarum\Settings\SettingsRepositoryInterface;
use Illuminate\Validation\Factory;
use Intervention\Image\ImageManager;

/** A member's photo: JPEG, PNG or WebP, within the admin's size limit. */
class PhotoValidator extends AbstractImageValidator
{
    public function __construct(
        Factory $validator,
        TranslatorInterface $translator,
        ImageManager $imageManager,
        private readonly SettingsRepositoryInterface $settings,
    ) {
        parent::__construct($validator, $translator, $imageManager);
    }

    public function getMaxSize(): int
    {
        $kb = (int) $this->settings->get(Settings::MAX_UPLOAD_KB);

        return $kb > 0 ? $kb : 10240;
    }

    /** @return list<string> */
    protected function getAllowedTypes(): array
    {
        return ['jpeg', 'jpg', 'png', 'webp'];
    }
}
