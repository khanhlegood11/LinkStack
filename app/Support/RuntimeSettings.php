<?php

namespace App\Support;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;

class RuntimeSettings
{
    /**
     * Default LinkStack configuration keys and values when running without a .env file.
     * Infrastructure secrets (APP_KEY, DB_*, SESSION_*, etc.) are intentionally excluded.
     *
     * @var array<string, string>
     */
    public const DEFAULTS = [
        'LOCALE' => 'en',
        'REGISTER_AUTH' => 'auth',
        'ALLOW_REGISTRATION' => 'true',
        'MANUAL_USER_VERIFICATION' => 'false',
        'NOTIFY_EVENTS' => 'true',
        'NOTIFY_UPDATES' => 'false',
        'DISPLAY_FOOTER' => 'true',
        'DISPLAY_CREDIT' => 'true',
        'DISPLAY_CREDIT_FOOTER' => 'true',
        'DISPLAY_FOOTER_HOME' => 'true',
        'DISPLAY_FOOTER_TERMS' => 'true',
        'DISPLAY_FOOTER_PRIVACY' => 'true',
        'DISPLAY_FOOTER_CONTACT' => 'true',
        'TITLE_FOOTER_HOME' => '',
        'TITLE_FOOTER_TERMS' => '',
        'TITLE_FOOTER_PRIVACY' => '',
        'TITLE_FOOTER_CONTACT' => '',
        'HOME_FOOTER_LINK' => '',
        'ADMIN_EMAIL' => '',
        'SUPPORTED_DOMAINS' => '',
        'HOME_URL' => '',
        'ALLOW_USER_HTML' => 'true',
        'APP_NAME' => 'LinkStack',
        'ENABLE_BUTTON_EDITOR' => 'true',
        'APP_DEBUG' => 'false',
        'APP_ENV' => 'production',
        'LOG_LEVEL' => 'error',
        'MAINTENANCE_MODE' => 'false',
        'MAIL_MAILER' => 'smtp',
        'MAIL_HOST' => '',
        'MAIL_PORT' => '',
        'MAIL_USERNAME' => '',
        'MAIL_PASSWORD' => '',
        'MAIL_ENCRYPTION' => '',
        'MAIL_FROM_ADDRESS' => '',
        'JOIN_BETA' => 'false',
        'SKIP_UPDATE_BACKUP' => 'true',
        'CUSTOM_META_TAGS' => 'false',
        'FORCE_HTTPS' => 'false',
        'ALLOW_CUSTOM_CODE_IN_THEMES' => 'true',
        'ENABLE_THEME_UPDATER' => 'false',
        'ENABLE_SOCIAL_LOGIN' => 'false',
        'USE_THEME_PREVIEW_IFRAME' => 'true',
        'FORCE_ROUTE_HTTPS' => 'true',
        'HIDE_VERIFICATION_CHECKMARK' => 'false',
        'ALLOW_CUSTOM_BACKGROUNDS' => 'false',
        'ALLOW_USER_EXPORT' => 'true',
        'ALLOW_USER_IMPORT' => 'false',
        'ENABLE_REPORT_ICON' => 'false',
        'ENABLE_ADMIN_BAR' => 'true',
        'ENABLE_ADMIN_BAR_USERS' => 'false',
    ];

    /**
     * Baseline process environment values captured before database overrides.
     *
     * @var array<string, string>|null
     */
    private static ?array $baseline = null;

    public static function hasEnvFile(): bool
    {
        return is_file(base_path('.env'));
    }

    public static function isEditable(string $key): bool
    {
        return array_key_exists($key, self::DEFAULTS);
    }

    public static function boot(): void
    {
        if (self::hasEnvFile()) {
            return;
        }

        if (self::$baseline === null) {
            self::$baseline = [];
            foreach (self::DEFAULTS as $key => $defaultValue) {
                $existing = self::readProcessEnv($key);
                if ($existing !== null) {
                    self::$baseline[$key] = $existing;
                } else {
                    self::$baseline[$key] = $defaultValue;
                    self::apply($key, $defaultValue);
                }
            }
        } else {
            foreach (self::$baseline as $key => $value) {
                self::apply($key, $value);
            }
        }

        if (app()->runningInConsole() && !env('VERCEL')) {
            self::syncConfig();
            return;
        }

        try {
            $overrides = DB::table('runtime_settings')->pluck('value', 'key');
            foreach ($overrides as $key => $value) {
                $keyStr = (string) $key;
                if (self::isEditable($keyStr)) {
                    self::apply($keyStr, self::normalize($value));
                }
            }
        } catch (\Throwable $e) {
            // Ignore database errors during build, discovery, or pre-migration boot.
        }

        self::syncConfig();
    }

    public static function get(string $key, mixed $default = null): float|bool|int|string|null
    {
        $raw = self::readProcessEnv($key);
        if ($raw === null && array_key_exists($key, self::DEFAULTS)) {
            $raw = self::DEFAULTS[$key];
        }

        if ($raw === null) {
            return $default;
        }

        return $raw !== '' ? $raw : ($default ?? '');
    }

    public static function set(string $key, mixed $rawValue): bool
    {
        if (!self::isEditable($key)) {
            return false;
        }

        $normalized = self::normalize($rawValue);
        $now = Carbon::now();

        DB::table('runtime_settings')->updateOrInsert(
            ['key' => $key],
            ['value' => $normalized, 'updated_at' => $now]
        );

        self::apply($key, $normalized);
        self::syncConfig();

        return true;
    }

