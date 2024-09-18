<?php

declare(strict_types=1);

namespace BackgroundMessageTransport\Tests\TestEnvironment\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class Child
{
    #[ORM\Id]
    #[ORM\Column(type: 'integer', options: ['unsigned' => true])]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $response = null;

    #[ORM\ManyToOne(targetEntity: Root::class, inversedBy: 'children')]
    private Root $root;

    public function __construct(
        #[ORM\Column(type: 'string')]
        public readonly string $name,
    ) {}

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getRoot(): Root
    {
        return $this->root;
    }

    public function setRoot(Root $root): void
    {
        $this->root = $root;
    }

    public function getResponse(): ?string
    {
        return $this->response;
    }

    public function setResponse(?string $response): void
    {
        $this->response = $response;
    }
}
