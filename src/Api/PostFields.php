<?php

namespace ErnestDefoe\Garage\Api;

use ErnestDefoe\Garage\Settings;
use Flarum\Api\Context;
use Flarum\Api\Schema\Attribute;
use Flarum\Http\UrlGenerator;
use Flarum\Post\CommentPost;
use Flarum\Post\Post;

/**
 * `garageEmbeds` on a comment post that links to builds: the builds' cards,
 * so the post shows a link on a line of its own as a card. Found by reading
 * the stored post for "<forum>/garage/<id>"; posts without one cost nothing.
 * Batched across every post on the page (Cards): one query, not one per post.
 */
class PostFields
{
    private ?string $pattern = null;

    public function __construct(private readonly Cards $cards, private readonly UrlGenerator $url)
    {
    }

    /** @return list<Attribute> */
    public function __invoke(): array
    {
        return [
            Attribute::make('garageEmbeds')
                ->visible(fn (Post $post, Context $context) => $post instanceof CommentPost && $context->getActor()->hasPermission(Settings::VIEW))
                ->get(function (Post $post, Context $context) {
                    $ids = $this->ids((string) ($post->getAttributes()['content'] ?? ''));

                    return $ids ? $this->cards->deferred($context, $ids, fn (array $cards) => $cards ?: null) : null;
                }),
        ];
    }

    /** @return list<int> */
    private function ids(string $content): array
    {
        if (! str_contains($content, '/garage/')) {
            return [];
        }

        $this->pattern ??= '~'.preg_quote(rtrim($this->url->to('forum')->base(), '/'), '~').'/garage/(\d+)~';
        preg_match_all($this->pattern, $content, $matches);

        return array_slice(array_values(array_unique(array_map('intval', $matches[1]))), 0, 10);
    }
}
