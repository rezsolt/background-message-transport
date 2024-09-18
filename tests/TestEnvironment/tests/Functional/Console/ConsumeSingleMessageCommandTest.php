<?php

declare(strict_types=1);

namespace BackgroundMessageTransport\Tests\Functional\Console;

use BackgroundMessageTransport\Tests\Functional\KernelTestCase;
use BackgroundMessageTransport\Tests\TestEnvironment\Message\ExampleMessage;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Stamp\BusNameStamp;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Messenger\Stamp\SentStamp;
use Symfony\Component\Messenger\Stamp\TransportMessageIdStamp;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;

class ConsumeSingleMessageCommandTest extends KernelTestCase
{
    private CommandTester $commandTester;
    private SerializerInterface $serializer;

    protected function setUp(): void
    {
        $application = new Application(self::getBootedKernel());
        $consumerCommand = $application->find('backround-message-transport:consume-single-message');
        $this->commandTester = new CommandTester($consumerCommand);
        $this->serializer = self::getServiceByClassName(SerializerInterface::class);
    }

    protected function tearDown(): void
    {
        unset(
            $this->commandTester,
            $this->serializer,
        );

        parent::tearDown();
    }


    public function test_command(): void
    {
        $message = new ExampleMessage(
            message: 'Hello World',
            sleepTime: 1,
            createdAt: new \DateTimeImmutable('2023-12-25T02:03:04+01:00'),
        );

        $envelope = new Envelope($message);
        $envelope = $envelope->with(new BusNameStamp('messenger.bus.default'))
            ->with(new SentStamp(InMemoryTransport::class, 'async'))
            ->with(new TransportMessageIdStamp(1));
        $encodedEnvelope = \base64_encode(\json_encode($this->serializer->encode($envelope), \JSON_THROW_ON_ERROR));

        $this->commandTester->execute([
            'envelope' => $encodedEnvelope,
            'fingerprint' => \hash_hmac('sha256', $encodedEnvelope, self::getContainer()->getParameter('kernel.secret')),
        ]);

        self::assertSame(0, $this->commandTester->getStatusCode());
        $output = $this->commandTester->getDisplay();
        self::assertNotEmpty($output);

        $returnedEnvelope = $this->serializer->decode(\json_decode(\base64_decode($output), true, 512, \JSON_THROW_ON_ERROR));

        $handler = $returnedEnvelope->last(HandledStamp::class);
        self::assertInstanceOf(HandledStamp::class, $handler);
        self::assertSame('BackgroundMessageTransport\Tests\TestEnvironment\MessageHandler\ExampleHandler::__invoke', $handler->getHandlerName());
        self::assertSame('createdAt: 2023-12-25T02:03:04+01:00', $handler->getResult());

        self::assertTrue(
            self::getTestLogger()->hasInfo(
                [
                    'message' => 'Hello World',
                    'context' => ['createdAt' => '2023-12-25T02:03:04+01:00'],
                ],
            ),
        );
    }
}
