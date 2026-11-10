<?php

namespace ErnestDefoe\Garage\Api;

use Illuminate\Contracts\Cache\Repository;

/**
 * A per-member allowance per minute and per day. 🚨 One cache key per member,
 * overwritten, never one per minute or per day: the file cache never deletes
 * expired entries, so time-stamped keys would leave a dead file behind for
 * every member every day.
 */
final class RateLimit
{
    public static function spend(string $key, int $perMinute, int $perDay): bool
    {
        $cache = resolve(Repository::class);
        $now = time();
        $day = (int) date('Ymd', $now);
        $minute = intdiv($now, 60);

        $state = $cache->get($key);
        $state = is_array($state) ? $state : [];
        $dayCount = ($state['day'] ?? 0) === $day ? (int) ($state['d'] ?? 0) : 0;
        $minuteCount = ($state['minute'] ?? 0) === $minute ? (int) ($state['m'] ?? 0) : 0;

        if ($dayCount >= $perDay || $minuteCount >= $perMinute) {
            return false;
        }

        $cache->put($key, ['day' => $day, 'd' => $dayCount + 1, 'minute' => $minute, 'm' => $minuteCount + 1], 86400);

        return true;
    }
}
