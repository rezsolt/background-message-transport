<?php

declare(strict_types=1);

namespace BackgroundMessageTransport\Tests\Functional\MessageHandler;

use BackgroundMessageTransport\Stamp\HandledCallbackStamp;
use BackgroundMessageTransport\Stamp\ProcessStamp;
use BackgroundMessageTransport\Tests\Functional\DoctrineSupport;
use BackgroundMessageTransport\Tests\Functional\KernelTestCase;
use BackgroundMessageTransport\Tests\TestEnvironment\Entity\Child;
use BackgroundMessageTransport\Tests\TestEnvironment\Entity\Root;
use BackgroundMessageTransport\Tests\TestEnvironment\Message\DoctrineEntityExampleMessage;
use BackgroundMessageTransport\Tests\TestEnvironment\Message\ExampleMessage;
use BackgroundMessageTransport\Tests\TestEnvironment\Message\SecondExampleMessage;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

class ExampleHandlerFunctionalTest extends KernelTestCase
{
    use DoctrineSupport;

    private MessageBusInterface $mesageBus;

    protected function setUp(): void
    {
        $this->mesageBus = self::getServiceByClassName(MessageBusInterface::class);
    }

    protected function tearDown(): void
    {
        unset(
            $this->mesageBus,
        );

        parent::tearDown();
    }

    public function test_example_message_handled_with_waiting_for_the_process(): void
    {
        $message = new ExampleMessage(
            message: 'Hello World',
            sleepTime: 2,
            createdAt: new \DateTimeImmutable('2023-12-25T02:03:04+01:00'),
        );
        self::assertNull($message->getResponse());

        $envelope = $this->mesageBus->dispatch($message);

        $message = $envelope->getMessage();
        self::assertInstanceOf(ExampleMessage::class, $message);
        self::assertSame('Hello World', $message->message);
        self::assertEquals(new \DateTimeImmutable('2023-12-25T02:03:04+01:00'), $message->createdAt);

        // Asynchronous message not yet handled
        self::assertNull($message->getResponse());
        self::assertNull($envelope->last(HandledStamp::class));

        // Wait for the process to finish
        $processStamp = $envelope->last(ProcessStamp::class);
        self::assertInstanceOf(ProcessStamp::class, $processStamp);
        $processStamp->process->wait();

        self::assertSame($message, $envelope->getMessage(), 'New envelope should contains the same message object');
        self::assertSame('2023-12-25T02:05:04+01:00', $message->getResponse(), 'Message should be handled and response copied to the original message object');

        self::assertTrue(
            self::getTestLogger()->hasInfo(
                [
                    'message' => 'ExampleMessage handled',
                    'context' => ['response' => '2023-12-25T02:05:04+01:00'],
                ],
            ),
        );
    }

    public function test_example_message_handled_with_callback_when_finishing(): void
    {
        $message = new SecondExampleMessage(
            message: 'Hello World',
            sleepTime: 2,
            createdAt: new \DateTimeImmutable('2023-12-25T02:03:04+01:00'),
        );
        self::assertSame([], $message->getResponse());

        $callbackCalled = false;
        $envelopeInTheCallback = null;
        $handledCallbackStamp = new HandledCallbackStamp(function (Envelope $envelope) use (&$callbackCalled, &$envelopeInTheCallback): void {
            $callbackCalled = true;
            $envelopeInTheCallback = $envelope;
        });

        $envelope = $this->mesageBus->dispatch($message, [$handledCallbackStamp]);

        $message = $envelope->getMessage();
        self::assertInstanceOf(SecondExampleMessage::class, $message);
        self::assertSame('Hello World', $message->message);
        self::assertEquals(new \DateTimeImmutable('2023-12-25T02:03:04+01:00'), $message->createdAt);

        // Asynchronous message not yet handled
        self::assertSame([], $message->getResponse());
        self::assertNull($envelope->last(HandledStamp::class));

        // Wait for the process to finish
        $processStamp = $envelope->last(ProcessStamp::class);
        self::assertInstanceOf(ProcessStamp::class, $processStamp);
        self::assertTrue($processStamp->process->isRunning());
        $endTime = (new \DateTimeImmutable())->modify('+4 seconds');
        while (!$callbackCalled) {
            \usleep(100);
            self::assertTrue(new \DateTimeImmutable() < $endTime, 'Callback should be called within 4 seconds');
            self::assertTrue($processStamp->process->isRunning(), 'Process should be still running');
        }

        self::assertSame($message, $envelopeInTheCallback->getMessage(), 'Envelope in the callback should contains the same message object');
        self::assertSame($message, $envelope->getMessage(), 'New envelope should contains the same message object');
        self::assertEquals(
            [
                'responseTime' => new \DateTimeImmutable('2023-12-25T02:05:04+01:00'),
                'foo' => 'bar',
                'baz' => [
                    'sub1.1key' => 'Sub 1.1 value',
                    'sub1.2key' => 'Sub 1.2 value',
                ],
            ],
            $message->getResponse(),
            'Message should be handled and response copied to the original message object',
        );

        self::assertFalse(self::getTestLogger()->hasInfoRecords());
    }

