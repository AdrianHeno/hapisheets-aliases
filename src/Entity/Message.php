<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\MessageRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: MessageRepository::class)]
#[ORM\Table(name: 'message')]
#[ORM\Index(columns: ['alias_id', 'received_at'], name: 'IDX_message_alias_received_at')]
class Message
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

    #[ORM\Column(length: 255)]
    private ?string $subject = null;

    #[ORM\Column(length: 255)]
    private ?string $fromAddress = null;

    #[ORM\Column(type: 'text')]
    private ?string $body = null;

    #[ORM\OneToOne(targetEntity: InboundRaw::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?InboundRaw $inboundRaw = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $previewSnippet = null;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $hasHtmlBody = false;

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

    public function getSubject(): ?string
    {
        return $this->subject;
    }

    public function setSubject(string $subject): static
    {
        $this->subject = $subject;
        return $this;
    }

    public function getFromAddress(): ?string
    {
        return $this->fromAddress;
    }

    public function setFromAddress(string $fromAddress): static
    {
        $this->fromAddress = $fromAddress;
        return $this;
    }

    public function getBody(): ?string
    {
        return $this->body;
    }

    public function setBody(string $body): static
    {
        $this->body = $body;
        return $this;
    }

    public function getInboundRaw(): ?InboundRaw
    {
        return $this->inboundRaw;
    }

    public function setInboundRaw(?InboundRaw $inboundRaw): static
    {
        $this->inboundRaw = $inboundRaw;
        return $this;
    }

    public function getPreviewSnippet(): ?string
    {
        return $this->previewSnippet;
    }

    public function setPreviewSnippet(?string $previewSnippet): static
    {
        $this->previewSnippet = $previewSnippet === null ? null : mb_substr($previewSnippet, 0, 255);
        return $this;
    }

    public function hasHtmlBody(): bool
    {
        return $this->hasHtmlBody;
    }

    public function setHasHtmlBody(bool $hasHtmlBody): static
    {
        $this->hasHtmlBody = $hasHtmlBody;
        return $this;
    }
}
