<?php

declare(strict_types=1);

namespace BackgroundMessageTransport\Tests\TestEnvironment\MessageHandler;

use BackgroundMessageTransport\Tests\TestEnvironment\Entity\Child;
use BackgroundMessageTransport\Tests\TestEnvironment\Message\DoctrineEntityExampleMessage;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(handles: DoctrineEntityExampleMessage::class)]
class DoctrineEntityExampleMessageHandler
{
    public function __invoke(DoctrineEntityExampleMessage $message): void
    {
        $message->root->setResponse('Response from the worker');
        $message->root->getChild1()->setResponse('Child 1 response from the worker');
        $message->root->setChild2(new Child('child 2 from the worker'));
        $message->root->getChildren()->first()?->setResponse('Child response from the worker');
        $message->root->addChild(new Child('child 1'));
        $message->root->addChild(new Child('child 2'));
        $message->root->addChild(new Child('child 3'));
    }
}