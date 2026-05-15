<?php

declare(strict_types=1);

namespace App\Http\Middleware\Idempotency;

class IdempotencyRecord
{
    private const string STATE_IN_PROGRESS = 'in_progress';

    private const string STATE_COMPLETED = 'completed';

    /**
     * @param  array<int|string, mixed>|null  $body
     */
    public function __construct(
        public readonly string $state,
        public readonly string $bodyHash,
        public readonly ?int $status = null,
        public readonly ?array $body = null,
    ) {}

    public static function inProgress(string $bodyHash): self
    {
        return new self(self::STATE_IN_PROGRESS, $bodyHash);
    }

    /**
     * @param  array<int|string, mixed>|null  $body
     */
    public static function completed(string $bodyHash, int $status, ?array $body): self
    {
        return new self(self::STATE_COMPLETED, $bodyHash, $status, $body);
    }

    /**
     * @param  array<string, mixed>  $raw
     */
    public static function fromArray(array $raw): self
    {
        /** @var array<int|string, mixed>|null $body */
        $body = $raw['body'] ?? null;

        return new self(
            state: (string) ($raw['state'] ?? self::STATE_IN_PROGRESS),
            bodyHash: (string) ($raw['body_hash'] ?? ''),
            status: isset($raw['status']) ? (int) $raw['status'] : null,
            body: is_array($body) ? $body : null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'state' => $this->state,
            'body_hash' => $this->bodyHash,
            'status' => $this->status,
            'body' => $this->body,
        ];
    }

    public function matches(string $bodyHash): bool
    {
        return hash_equals($this->bodyHash, $bodyHash);
    }

    public function isCompleted(): bool
    {
        return $this->state === self::STATE_COMPLETED;
    }
}
