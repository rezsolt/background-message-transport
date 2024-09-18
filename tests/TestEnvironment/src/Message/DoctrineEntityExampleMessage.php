<?php

declare(strict_types=1);

namespace BackgroundMessageTransport\Tests\TestEnvironment\Message;

use BackgroundMessageTransport\Tests\TestEnvironment\Entity\Root;

readonly class DoctrineEntityExampleMessage
{
    public function __construct(
        public Root $root,
    ) {}
}
