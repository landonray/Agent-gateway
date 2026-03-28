<?php

declare(strict_types=1);

namespace AgentGateway\Service;

use Predis\Client as RedisClient;
use AgentGateway\Logger;

class ConversationService
{
    private RedisClient $redis;
    private int $ttl;
    private int $maxMessages;

    public function __construct(string $redisUrl, int $ttl = 86400, int $maxMessages = 20)
    {
        $this->redis       = new RedisClient($redisUrl);
        $this->ttl         = $ttl;
        $this->maxMessages = $maxMessages;
    }

    /**
     * Retrieve conversation history for a given conversation ID.
     *
     * @return list<array{role: string, content: string}>
     */
    public function getHistory(string $conversationId): array
    {
        $data = $this->redis->get("conversation:{$conversationId}");
        if ($data === null) {
            return [];
        }

        $decoded = json_decode($data, true);
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Append a user message and assistant response to conversation history,
     * capping at the configured max messages.
     *
     * @param list<array{role: string, content: string}> $history
     */
    public function saveHistory(
        string $conversationId,
        array $history,
        string $userMessage,
        string $assistantResponse
    ): void {
        $history[] = ['role' => 'user', 'content' => $userMessage];
        $history[] = ['role' => 'assistant', 'content' => $assistantResponse];

        // Cap to last N messages
        $maxEntries = $this->maxMessages * 2; // each turn = user + assistant
        if (count($history) > $maxEntries) {
            $history = array_slice($history, -$maxEntries);
        }

        $key = "conversation:{$conversationId}";
        $this->redis->set($key, json_encode($history));
        $this->redis->expire($key, $this->ttl);
    }

    /**
     * Generate a new unique conversation ID.
     */
    public static function generateId(): string
    {
        return bin2hex(random_bytes(16));
    }
}
