<?php

namespace ErnestDefoe\Garage\Api;

use Illuminate\Contracts\Cache\LockProvider;
use Illuminate\Contracts\Cache\Repository;

/**
 * A per-member allowance per minute and per day. 🚨 One cache key per member,
 * overwritten, never one per minute or per day: the file cache never deletes
 * expired entries, so time-stamped keys would leave a dead file behind for
 * every member every day. Read and write happen under a cache lock when the
 * store has locks (file, Redis, database all do), so requests sent at the same
 * moment can't each read the old count and both pass.
 */
final class RateLimit
{
    public function __construct(private readonly Repository $cache)
    {
    }

    public function spend(string $key, int $perMinute, int $perDay): bool
    {
        $store = $this->cache->getStore();

        if (! $store instanceof LockProvider) {
            return $this->take($key, $perMinute, $perDay);
        }

        // Never waits: Garage's own forms send one request at a time (photos upload one by
        // one), so a member's second request arriving while the first holds the lock is a
        // burst, and is turned away like any request over the limit.
        return (bool) $store->lock("$key.lock", 5)->get(fn () => $this->take($key, $perMinute, $perDay));
    }

    private function take(string $key, int $perMinute, int $perDay): bool
    {
        $now = time();
        $day = (int) date('Ymd', $now);
        $minute = intdiv($now, 60);

        $state = $this->cache->get($key);
        $state = is_array($state) ? $state : [];
        $dayCount = ($state['day'] ?? 0) === $day ? (int) ($state['d'] ?? 0) : 0;
        $minuteCount = ($state['minute'] ?? 0) === $minute ? (int) ($state['m'] ?? 0) : 0;

        if ($dayCount >= $perDay || $minuteCount >= $perMinute) {
            return false;
        }

        $this->cache->put($key, ['day' => $day, 'd' => $dayCount + 1, 'minute' => $minute, 'm' => $minuteCount + 1], 86400);

        return true;
    }
}
