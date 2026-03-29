# Ontraport Agent Gateway

A lightweight PHP API endpoint that connects Ontraport users to Claude via natural language. Users send plain-English requests, and the gateway orchestrates Claude + the Ontraport MCP server to execute actions and return responses.

## Architecture

```
User → Agent Gateway → Anthropic Messages API → Ontraport MCP Server → Ontraport API
```

The gateway handles authentication, rate limiting, conversation history, and observability. Claude discovers and calls MCP tools (create_contact, get_pipeline, etc.) autonomously.

## Endpoint

```
POST /api/v1/agent
```

### Headers

| Header      | Description                |
|-------------|----------------------------|
| `Api-Key`   | Ontraport API key          |
| `Api-Appid` | Ontraport App ID           |

### Request Body

```json
{
  "message": "How many contacts were added this week?",
  "conversation_id": "optional-for-follow-ups",
  "context": { "current_page": "contacts" },
  "model": "claude-sonnet-4-20250514",
  "stream": false
}
```

### Response

```json
{
  "response": "You had 47 new contacts added this week...",
  "conversation_id": "abc123",
  "actions_taken": [
    { "tool_name": "search_contacts", "summary": "Called search_contacts (date_range=this_week)" }
  ],
  "usage": { "input_tokens": 1250, "output_tokens": 340 }
}
```

## Setup

### Prerequisites

- PHP 7.4+
- Composer
- Redis (for conversation history)

### Installation

```bash
cp .env.example .env
# Edit .env with your Anthropic API key, MCP server URL, and Redis URL
composer install
```

### Run Locally

```bash
php -S 0.0.0.0:3000 -t public
```

### Docker

```bash
docker build -t agent-gateway .
docker run -p 3000:80 --env-file .env agent-gateway
```

## Configuration

See `.env.example` for all configuration options including rate limits, conversation TTL, and default model.

## Error Codes

| Status | Meaning         |
|--------|-----------------|
| 401    | Missing/invalid API credentials |
| 400    | Missing message or invalid request |
| 429    | Rate limit exceeded |
| 502    | Anthropic API or MCP server unreachable |
| 500    | Internal gateway error |
