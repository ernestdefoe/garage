<?php

namespace ErnestDefoe\Garage;

use Flarum\Api\Context;
use Flarum\Api\Schema;
use Flarum\Extension\ExtensionManager;
use Flarum\Formatter\Formatter;
use Flarum\Foundation\ValidationException;
use Flarum\Locale\TranslatorInterface;
use Flarum\User\User;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Stories and log updates written in Scribe. Its editor sends HTML, which
 * Flarum's formatter parses into XML (as it does a post's) and renders through
 * its allow-list; text written without Scribe stays plain text, rendered by
 * forum/text.js. A flag column says which a stored value is. It is never
 * guessed from the value: plain text can be typed to look like formatter XML,
 * and stored XML is rendered without being filtered again.
 */
class RichText
{
    /** A MySQL TEXT column. */
    private const MAX_BYTES = 65535;

    public function __construct(private readonly ExtensionManager $extensions)
    {
    }

    /** Scribe's editor is on: it stands down while Markdown or FoF Rich Text is enabled. */
    public function editor(): bool
    {
        return $this->extensions->isEnabled('ernestdefoe-scribe')
            && ! $this->extensions->isEnabled('flarum-markdown')
            && ! $this->extensions->isEnabled('fof-rich-text');
    }

    /**
     * A text column's API fields: `story` (plain text, as it always was),
     * `storySource` (the HTML Scribe edits, for whoever may edit it) and
     * `storyHtml` (the formatter's rendering, when it was written in Scribe).
     *
     * @return list<Schema\Str>
     */
    public function fields(string $name, string $column, string $flag, callable $writable, callable $visible): array
    {
        return [
            Schema\Str::make($name)
                ->nullable()
                ->writable($writable)
                ->rule('max:20000')
                ->visible($visible)
                ->get(fn ($model) => $this->text($model->$column, (bool) $model->$flag, $model))
                ->set(function ($model, ?string $value) use ($column, $flag) {
                    $model->$column = $value;
                    $model->$flag = false;
                }),
            Schema\Str::make($name.'Source')
                ->nullable()
                ->writable(fn ($model, Context $context) => $this->editor() && $writable($model, $context))
                // HTML: room for its markup. The stored XML's size is checked in parse().
                ->rule('max:40000')
                ->visible(fn ($model, Context $context) => (bool) $model->$flag && $visible($model, $context) && $context->getActor()->can('update', $model))
                ->get(fn ($model) => $this->source($model->$column, $model))
                ->set(function ($model, ?string $value, Context $context) use ($name, $column, $flag) {
                    $model->$column = $this->parse($value, $model, $context->getActor(), $name.'Source');
                    $model->$flag = $model->$column !== null;
                }),
            Schema\Str::make($name.'Html')
                ->visible(fn ($model, Context $context) => (bool) $model->$flag && $visible($model, $context))
                ->get(fn ($model, Context $context) => $this->render($model->$column, $model, $context->request)),
        ];
    }

    /** A stored value as plain text, whichever way it was written. */
    public function text(?string $value, bool $formatted, mixed $context): ?string
    {
        return $formatted && $value !== null ? $this->plain($value, $context) : $value;
    }

    /** Scribe's HTML as the formatter's XML, or null when it holds nothing. */
    public function parse(?string $html, mixed $context, User $actor, string $field): ?string
    {
        if ($html === null || ! preg_match('~<(img|video|iframe)\b~i', $html) && trim(html_entity_decode(strip_tags($html))) === '') {
            return null;
        }

        $xml = $this->formatter()->parse($html, $context, $actor);

        if (strlen($xml) > self::MAX_BYTES) {
            throw new ValidationException([$field => resolve(TranslatorInterface::class)->trans('ernestdefoe-garage.api.text_too_long')]);
        }

        return $xml;
    }

    public function render(string $xml, mixed $context, ?ServerRequestInterface $request): string
    {
        return $this->formatter()->render($xml, $context, $request);
    }

    /** The HTML it was written as, for Scribe to edit. */
    public function source(string $xml, mixed $context): string
    {
        return (string) $this->formatter()->unparse($xml, $context);
    }

    /** Its words, paragraphs kept: for the plain editor, page descriptions and data exports. */
    public function plain(string $xml, mixed $context): string
    {
        $html = preg_replace('~<br\s*/?>~i', "\n", $this->source($xml, $context));
        $html = preg_replace('~</(p|h[1-6]|li|blockquote|pre|div|tr|table|ul|ol|details|summary|aside|figure)>~i', "\n\n", (string) $html);
        $text = html_entity_decode(strip_tags((string) $html), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim((string) preg_replace(["~[ \t]+\n~", "~\n{3,}~"], ["\n", "\n\n"], $text));
    }

    /** Resolved when used: resources are built on every API request. */
    private function formatter(): Formatter
    {
        return resolve(Formatter::class);
    }
}
