<?php

declare(strict_types=1);

namespace BackgroundMessageTransport\Tests\Unit\DeepCopy\Examples;

class ContainsArrays
{
    public function __construct(
        private ?array $array1,
        private ?array $array2,
        private ?array $array3,
    ) {}

    public function getArray1(): ?array
    {
        return $this->array1;
    }

    public function setArray1(?array $array1): void
    {
        $this->array1 = $array1;
    }

    public function getArray2(): ?array
    {
        return $this->array2;
    }

    public function setArray2(?array $array2): void
    {
        $this->array2 = $array2;
    }

    public function getArray3(): ?array
    {
        return $this->array3;
    }

    public function setArray3(?array $array3): void
    {
        $this->array3 = $array3;
    }
}
