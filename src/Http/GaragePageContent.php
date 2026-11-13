<?php

namespace ErnestDefoe\Garage\Http;

use ErnestDefoe\Garage\Build;
use ErnestDefoe\Garage\RichText;
use ErnestDefoe\Garage\Storage\Photos;
use Flarum\Frontend\Document;
use Flarum\Http\RequestUtil;
use Flarum\Http\UrlGenerator;
use Flarum\Locale\TranslatorInterface;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Psr\Http\Message\ServerRequestInterface;

/**
 * /garage and /garage/{id}-{slug} as the server sends them: a title, a
 * canonical URL and, for a build the visitor may see, a description and a
 * cover picture for link previews. One query, on these pages only.
 */
class GaragePageContent
{
    public function __construct(
        private readonly UrlGenerator $url,
        private readonly TranslatorInterface $translator,
        private readonly Photos $photos,
        private readonly RichText $richText,
    ) {
    }

    public function __invoke(Document $document, ServerRequestInterface $request): Document
    {
        $id = (int) Arr::get($request->getAttribute('routeParameters', []), 'id', 0);

        $build = $id > 0 ? Build::query()->whereVisibleTo(RequestUtil::getActor($request))->whereNotNull('published_at')->find($id) : null;

        if (! $build) {
            $document->title = (string) $this->translator->trans('ernestdefoe-garage.forum.page.title');
            $document->canonicalUrl = $this->url->to('forum')->route('garage');

            return $document;
        }

        $url = $this->url->to('forum')->route('garage.build', ['id' => $build->id.'-'.$build->slug]);
        $description = Str::limit(trim(preg_replace('/\s+/', ' ', (string) ($build->tagline ?: $this->richText->text($build->story, $build->story_formatted, $build)))), 200);
        $cover = $this->photos->present($build->photos[0] ?? null);

        $document->title = $build->title;
        $document->canonicalUrl = $url;
        if ($description !== '') {
            $document->meta['description'] = $description;
        }

        $document->head[] = '<meta property="og:type" content="article">';
        $document->head[] = '<meta property="og:title" content="'.e($build->title).'">';
        $document->head[] = '<meta property="og:url" content="'.e($url).'">';
        if ($description !== '') {
            $document->head[] = '<meta property="og:description" content="'.e($description).'">';
        }
        if ($cover) {
            $document->head[] = '<meta property="og:image" content="'.e($cover['url']).'">';
            $document->head[] = '<meta name="twitter:card" content="summary_large_image">';
        }

        return $document;
    }
}
