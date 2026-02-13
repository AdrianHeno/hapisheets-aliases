<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\AliasRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AliasRepository::class)]
#[ORM\Table(name: 'alias')]
#[ORM\Index(columns: ['user_id'], name: 'IDX_alias_user_id')]
#[ORM\UniqueConstraint(name: 'UNIQ_alias_local_part', columns: ['local_part'])]
class Alias
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'aliases')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    #[ORM\Column(length: 255, unique: true)]
    private ?string $localPart = null;

    #[ORM\Column(type: 'boolean', options: ['default' => true])]
    private bool $enabled = true;

    #[ORM\Column(type: 'datetime_immutable')]
    private ?\DateTimeImmutable $createdAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;
        return $this;
    }

    public function getLocalPart(): ?string
    {
        return $this->localPart;
    }

    public function setLocalPart(string $localPart): static
    {
        $this->localPart = $localPart;
        return $this;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function setEnabled(bool $enabled): static
    {
        $this->enabled = $enabled;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }
}
