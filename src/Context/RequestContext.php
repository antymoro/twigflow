<?php
namespace App\Context;

class RequestContext
{
    private ?string $language;
    private ?array $supportedLanguages;

    public function __construct(?string $language = null)
    {
        $this->language = $language;
        $this->supportedLanguages = $supportedLanguages ?? [];
    }

    public function getLanguage(): ?string
    {
        return $this->language;
    }

    public function setLanguage(?string $language): void
    {
        $this->language = $language;
    }

    public function setSupportedLanguages($languages): void
    {
        $this->supportedLanguages = $languages;
    }

    public function getSupportedLanguages(): ?array
    {
        return $this->supportedLanguages;
    }
}