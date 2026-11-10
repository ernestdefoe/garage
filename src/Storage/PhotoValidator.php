<?php

namespace ErnestDefoe\Garage\Storage;

use ErnestDefoe\Garage\Settings;
use Flarum\Foundation\AbstractImageValidator;
use Flarum\Settings\SettingsRepositoryInterface;

/** A member's photo: JPEG, PNG or WebP, within the admin's size limit. */
class PhotoValidator extends AbstractImageValidator
{
    public function getMaxSize(): int
    {
        $kb = (int) resolve(SettingsRepositoryInterface::class)->get(Settings::MAX_UPLOAD_KB);

        return $kb > 0 ? $kb : 10240;
    }

    /** @return list<string> */
    protected function getAllowedTypes(): array
    {
        return ['jpeg', 'jpg', 'png', 'webp'];
    }
}
