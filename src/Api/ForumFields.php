<?php

namespace ErnestDefoe\Garage\Api;

use ErnestDefoe\Garage\RichText;
use ErnestDefoe\Garage\Settings;
use ErnestDefoe\Garage\Storage\Photos;
use Flarum\Api\Context;
use Flarum\Api\Schema;

/**
 * What the forum tells the browser about Garage: the viewer's own permissions
 * (already loaded, so no query), where their photos go and whether stories
 * are written in Scribe.
 */
class ForumFields
{
    public function __construct(
        private readonly Photos $photos,
        private readonly RichText $richText,
    ) {
    }

    /** @return list<Schema\Attribute> */
    public function __invoke(): array
    {
        return [
            Schema\Boolean::make('garageCanView')
                ->get(fn ($forum, Context $context) => $context->getActor()->hasPermission(Settings::VIEW)),
            Schema\Boolean::make('garageCanCreate')
                ->get(fn ($forum, Context $context) => $context->getActor()->hasPermission(Settings::CREATE)),
            Schema\Boolean::make('garageCanModerate')
                ->get(fn ($forum, Context $context) => $context->getActor()->hasPermission(Settings::MODERATE)),
            // Where this member's photos go: FoF Upload, or Garage's own storage.
            Schema\Str::make('garageUploads')
                ->visible(fn ($forum, Context $context) => $context->getActor()->hasPermission(Settings::CREATE))
                ->get(fn ($forum, Context $context) => $this->photos->viaFof($context->getActor()) ? 'fof' : 'own'),
            // Stories and updates are written in Scribe's editor when it is on.
            Schema\Boolean::make('garageRichText')
                ->visible(fn ($forum, Context $context) => $context->getActor()->hasPermission(Settings::CREATE))
                ->get(fn () => $this->richText->editor()),
        ];
    }
}
