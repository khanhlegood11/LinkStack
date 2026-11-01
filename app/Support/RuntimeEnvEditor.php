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

        return array_key_exists($key, $_ENV)
            || array_key_exists($key, $_SERVER)
            || getenv($key) !== false;
    }

    public function getKey(string $key, mixed $default = null): float|bool|int|string|null
    {
        if ($this->hasEnvFile()) {
            return parent::getKey($key, $default);
        }

        $raw = $_ENV[$key] ?? $_SERVER[$key] ?? null;
        if ($raw === null) {
            $envVal = getenv($key);
            $raw = $envVal !== false ? $envVal : null;
        }

        if (is_bool($raw)) {
            return $raw ? 'true' : 'false';
        }

        return ($raw !== null && $raw !== '') ? $raw : $default;
    }

    private function hasEnvFile(): bool
    {
        $dir = rtrim((string) $this->config('paths.env'), DIRECTORY_SEPARATOR);
        $file = (string) $this->config('envFileName', '.env');

        return $dir !== '' && is_file($dir . DIRECTORY_SEPARATOR . $file);
    }
}
