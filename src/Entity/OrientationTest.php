<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'orientation_test')]
class OrientationTest
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, name: 'user_id')]
    private ?User $user = null;

    #[ORM\Column(type: 'string', length: 36, unique: true)]
    private ?string $uuid = null;

    #[ORM\Column(type: 'string', length: 50)]
    private ?string $testType = 'welcome';

    #[ORM\Column(type: 'datetime_immutable')]
    private ?\DateTimeImmutable $startedAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $completedAt = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $duration = null;

    #[ORM\Column(type: 'string', length: 2)]
    private ?string $language = 'fr';

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $totalQuestions = 0;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $metadata = null;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $currentStep = null;

    #[ORM\Column(type: 'boolean')]
    private bool $isCompleted = false;

    #[ORM\Column(type: 'datetime_immutable')]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    public function __construct()
    {
        $this->startedAt = new \DateTimeImmutable();
        $this->createdAt = new \DateTimeImmutable();
        $this->uuid = $this->generateUuid();
        $this->testType = 'welcome';
        $this->language = 'fr';
        $this->totalQuestions = 0;
        $this->isCompleted = false;
    }

    private function generateUuid(): string
    {
        // Format UUID hexadécimal (0x...)
        return '0x' . bin2hex(random_bytes(16));
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): self
    {
        $this->user = $user;
        return $this;
    }

    public function getUuid(): ?string
    {
        return $this->uuid;
    }

    public function setUuid(string $uuid): self
    {
        $this->uuid = $uuid;
        return $this;
    }

    public function getTestType(): ?string
    {
        return $this->testType;
    }

    public function setTestType(string $testType): self
    {
        $this->testType = $testType;
        return $this;
    }

    public function getStartedAt(): ?\DateTimeImmutable
    {
        return $this->startedAt;
    }

    public function setStartedAt(\DateTimeImmutable $startedAt): self
    {
        $this->startedAt = $startedAt;
        return $this;
    }

    public function getCompletedAt(): ?\DateTimeImmutable
    {
        return $this->completedAt;
    }

    public function setCompletedAt(?\DateTimeImmutable $completedAt): self
    {
        $this->completedAt = $completedAt;
        if ($completedAt && $this->startedAt) {
            $this->duration = $completedAt->getTimestamp() - $this->startedAt->getTimestamp();
        }
        return $this;
    }

    public function getDuration(): ?int
    {
        return $this->duration;
    }

    public function setDuration(?int $duration): self
    {
        $this->duration = $duration;
        return $this;
    }

    public function getLanguage(): ?string
    {
        return $this->language;
    }

    public function setLanguage(string $language): self
    {
        $this->language = $language;
        return $this;
    }

    public function getTotalQuestions(): ?int
    {
        return $this->totalQuestions;
    }

    public function setTotalQuestions(?int $totalQuestions): self
    {
        $this->totalQuestions = $totalQuestions;
        return $this;
    }

    public function getMetadata(): ?array
    {
        return $this->metadata;
    }

    public function setMetadata(?array $metadata): self
    {
        $this->metadata = $metadata;
        $this->updatedAt = new \DateTimeImmutable();
        return $this;
    }

    public function getCurrentStep(): ?array
    {
        return $this->currentStep;
    }

    public function setCurrentStep(?array $currentStep): self
    {
        $this->currentStep = $currentStep;
        $this->updatedAt = new \DateTimeImmutable();
        return $this;
    }

    public function isCompleted(): bool
    {
        return $this->isCompleted;
    }

    public function setIsCompleted(bool $isCompleted): self
    {
        $this->isCompleted = $isCompleted;
        if ($isCompleted && !$this->completedAt) {
            $this->completedAt = new \DateTimeImmutable();
            if ($this->startedAt) {
                $this->duration = $this->completedAt->getTimestamp() - $this->startedAt->getTimestamp();
            }
        }
        $this->updatedAt = new \DateTimeImmutable();
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): self
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTimeImmutable $updatedAt): self
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }
}
