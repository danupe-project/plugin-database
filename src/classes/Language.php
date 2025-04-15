<?php

namespace Danupe\Plugin\Database\Classes;

class Language
{
    private string $locale;
    private array $translations = [];

    public function __construct(string $locale = 'en')
    {
        $this->locale = $locale;
        $this->loadTranslations();
    }

    private function loadTranslations(): void
    {
        $this->translations = danupe()->config()->getAllByKey("language") ?? [];
    }

    public function get(string $key, array $replacements = []): string|false|array
    {

        $explodedKey = explode('.', $key);
        $getFirstKeyElement = danupe()->data()->get($explodedKey, 0);
        $translationsElement = danupe()->data()->get($this->translations, $getFirstKeyElement . "." . $this->locale);
        $keyWithoutFirstElement = array_pop($explodedKey);
        $translation = danupe()->data()->get($translationsElement, $keyWithoutFirstElement);

        foreach ($replacements as $placeholder => $value) {
            $translation = str_replace(":$placeholder", $value, $translation);
        }

        return $translation;
    }

    public function getAll(): array
    {
        return $this->translations;
    }

    public function getLocale(): string
    {
        return $this->locale;
    }

    public function setLocale(string $locale): void
    {
        $this->locale = $locale;
    }

    public function getAllAvailableBackendLanguageKeys(bool $groupAsKey = false): array
    {

        $keys = [];

        foreach ($this->translations as $key => $group) {

            foreach ($group as $subKey => $value) {
                if ($groupAsKey) {
                    $keys[$subKey] = $subKey;
                } else {
                    $keys[] = $subKey;
                }
            }
        }
        return $keys;
    }
    public function getAllAvailableFrontendLanguageKeys(bool $groupAsKey = false): array
    {

        $keysFromEnv = explode(',', danupe()->env()->get('DANUPE_LANGUAGE_FRONTEND'));
        if ($groupAsKey) {
            foreach ($keysFromEnv as $key) {
                $keys[$key] = $key;
            }
        }else{
            $keys = $keysFromEnv;
        }

        return $keys;
    }
}
