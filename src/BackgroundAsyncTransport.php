<?php

declare(strict_types=1);

namespace BackgroundMessageTransport;

use BackgroundMessageTransport\DeepCopy\DeepValueCopy;
use BackgroundMessageTransport\Stamp\HandledCallbackStamp;
use BackgroundMessageTransport\Stamp\ProcessStamp;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Event\WorkerMessageHandledEvent;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\Exception\InvalidArgumentException;
use Symfony\Component\Messenger\Stamp\ErrorDetailsStamp;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;
use Symfony\Component\Messenger\Transport\TransportInterface;
use Symfony\Component\Process\Process;

final readonly class BackgroundAsyncTransport implements TransportInterface
{
    public function __construct(
        #[\SensitiveParameter]
        private string $appSecret,
        private EventDispatcherInterface $eventDispatcher,
        private DeepValueCopy $deepValueCopy,
        private SerializerInterface $serializer,
    ) {}

    public function get(): iterable
    {
        throw new InvalidArgumentException('You cannot receive messages from the Messenger SyncTransport.');
    }

    public function ack(Envelope $envelope): void
    {
        throw new InvalidArgumentException('You cannot call ack() on the Messenger SyncTransport.');
    }

    public function reject(Envelope $envelope): void
    {
        throw new InvalidArgumentException('You cannot call reject() on the Messenger SyncTransport.');
    }

    public function send(Envelope $envelope): Envelope
    {
        $handledCallbackStamp = $envelope->last(HandledCallbackStamp::class);
        $envelope = $envelope->withoutAll(HandledCallbackStamp::class);
        $encodedEnvelope = $this->encodeEnvelope($envelope);
        $fingerPrint = \hash_hmac('sha256', $encodedEnvelope, $this->appSecret);
        $process = new Process([
            'php',
            'bin/console',
            'backround-message-transport:consume-single-message',
            $encodedEnvelope,
            $fingerPrint,
        ]);

        $process->start(function (string $type, string $buffer) use ($process, $envelope, $handledCallbackStamp) {
            if (Process::ERR === $type) {
                $process->stop();
                throw new \RuntimeException($buffer);
            }

            $workersEnvelope = $this->decodeEnvelope($buffer);

            $errorStamp = $workersEnvelope->last(ErrorDetailsStamp::class);
            if ($errorStamp !== null) {
                $process->stop();

                throw new HandlerFailedException(
                    $workersEnvelope,
                    [new \RuntimeException($errorStamp->getExceptionMessage(), $errorStamp->getExceptionCode())]
                );
            }

            $envelope = $this->copyChangesToEnvelope($workersEnvelope, $envelope);
            $this->eventDispatcher->dispatch(new WorkerMessageHandledEvent($envelope, 'background-async'));
            $handledCallbackStamp?->callback->call($envelope, $envelope);
        });

        return $envelope->with(new ProcessStamp($process));
    }

    private function encodeEnvelope(Envelope $envelope): string
    {
        $serializedEnvelope = $this->serializer->encode($envelope);

        return \base64_encode(\json_encode($serializedEnvelope, JSON_THROW_ON_ERROR));
    }

    private function decodeEnvelope(string $encodedEnvelope): Envelope
    {
        $decodedEnvelope = \base64_decode($encodedEnvelope);
        if ($decodedEnvelope === false) {
            throw new \RuntimeException('Unable to decode the envelope');
        }

        $envelopeData = \json_decode($decodedEnvelope, true, 512, JSON_THROW_ON_ERROR);
        $envelope = $this->serializer->decode($envelopeData);
        if (!$envelope instanceof Envelope) {
            throw new \RuntimeException('Worker must return an Envelope object');
        }

        return $envelope;
    }

    private function copyChangesToEnvelope(
        Envelope $changedEnvelope,
        Envelope $originalEnvelope,
    ): Envelope {
        $originalMessage = $originalEnvelope->getMessage();
        $changedMessage = $changedEnvelope->getMessage();
        $this->deepValueCopy->copyValues($changedMessage, $originalMessage);

        $originalStamps = $originalEnvelope->all();
        $changedStamps = $changedEnvelope->all();

        foreach ($changedStamps as $name => $changedStampList) {
            if (isset($originalStamps[$name])) {
                $originalEnvelope = $originalEnvelope->with(...\array_slice($changedStampList, \count($originalStamps[$name])));
                continue;
            }

            $originalEnvelope = $originalEnvelope->with(...$changedStampList);
        }

        return $originalEnvelope;
    }
}
