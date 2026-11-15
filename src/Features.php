<?php

namespace ErnestDefoe\Garage;

/**
 * Which optional extensions Garage works with on this forum. Every flag is
 * off until extend.php switches it on, inside Extend\Conditional, when that
 * extension is enabled (Extend\Feature): the code that branches on another
 * extension reads a flag instead of asking the extension manager.
 */
class Features
{
    /** FoF Upload: photos can be stored through it. */
    public bool $fofUpload = false;

    /** FoF Signature: a build can show under its owner's posts. */
    public bool $fofSignature = false;

    /** Scribe: stories and updates can be written in its editor. */
    public bool $scribe = false;

    /** Markdown or FoF Rich Text: either one takes the editor over, and Scribe stands down. */
    public bool $otherEditor = false;
}