    public function test_worker_does_not_block_the_main_process(): void
    {
        $message1 = new ExampleMessage(
            message: 'First message',
            sleepTime: 3,
            createdAt: new \DateTimeImmutable('2023-12-25T02:03:04+01:00'),
        );
        $message2 = new ExampleMessage(
            message: 'Second message',
            sleepTime: 1,
            createdAt: new \DateTimeImmutable('2023-12-25T02:03:04+01:00'),
        );
        $message3 = new ExampleMessage(
            message: 'Third message',
            sleepTime: 2,
            createdAt: new \DateTimeImmutable('2023-12-25T02:03:04+01:00'),
        );

        $maximumExecutionTime = (new \DateTimeImmutable())->modify('+4 seconds');

        $envelope1 = $this->mesageBus->dispatch($message1);
        $envelope2 = $this->mesageBus->dispatch($message2);
        $envelope3 = $this->mesageBus->dispatch($message3);

        $process1 = $envelope1->last(ProcessStamp::class)?->process;
        $process2 = $envelope2->last(ProcessStamp::class)?->process;
        $process3 = $envelope3->last(ProcessStamp::class)?->process;

        self::assertNotNull($process1);
        self::assertNotNull($process2);
        self::assertNotNull($process3);

        $waitCycle = 0;
        $finishers = [];
        while ($process1->isRunning() || $process2->isRunning() || $process3->isRunning()) {
            \usleep(100);
            $waitCycle++;

            if (!$process1->isRunning() && !\in_array(1, $finishers, true)) {
                $finishers[] = 1;
            }

            if (!$process2->isRunning() && !\in_array(2, $finishers, true)) {
                $finishers[] = 2;
            }

            if (!$process3->isRunning() && !\in_array(3, $finishers, true)) {
                $finishers[] = 3;
            }
        }

        self::assertLessThanOrEqual($maximumExecutionTime->getTimestamp(), (new \DateTimeImmutable())->getTimestamp());
        self::assertGreaterThan(20, $waitCycle, 'It must have time to run the main process before the worker finishes');
        self::assertSame([2, 3, 1], $finishers, 'Workers should finish in correct order since the sleep times');
    }

    public function test_doctrine_entity_changes_applying_on_the_main_process(): void
    {
        self::createDataBase();

        $entityManager = $this->getServiceByClassName(EntityManagerInterface::class);
        $rootEntity = new Root('First root');
        $rootEntity->addChild(new Child('Original'));
        $rootEntity->setChild1(new Child('Original 1'));
        $entityManager->persist($rootEntity, $rootEntity->getChildren()->first());
        self::assertTrue($entityManager->contains($rootEntity));

        $message = new DoctrineEntityExampleMessage($rootEntity);

        $envelope = $this->mesageBus->dispatch($message);
        $processStamp = $envelope->last(ProcessStamp::class);
        self::assertInstanceOf(ProcessStamp::class, $processStamp);
        $processStamp->process->wait();

        $entityManager->persist($rootEntity);

        self::assertTrue($entityManager->contains($rootEntity));
        $entityManager->flush();
        $id = $rootEntity->getId();
        $entityManager->clear();

        $root1test = $entityManager->find(Root::class, $id);

        self::assertSame('First root', $root1test->name);
        self::assertSame('Response from the worker', $root1test->getResponse());
        self::assertSame('Original 1', $root1test->getChild1()?->name);
        self::assertSame('Child 1 response from the worker', $root1test->getChild1()?->getResponse());
        self::assertSame('child 2 from the worker', $root1test->getChild2()?->name);
        self::assertCount(4, $root1test->getChildren());
        self::assertSame('Child response from the worker', $root1test->getChildren()->first()?->getResponse());
        self::assertSame('Original', $root1test->getChildren()->first()?->name);
        self::assertSame('child 1', $root1test->getChildren()->get(1)?->name);
        self::assertSame('child 2', $root1test->getChildren()->get(2)?->name);
        self::assertSame('child 3', $root1test->getChildren()->get(3)?->name);
    }
}
