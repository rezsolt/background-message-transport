<?php

declare(strict_types=1);

namespace BackgroundMessageTransport\Tests\TestEnvironment\MessageHandler;

use BackgroundMessageTransport\Tests\TestEnvironment\Message\ExampleMessage;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(handles: ExampleMessage::class)]
readonly class ExampleHandler
{
    public function __construct(
        private LoggerInterface $logger,
    ) {}

    public function __invoke(ExampleMessage $message): string {
        $this->logger->info(
            $message->message,
            [
                'createdAt' => $message->createdAt->format(\DateTimeInterface::ATOM),
            ]
        );

        if ($message->sleepTime > 0) {
            \sleep($message->sleepTime);
        }

        $message->setResponse($message->createdAt->modify('+2 minutes')->format(\DateTimeInterface::ATOM));

        return 'createdAt: '. $message->createdAt->format(\DateTimeInterface::ATOM);
    }
}