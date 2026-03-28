<?php

declare(strict_types=1);

namespace AgentGateway\Service;

use Predis\Client as RedisClient;

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
     * @return list<array{role: string, content: mixed}>
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
     * Save the full messages array (including any tool_use/tool_result blocks)
     * for a conversation, capping at the configured max messages.
     *
     * @param list<array{role: string, content: mixed}> $fullMessages  The complete messages array sent to Anthropic
     * @param array<string, mixed>                      $assistantContent  The raw content array from Anthropic's response
     */
    public function saveHistory(
        string $conversationId,
        array $fullMessages,
        array $assistantContent
    ): void {
        // Append the assistant response with its full content (text + tool_use blocks)
        $fullMessages[] = ['role' => 'assistant', 'content' => $assistantContent];

        // Cap to last N messages
        if (count($fullMessages) > $this->maxMessages) {
            $fullMessages = array_slice($fullMessages, -$this->maxMessages);
        }

        $key = "conversation:{$conversationId}";
        $this->redis->set($key, json_encode($fullMessages));
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
