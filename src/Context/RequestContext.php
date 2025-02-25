<?php
namespace App\Context;

class RequestContext
{
    private ?string $language;

    public function __construct(?string $language = null)
    {
        $this->language = $language;
    }

    public function getLanguage(): ?string
    {
        return $this->language;
    }

    public function setLanguage(?string $language): void
    {
        $this->language = $language;
    }
}