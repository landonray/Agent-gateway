<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;
use AgentGateway\Middleware\AuthMiddleware;
use AgentGateway\Middleware\RateLimitMiddleware;
use AgentGateway\Middleware\ValidationMiddleware;
use AgentGateway\Controller\AgentController;
use AgentGateway\Service\AnthropicService;
use AgentGateway\Service\ConversationService;

// Load environment variables
$envFile = __DIR__ . '/../.env';
if (file_exists($envFile)) {
    $dotenv = Dotenv::createImmutable(__DIR__ . '/..');
    $dotenv->load();
}

// Load config
$config = require __DIR__ . '/../config/config.php';

// Set JSON content type for all responses
header('Content-Type: application/json');

// Simple router
$method = $_SERVER['REQUEST_METHOD'];
$uri    = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Health check endpoint
if ($uri === '/health' && $method === 'GET') {
    echo json_encode(['status' => 'ok']);
    exit;
}

// Main agent endpoint
if ($uri === '/api/v1/agent' && $method === 'POST') {
    try {
        // 1. Authenticate
        $credentials = AuthMiddleware::handle();
        if ($credentials === null) {
            exit;
        }

        // 2. Rate limit
        $allowed = RateLimitMiddleware::handle(
            $credentials['app_id'],
            $config['rate_limit_per_minute'],
            $config['rate_limit_per_day']
        );
        if (!$allowed) {
            exit;
        }

        // 3. Validate request body
        $body = ValidationMiddleware::handle();
        if ($body === null) {
            exit;
        }

        // 4. Reject streaming requests until implemented (reserved for future)
        if (!empty($body['stream'])) {
            http_response_code(501);
            echo json_encode([
                'error'  => 'Streaming is not yet supported. Omit the stream field or set it to false.',
                'status' => 501,
            ]);
            exit;
        }

        // 5. Build services and handle request
        $anthropic = new AnthropicService(
            $config['anthropic_api_key'],
            $config['mcp_server_url'],
            $config['default_model']
        );

        $conversation = new ConversationService(
            $config['redis_url'],
            $config['conversation_ttl'],
            $config['conversation_max_messages']
        );

        $controller = new AgentController($anthropic, $conversation);
        $controller->handle($body, $credentials);
    } catch (\Throwable $e) {
        \AgentGateway\Logger::get()->error('Unhandled exception', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);
        http_response_code(500);
        echo json_encode([
            'error'  => 'Internal server error',
            'status' => 500,
        ]);
    }
    exit;
}

// 404 for everything else
http_response_code(404);
echo json_encode([
    'error'  => 'Not found',
    'status' => 404,
]);
