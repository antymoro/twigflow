<?php
namespace App\Context;

class RequestContext
{
    private ?string $language;
    private ?array $supportedLanguages;
    private array $globalContext = [];
    private array $ogTags = [];

    public function __construct(?string $language = null)
    {
        $this->language = $language;
        $this->supportedLanguages = $supportedLanguages ?? [];
        $this->loadDefaultOgTags();
    }

    public function getGlobalContext(): array
    {
        return $this->globalContext;
    }

    private function loadDefaultOgTags(): void
    {
        $filePath = BASE_PATH . '/application/og_tags.json';
        if (file_exists($filePath)) {
            $this->ogTags = json_decode(file_get_contents($filePath), true) ?? [];
            if (!empty($this->language) && isset($this->ogTags[$this->language])) {
                $this->ogTags = $this->ogTags[$this->language];
            }
        }
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

    public function setOgTags(array $tags = []): void
    {
        foreach ($tags as $key => $value) {
            if ($key === 'og:title' && isset($this->ogTags['og:title'])) {
                $this->ogTags['og:title'] = $value . ' - ' . $this->ogTags['og:title'];
            } else {
                $this->ogTags[$key] = $value;
            }
        }
    }

    public function getOgTags(): array
    {
        return $this->ogTags;
    }
    
}