<?php

declare(strict_types=1);

namespace BackgroundMessageTransport\Tests\TestEnvironment\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class Root
{
    #[ORM\Id]
    #[ORM\Column(type: 'integer', options: ['unsigned' => true])]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $response = null;

    #[ORM\OneToOne(targetEntity: Child::class, cascade: ['persist'])]
    private ?Child $child1 = null;

    #[ORM\OneToOne(targetEntity: Child::class, cascade: ['persist'])]
    private ?Child $child2 = null;

    /**
     * @var Collection<Child>
     */
    #[ORM\OneToMany(targetEntity: Child::class, mappedBy: 'root', cascade: ['persist'])]
    private Collection $children;

    public function __construct(
        #[ORM\Column(type: 'string')]
        public readonly string $name,
    ) {
        $this->children = new ArrayCollection();
    }

    public function addChild(Child $child): void
    {
        if ($this->children->contains($child)) {
            return;
        }

        $child->setRoot($this);
        $this->children->add($child);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * @return Collection<Child>
     */
    public function getChildren(): Collection
    {
        return $this->children;
    }

    public function getResponse(): ?string
    {
        return $this->response;
    }

    public function setResponse(?string $response): void
    {
        $this->response = $response;
    }

    public function getChild1(): ?Child
    {
        return $this->child1;
    }

    public function setChild1(?Child $child1): void
    {
        $this->child1 = $child1;
    }

    public function getChild2(): ?Child
    {
        return $this->child2;
    }

    public function setChild2(?Child $child2): void
    {
        $this->child2 = $child2;
    }
}