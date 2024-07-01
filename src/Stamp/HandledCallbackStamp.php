<?php

declare(strict_types=1);

namespace BackgroundMessageTransport\Stamp;

use Symfony\Component\Messenger\Stamp\StampInterface;

final readonly class HandledCallbackStamp implements StampInterface
{
    public function __construct(
        public \Closure $callback,
    ) {}
}
