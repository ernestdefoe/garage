<?php

namespace ErnestDefoe\Garage\Extend;

use ErnestDefoe\Garage\Features;
use Flarum\Extend\ExtenderInterface;
use Flarum\Extension\Extension;
use Illuminate\Contracts\Container\Container;

/** Switches a Features flag on. Used inside Extend\Conditional, so it applies only while that extension is enabled. */
class Feature implements ExtenderInterface
{
    /** @param 'fofUpload'|'fofSignature'|'scribe'|'otherEditor' $flag */
    public function __construct(private readonly string $flag)
    {
    }

    public function extend(Container $container, ?Extension $extension = null): void
    {
        $container->resolving(Features::class, function (Features $features) {
            $features->{$this->flag} = true;
        });
    }
}
