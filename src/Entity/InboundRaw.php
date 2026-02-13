<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\InboundRawRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: InboundRawRepository::class)]
#[ORM\Table(name: 'inbound_raw')]
#[ORM\Index(columns: ['alias_id', 'received_at'], name: 'IDX_inbound_raw_alias_received_at')]
class InboundRaw
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Alias::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Alias $alias = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private ?\DateTimeImmutable $receivedAt = null;

    #[ORM\Column(type: 'text')]
    private ?string $rawMime = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getAlias(): ?Alias
    {
        return $this->alias;
    }

    public function setAlias(?Alias $alias): static
    {
        $this->alias = $alias;
        return $this;
    }

    public function getReceivedAt(): ?\DateTimeImmutable
    {
        return $this->receivedAt;
    }

    public function setReceivedAt(\DateTimeImmutable $receivedAt): static
    {
        $this->receivedAt = $receivedAt;
        return $this;
    }

    public function getRawMime(): ?string
    {
        return $this->rawMime;
    }

    public function setRawMime(string $rawMime): static
    {
        $this->rawMime = $rawMime;
        return $this;
    }
}
