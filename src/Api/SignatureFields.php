<?php

namespace ErnestDefoe\Garage\Api;

use ErnestDefoe\Garage\Settings;
use Flarum\Api\Context;
use Flarum\Api\Schema\Attribute;
use Flarum\User\User;

/**
 * With FoF Signature on (registered only then), `garageSignature`: the card
 * of the build a member shows under their posts. Batched across every user on
 * the page (Cards).
 */
class SignatureFields
{
    public function __construct(private readonly Cards $cards)
    {
    }

    /** @return list<Attribute> */
    public function __invoke(): array
    {
        return [
            Attribute::make('garageSignature')
                ->visible(fn (User $user, Context $context) => $user->garage_signature_build_id
                    && $context->getActor()->hasPermission(Settings::VIEW))
                ->get(fn (User $user, Context $context) => $this->cards->deferred($context, [(int) $user->garage_signature_build_id], fn (array $cards) => $cards[0] ?? null, $user)),
        ];
    }
}
