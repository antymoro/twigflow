<?php
namespace App\Context;

class RequestContext
{
    private ?string $language;
    private ?array $supportedLanguages;
    private array $globalContext = [];


    public function __construct(?string $language = null)
    {
        $this->language = $language;
        $this->supportedLanguages = $supportedLanguages ?? [];
    }

    public function getGlobalContext(): array
    {
        return $this->globalContext;
    }

    public function moveToGlobalContext(array $module, string $key, bool $asSubarray): void
    {
        if (!isset($this->globalContext[$key])) {
            $this->globalContext[$key] = $asSubarray ? [] : $module;
        }

        if ($asSubarray) {
            // append the module as a new item in the array
            $this->globalContext[$key][] = $module;
        } else {
            // merge the module into the existing key
            $this->globalContext[$key] = array_merge($this->globalContext[$key], $module);
        }
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