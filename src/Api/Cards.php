<?php

namespace ErnestDefoe\Garage\Api;

use ErnestDefoe\Garage\Build;
use ErnestDefoe\Garage\Category;
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
    /** @var WeakMap<ServerRequestInterface, array{queued: array<int, true>, rows: array<int, array<string, mixed>|null>, owners: array<int, User>, categories?: \Illuminate\Support\Collection<array-key, Category>}> */
    // Static: post fields, user fields and resources each get their own instance, and must share one batch.
    private static WeakMap $byRequest;

    public function __construct(private readonly Photos $photos)
    {
        self::$byRequest ??= new WeakMap();
    }

    /**
     * @param list<int> $ids
     * @param callable(list<array<string, mixed>>): mixed $value
     */
    public function deferred(Context $context, array $ids, callable $value, ?User $owner = null): \Closure
    {
        $this->queue($context, $ids, $owner);

        return function () use ($context, $ids, $value) {
            $rows = $this->load($context);

            return $value(array_values(array_filter(array_map(fn ($id) => $rows[$id] ?? null, $ids))));
        };
    }

    /**
     * Ask for builds that will be wanted later in this request (a post's author's
     * signature, before the author is serialized), so they load in the same query.
     *
     * @param list<int> $ids
     */
    public function queue(Context $context, array $ids, ?User $owner = null): void
    {
        $state = self::$byRequest[$context->request] ?? ['queued' => [], 'rows' => [], 'owners' => []];
        // A member already loaded (their own signature build): no need to fetch them again.
        if ($owner) {
            $state['owners'][(int) $owner->id] = $owner;
        }

        foreach ($ids as $id) {
            if (! array_key_exists($id, $state['rows'])) {
                $state['queued'][$id] = true;
            }
        }
        self::$byRequest[$context->request] = $state;
    }

    /**
     * Everything queued so far: the builds in one query, their categories from
     * one query per request (a small table), and only the owners not already
     * loaded.
     *
     * @return array<int, array<string, mixed>|null>
     */
    private function load(Context $context): array
    {
        $state = self::$byRequest[$context->request];
        $ids = array_keys($state['queued']);

        if ($ids) {
            $builds = Build::query()->whereVisibleTo($context->getActor())->whereIn('garage_builds.id', $ids)
                ->whereNotNull('garage_builds.published_at')->get()->keyBy('id');

            $state['categories'] ??= Category::query()->get()->keyBy('id');
            $missing = array_diff($builds->pluck('user_id')->unique()->all(), array_keys($state['owners']));
            foreach ($missing ? User::query()->whereIn('id', $missing)->get() : [] as $user) {
                $state['owners'][(int) $user->id] = $user;
            }

            foreach ($ids as $id) {
                $build = $builds[$id] ?? null;
                if ($build) {
                    $build->setRelation('category', $state['categories'][$build->category_id] ?? null);
                    $build->setRelation('user', $state['owners'][$build->user_id] ?? null);
                }
                $state['rows'][$id] = $build ? $this->card($build) : null;
            }
            $state['queued'] = [];
            self::$byRequest[$context->request] = $state;
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
