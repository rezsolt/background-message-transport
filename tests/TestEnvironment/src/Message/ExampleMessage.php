<?php

declare(strict_types=1);

namespace BackgroundMessageTransport\Tests\TestEnvironment\Message;

class ExampleMessage
{
    private ?string $response = null;

    public function __construct(
        public readonly string $message,
        public readonly int $sleepTime,
        public readonly \DateTimeInterface $createdAt,
    ) {}

    public function setResponse(string $response): void
    {
        $this->response = $response;
    }

    public function getResponse(): ?string
    {
        return $this->response;
    }
}
