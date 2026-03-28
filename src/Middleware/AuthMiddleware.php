<?php

declare(strict_types=1);

namespace AgentGateway\Middleware;

class AuthMiddleware
{
    /**
     * Validate that the request includes Ontraport API credentials.
     * Returns credentials on success, or sends a 401 response and returns null.
     *
     * @return array{api_key: string, app_id: string}|null
     */
    public static function handle(): ?array
    {
        $apiKey = $_SERVER['HTTP_API_KEY'] ?? null;
        $appId  = $_SERVER['HTTP_API_APPID'] ?? null;

        if (empty($apiKey) || empty($appId)) {
            http_response_code(401);
            echo json_encode([
                'error'  => 'Missing required authentication headers: Api-Key and Api-Appid',
                'status' => 401,
            ]);
            return null;
        }

        return [
            'api_key' => $apiKey,
            'app_id'  => $appId,
        ];
    }
}
