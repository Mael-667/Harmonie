<?php

namespace App\Entity;

use App\Repository\ThemeRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ThemeRepository::class)]
class Theme
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 70)]
    private ?string $name = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $css_rules = null;

    #[ORM\Column]
    private ?int $usage_count = null;

    #[ORM\ManyToOne(inversedBy: 'themes')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getCssRules(): ?string
    {
        return $this->css_rules;
    }

    public function setCssRules(string $css_rules): static
    {
        $this->css_rules = $css_rules;

        return $this;
    }

    public function getUsageCount(): ?int
    {
        return $this->usage_count;
    }

    public function setUsageCount(int $usage_count): static
    {
        $this->usage_count = $usage_count;

        return $this;
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
}
