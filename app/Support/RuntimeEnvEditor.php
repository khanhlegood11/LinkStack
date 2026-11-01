<?php

namespace App\Support;

use GeoSot\EnvEditor\EnvEditor;

class RuntimeEnvEditor extends EnvEditor
{
    public function keyExists(string $key): bool
    {
        if ($this->hasEnvFile()) {
            return parent::keyExists($key);
        }

        return RuntimeSettings::isEditable($key)
            || array_key_exists($key, $_ENV)
            || array_key_exists($key, $_SERVER)
            || getenv($key) !== false;
    }

    public function getKey(string $key, mixed $default = null): float|bool|int|string|null
    {
        if ($this->hasEnvFile()) {
            return parent::getKey($key, $default);
        }

        return RuntimeSettings::get($key, $default);
    }

    /**
     * @param array<string, int|string> $options
     */
    public function addKey(string $key, mixed $value, array $options = []): bool
    {
        if ($this->hasEnvFile()) {
            return parent::addKey($key, $value, $options);
        }

        return RuntimeSettings::set($key, $value);
    }

    public function editKey(string $keyToChange, mixed $newValue): bool
    {
        if ($this->hasEnvFile()) {
            return parent::editKey($keyToChange, $newValue);
        }

        return RuntimeSettings::set($keyToChange, $newValue);
    }

    public function deleteKey(string $key): bool
    {
        if ($this->hasEnvFile()) {
            return parent::deleteKey($key);
        }

        return RuntimeSettings::delete($key);
    }

    private function hasEnvFile(): bool
    {
        $dir = rtrim((string) $this->config('paths.env'), DIRECTORY_SEPARATOR);
        $file = (string) $this->config('envFileName', '.env');

        return $dir !== '' && is_file($dir . DIRECTORY_SEPARATOR . $file);
    }
}
