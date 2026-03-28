<?php

declare(strict_types=1);

namespace AgentGateway\Middleware;

use AgentGateway\Logger;

class RateLimitMiddleware
{
    /** @var array<string, array{minute_count: int, minute_reset: int, day_count: int, day_reset: int}> */
    private static array $limits = [];

    public static function handle(string $appId, int $perMinute, int $perDay): bool
    {
        $now   = time();
        $entry = self::$limits[$appId] ?? null;

        if ($entry === null) {
            $entry = [
                'minute_count' => 0,
                'minute_reset' => $now + 60,
                'day_count'    => 0,
                'day_reset'    => $now + 86400,
            ];
        }

        // Reset windows if expired
        if ($now > $entry['minute_reset']) {
            $entry['minute_count'] = 0;
            $entry['minute_reset'] = $now + 60;
        }
        if ($now > $entry['day_reset']) {
            $entry['day_count'] = 0;
            $entry['day_reset'] = $now + 86400;
        }

        if ($entry['minute_count'] >= $perMinute) {
            Logger::get()->warning('Rate limit exceeded (per-minute)', ['app_id' => $appId]);
            http_response_code(429);
            echo json_encode([
                'error'  => "Rate limit exceeded. Maximum {$perMinute} requests per minute.",
                'status' => 429,
            ]);
            self::$limits[$appId] = $entry;
            return false;
        }

        if ($entry['day_count'] >= $perDay) {
            Logger::get()->warning('Rate limit exceeded (per-day)', ['app_id' => $appId]);
            http_response_code(429);
            echo json_encode([
                'error'  => "Rate limit exceeded. Maximum {$perDay} requests per day.",
                'status' => 429,
            ]);
            self::$limits[$appId] = $entry;
            return false;
        }

        $entry['minute_count']++;
        $entry['day_count']++;
        self::$limits[$appId] = $entry;

        return true;
    }
}
