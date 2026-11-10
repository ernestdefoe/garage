<?php

namespace ErnestDefoe\Garage\Api;

use ErnestDefoe\Garage\Build;
use ErnestDefoe\Garage\Storage\Photos;
use Flarum\Api\Context;
use Flarum\Http\SlugManager;
use Flarum\User\User;
use Psr\Http\Message\ServerRequestInterface;
use WeakMap;

/**
 * Small build cards for places outside Garage: a build linked in a post and
 * the build under a member's posts (FoF Signature). 🚨 One query (plus one for
 * the categories and one for the owners) per request, however many posts and
 * members are on the page: getters queue build ids and return a deferred
 * value; the first one resolved loads everything queued. Only builds the
 * viewer may see come back.
 */
class Cards
{
    /** @var WeakMap<ServerRequestInterface, array{queued: array<int, true>, rows: array<int, array<string, mixed>|null>}> */
    private WeakMap $byRequest;

    public function __construct(private readonly Photos $photos)
    {
        $this->byRequest = new WeakMap();
    }

    /**
     * @param list<int> $ids
     * @param callable(list<array<string, mixed>>): mixed $value
     */
    public function deferred(Context $context, array $ids, callable $value): \Closure
    {
        $request = $context->request;
        $state = $this->byRequest[$request] ?? ['queued' => [], 'rows' => []];

        foreach ($ids as $id) {
            if (! array_key_exists($id, $state['rows'])) {
                $state['queued'][$id] = true;
            }
        }
        $this->byRequest[$request] = $state;

        return function () use ($context, $ids, $value) {
            $rows = $this->load($context);

            return $value(array_values(array_filter(array_map(fn ($id) => $rows[$id] ?? null, $ids))));
        };
    }

    /** @return array<int, array<string, mixed>|null> */
    private function load(Context $context): array
    {
        $state = $this->byRequest[$context->request];
        $ids = array_keys($state['queued']);

        if ($ids) {
            $builds = Build::query()->whereVisibleTo($context->getActor())->whereIn('garage_builds.id', $ids)
                ->whereNotNull('garage_builds.published_at')
                ->with(['category', 'user'])->get()->keyBy('id');

            foreach ($ids as $id) {
                $state['rows'][$id] = isset($builds[$id]) ? $this->card($builds[$id]) : null;
            }
            $state['queued'] = [];
            $this->byRequest[$context->request] = $state;
        }

        return $state['rows'];
    }

    /** @return array<string, mixed> */
    public function card(Build $build): array
    {
        $cover = $this->photos->present($build->photos[0] ?? null);

        return [
            'id' => $build->id,
            'slug' => $build->slug,
            'title' => $build->title,
            'tagline' => $build->tagline,
            'stage' => $build->stage,
            'cover' => $cover['small'] ?? null,
            'likeCount' => $build->like_count,
            'entryCount' => $build->entry_count,
            'category' => $build->category ? ['name' => $build->category->name, 'color' => $build->category->color, 'icon' => $build->category->icon] : null,
            'user' => $build->user ? ['displayName' => $build->user->display_name, 'slug' => resolve(SlugManager::class)->forResource(User::class)->toSlug($build->user), 'avatarUrl' => $build->user->avatar_url] : null,
        ];
    }
}
