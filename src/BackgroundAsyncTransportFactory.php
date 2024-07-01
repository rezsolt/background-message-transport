<?php

declare(strict_types=1);

namespace BackgroundMessageTransport;

use BackgroundMessageTransport\DeepCopy\DeepValueCopy;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;
use Symfony\Component\Messenger\Transport\TransportFactoryInterface;
use Symfony\Component\Messenger\Transport\TransportInterface;

final readonly class BackgroundAsyncTransportFactory implements TransportFactoryInterface
{
    public function __construct(
        #[\SensitiveParameter]
        private string $appSecret,
        private EventDispatcherInterface $eventDispatcher,
        private DeepValueCopy $deepValueCopy,
    ) {}

    public function createTransport(
        #[\SensitiveParameter]
        string $dsn,
        array $options,
        SerializerInterface $serializer
    ): TransportInterface {
        return new BackgroundAsyncTransport(
            appSecret: $this->appSecret,
            eventDispatcher: $this->eventDispatcher,
            deepValueCopy: $this->deepValueCopy,
            serializer: $serializer,
        );
    }

    public function supports(string $dsn, array $options): bool
    {
        return str_starts_with($dsn, 'background-async://');
    }
}
