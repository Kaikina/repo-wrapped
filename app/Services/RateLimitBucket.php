<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

/**
 * Redis-backed token bucket rate limiter.
 * Max 10 requests/second, max 4500 requests/hour.
 */
class RateLimitBucket
{
    private const PER_SECOND_LIMIT = 10;
    private const PER_HOUR_LIMIT   = 4500;

    public function acquire(): bool
    {
        try {
            if (! $this->checkSecondBucket()) {
                return false;
            }
            return $this->checkHourBucket();
        } catch (\Throwable $e) {
            // Redis unavailable — allow request to proceed (degrade gracefully)
            Log::warning('RateLimitBucket: Redis unavailable, bypassing rate limit', [
                'error' => $e->getMessage(),
            ]);
            return true;
        }
    }

    public function remainingHour(): int
    {
        try {
            $key   = $this->hourKey();
            $used  = (int) Redis::get($key);
            return max(0, self::PER_HOUR_LIMIT - $used);
        } catch (\Throwable) {
            return self::PER_HOUR_LIMIT;
        }
    }

    private function checkSecondBucket(): bool
    {
        $key = $this->secondKey();

        $count = Redis::incr($key);

        if ((int) $count === 1) {
            Redis::expire($key, 1);
        }

        return (int) $count <= self::PER_SECOND_LIMIT;
    }

    private function checkHourBucket(): bool
    {
        $key   = $this->hourKey();
        $count = Redis::incr($key);

        if ((int) $count === 1) {
            Redis::expire($key, 3600);
        }

        return (int) $count <= self::PER_HOUR_LIMIT;
    }

    private function secondKey(): string
    {
        return 'rate_bucket:second:' . floor(microtime(true));
    }

    private function hourKey(): string
    {
        return 'rate_bucket:hour:' . floor(time() / 3600);
    }
}
