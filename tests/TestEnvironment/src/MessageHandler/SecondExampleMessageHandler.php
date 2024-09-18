<?php

declare(strict_types=1);

namespace BackgroundMessageTransport\Tests\TestEnvironment\MessageHandler;

use BackgroundMessageTransport\Tests\TestEnvironment\Message\SecondExampleMessage;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(handles: SecondExampleMessage::class)]
readonly class SecondExampleMessageHandler
{
    public function __construct(
        private LoggerInterface $logger,
    ) {}

    public function __invoke(SecondExampleMessage $message)
    {
        $this->logger->info(
            $message->message,
            [
                'createdAt' => $message->createdAt->format(\DateTimeInterface::ATOM),
            ]
        );

        if ($message->sleepTime > 0) {
            \sleep($message->sleepTime);
        }

        $message->setResponse([
            'responseTime' => $message->createdAt->modify('+2 minutes'),
            'foo' => 'bar',
            'baz' => [
                'sub1.1key' => 'Sub 1.1 value',
                'sub1.2key' => 'Sub 1.2 value',
            ],
        ]);

        return 'Second createdAt: '. $message->createdAt->format(\DateTimeInterface::ATOM);
    }
}