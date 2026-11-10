<?php

namespace ErnestDefoe\Garage\Api;

use Flarum\Api\Context;
use Illuminate\Database\ConnectionInterface;
use Psr\Http\Message\ServerRequestInterface;
use WeakMap;

/**
 * Whether the viewer likes / follows each build on the page. 🚨 One query per
 * table per request, however many builds are listed: each getter queues its
 * build and returns a deferred value; the first one resolved loads every
 * queued build's rows at once. Guests cost nothing. State is keyed by the
 * request in a WeakMap, never kept on a model.
 */
class ViewerState
{
    /** @var WeakMap<ServerRequestInterface, array<string, array{queued: array<int, true>, rows: array<int, bool>}>> */
    // Static: post fields, user fields and resources each get their own instance, and must share one batch.
    private static WeakMap $byRequest;

    public function __construct(private readonly ConnectionInterface $db)
    {
        self::$byRequest ??= new WeakMap();
    }

    /** @param 'garage_likes'|'garage_follows' $table */
    public function deferred(Context $context, string $table, int $buildId): \Closure|bool
    {
        $actor = $context->getActor();
        if ($actor->isGuest()) {
            return false;
        }

        $request = $context->request;
        $state = self::$byRequest[$request] ?? [];
        $state[$table] ??= ['queued' => [], 'rows' => []];

        if (! array_key_exists($buildId, $state[$table]['rows'])) {
            $state[$table]['queued'][$buildId] = true;
        }
        self::$byRequest[$request] = $state;

        return fn () => $this->load($request, $table, (int) $actor->id)[$buildId] ?? false;
    }

    /** After a toggle: what this request answers from now on. */
    public function set(Context $context, string $table, int $buildId, bool $value): void
    {
        $state = self::$byRequest[$context->request] ?? [];
        $state[$table] ??= ['queued' => [], 'rows' => []];
        $state[$table]['rows'][$buildId] = $value;
        unset($state[$table]['queued'][$buildId]);
        self::$byRequest[$context->request] = $state;
    }

    /** @return array<int, bool> */
    private function load(ServerRequestInterface $request, string $table, int $userId): array
    {
        $state = self::$byRequest[$request];
        $ids = array_keys($state[$table]['queued']);

        if ($ids) {
            $found = $this->db->table($table)->where('user_id', $userId)->whereIn('build_id', $ids)->pluck('build_id')->flip();
            foreach ($ids as $id) {
                $state[$table]['rows'][$id] = isset($found[$id]);
            }
            $state[$table]['queued'] = [];
            self::$byRequest[$request] = $state;
        }

        return $state[$table]['rows'];
    }
}
