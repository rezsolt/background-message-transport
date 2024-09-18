<?php

declare(strict_types=1);

namespace BackgroundMessageTransport\Tests\Unit\DeepCopy\Examples;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

class ContainsObjects
{
    private ?Scalar $object2 = null;

    private ?Scalar $object3 = null;

    private Collection $scalars;

    public function __construct(
        private readonly Scalar $object1,
    ) {
        $this->scalars = new ArrayCollection();
    }

    public function getObject1(): Scalar
    {
        return $this->object1;
    }

    public function getObject2(): ?Scalar
    {
        return $this->object2;
    }

    public function setObject2(Scalar $object2): void
    {
        $this->object2 = $object2;
    }

    public function getObject3(): ?Scalar
    {
        return $this->object3;
    }

    public function setObject3(Scalar $object3): void
    {
        $this->object3 = $object3;
    }

    /**
     * @return Collection<Scalar>
     */
    public function getScalars(): Collection
    {
        return $this->scalars;
    }

    public function addScalar(Scalar $scalar): void
    {
        $this->scalars->add($scalar);
    }

    public function removeScalar(Scalar $scalar): void
    {
        $this->scalars->removeElement($scalar);
    }
}