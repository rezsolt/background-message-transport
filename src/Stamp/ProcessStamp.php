<?php

declare(strict_types=1);

namespace BackgroundMessageTransport\Stamp;

use Symfony\Component\Messenger\Stamp\StampInterface;
use Symfony\Component\Process\Process;

final readonly class ProcessStamp implements StampInterface
{
    public function __construct(
        public Process $process,
    ) {}
}