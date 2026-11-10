<?php

namespace ErnestDefoe\Garage\Api;

use ErnestDefoe\Garage\Settings;
use Flarum\Api\Context;
use Flarum\Api\Schema\Attribute;
use Flarum\Api\Schema\Integer;
use Flarum\Extension\ExtensionManager;
use Flarum\User\User;

/**
 * `garageBuildCount` for the profile tab (a column, no query), and with FoF
 * Signature on, `garageSignature`: the card of the build a member shows under
 * their posts. Batched across every user on the page (Cards).
 */
class UserFields
{
    public function __construct(private readonly Cards $cards, private readonly ExtensionManager $extensions)
    {
    }

    /** @return list<Attribute> */
    public function __invoke(): array
    {
        return [
            Integer::make('garageBuildCount')
                ->property('garage_build_count'),

            Attribute::make('garageSignature')
                ->visible(fn (User $user, Context $context) => $user->garage_signature_build_id
                    && $context->getActor()->hasPermission(Settings::VIEW)
                    && $this->extensions->isEnabled('fof-signature'))
                ->get(fn (User $user, Context $context) => $this->cards->deferred($context, [(int) $user->garage_signature_build_id], fn (array $cards) => $cards[0] ?? null, $user)),
        ];
    }
}
