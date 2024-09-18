<?php

declare(strict_types=1);

namespace BackgroundMessageTransport\Tests\TestEnvironment\EventSubscriber;

use BackgroundMessageTransport\Tests\TestEnvironment\Message\ExampleMessage;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Messenger\Event\WorkerMessageHandledEvent;

#[AsEventListener(event: WorkerMessageHandledEvent::class)]
readonly class ExampleMessageHandled
{
    public function __construct(
        private LoggerInterface $logger,
    ) {}

    public function __invoke(WorkerMessageHandledEvent $event): void
    {
        $message = $event->getEnvelope()->getMessage();
        if (!$message instanceof ExampleMessage) {
            return;
        }

        // Do something with the message

        $this->logger->info(
            'ExampleMessage handled',
            [
                'response' => $message->getResponse(),
            ]
        );
    }
}