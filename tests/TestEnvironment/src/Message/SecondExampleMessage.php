<?php

declare(strict_types=1);

namespace BackgroundMessageTransport\Tests\TestEnvironment\Message;

class SecondExampleMessage
{
    private array $response = [];

    public function __construct(
        public readonly string $message,
        public readonly int $sleepTime,
        public readonly \DateTimeInterface $createdAt,
    ) {}

    public function setResponse(array $response): void
    {
        $this->response = $response;
    }

    public function getResponse(): array
    {
        return $this->response;
    }
}