    public static function delete(string $key): bool
    {
        if (!self::isEditable($key)) {
            return false;
        }

        DB::table('runtime_settings')->where('key', $key)->delete();

        $fallback = self::$baseline[$key] ?? self::DEFAULTS[$key];
        self::apply($key, $fallback);
        self::syncConfig();

        return true;
    }

    public static function renderEditableEnv(): string
    {
        $lines = [
            '# LinkStack Runtime Configuration (persisted in PostgreSQL)',
            '# Infrastructure & secret keys (APP_KEY, DB_*, SESSION_*) are managed in Vercel Environment Variables.',
            '',
        ];

        foreach (self::DEFAULTS as $key => $defaultValue) {
            $val = (string) (self::get($key, $defaultValue) ?? '');
            if ($val !== '' && preg_match('/[\s#"\'=$]/', $val)) {
                $escaped = str_replace(['\\', '"'], ['\\\\', '\\"'], $val);
                $lines[] = "{$key}=\"{$escaped}\"";
            } else {
                $lines[] = "{$key}={$val}";
            }
        }

        return implode("\n", $lines) . "\n";
    }

    public static function saveEditableEnv(string $content): void
    {
        $now = Carbon::now();
        $lines = preg_split('/\r\n|\r|\n/', $content) ?: [];

        foreach ($lines as $line) {
            $trimmed = trim($line);
            if ($trimmed === '' || str_starts_with($trimmed, '#')) {
                continue;
            }

            $pos = strpos($trimmed, '=');
            if ($pos === false) {
                continue;
            }

            $key = trim(substr($trimmed, 0, $pos));
            $rawVal = trim(substr($trimmed, $pos + 1));

            if (!self::isEditable($key)) {
                continue;
            }

            $normalized = self::normalize($rawVal);
            DB::table('runtime_settings')->updateOrInsert(
                ['key' => $key],
                ['value' => $normalized, 'updated_at' => $now]
            );
            self::apply($key, $normalized);
        }

        self::syncConfig();
    }

    public static function normalize(mixed $rawValue): string
    {
        if (is_bool($rawValue)) {
            return $rawValue ? 'true' : 'false';
        }

        if ($rawValue === null) {
            return '';
        }

        $str = str_replace(["\r", "\n", "\0"], '', (string) $rawValue);

        if (preg_match('/\A"(.*)"\z/s', $str, $matches)) {
            $str = str_replace(['\\"', '\\\\'], ['"', '\\'], $matches[1]);
        } elseif (preg_match("/\A'(.*)'\z/s", $str, $matches)) {
            $str = $matches[1];
        }

        return $str;
    }

    private static function readProcessEnv(string $key): ?string
    {
        $raw = $_ENV[$key] ?? $_SERVER[$key] ?? null;
        if ($raw === null) {
            $envVal = getenv($key);
            if ($envVal === false) {
                return null;
            }
            $raw = $envVal;
        }

        if (is_bool($raw)) {
            return $raw ? 'true' : 'false';
        }

        return (string) $raw;
    }

    private static function apply(string $key, string $value): void
    {
        $clean = str_replace(["\r", "\n", "\0"], '', $value);
        $_ENV[$key] = $clean;
        $_SERVER[$key] = $clean;
        putenv($key . '=' . $clean);
    }

    private static function syncConfig(): void
    {
        $appName = (string) (self::get('APP_NAME', 'LinkStack') ?? 'LinkStack');
        $locale = (string) (self::get('LOCALE', 'en') ?? 'en');
        $debug = filter_var(self::get('APP_DEBUG', 'false'), FILTER_VALIDATE_BOOLEAN);
        $appEnv = (string) (self::get('APP_ENV', 'production') ?? 'production');
        $logLevel = (string) (self::get('LOG_LEVEL', 'error') ?? 'error');
        $mailMailer = (string) (self::get('MAIL_MAILER', 'smtp') ?? 'smtp');
        $mailFromAddress = self::get('MAIL_FROM_ADDRESS', '');
        $mailFromName = self::readProcessEnv('MAIL_FROM_NAME') ?: $appName;

        config([
            'app.name' => $appName,
            'app.debug' => $debug,
            'app.env' => $appEnv,
            'logging.channels.stderr.level' => $logLevel,
            'mail.default' => $mailMailer !== '' ? $mailMailer : 'smtp',
            'mail.mailers.smtp.host' => self::get('MAIL_HOST', ''),
            'mail.mailers.smtp.port' => self::get('MAIL_PORT', ''),
            'mail.mailers.smtp.encryption' => self::get('MAIL_ENCRYPTION', ''),
            'mail.mailers.smtp.username' => self::get('MAIL_USERNAME', ''),
            'mail.mailers.smtp.password' => self::get('MAIL_PASSWORD', ''),
            'mail.mailers.smtp.from.address' => $mailFromAddress,
            'mail.mailers.smtp.from.name' => $mailFromName,
            'mail.mailers.sendmail.from.address' => $mailFromAddress,
            'mail.mailers.sendmail.from.name' => $mailFromName,
            'mail.mailers.built-in.from.name' => $mailFromName,
        ]);

        if ($locale !== '') {
            config(['app.locale' => $locale]);
            app()->setLocale($locale);
        }

        if (env('FORCE_ROUTE_HTTPS') == 'true' || env('FORCE_HTTPS') == 'true') {
            URL::forceScheme('https');
        }
    }
}
