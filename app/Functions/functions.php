<?php

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;

if (!function_exists('preloadDirectoryFiles')) {
    /**
     * Preload all files in a directory and optionally cache in Redis or Laravel cache.
     *
     * @param string $directory
     * @param string $cacheKey Unique cache key
     * @param int $ttl Cache time in seconds
     * @return array Array of filenames
     */
    function preloadDirectoryFiles(string $directory, string $cacheKey, int $ttl = 3600): array
    {
        static $memoryCache = [];

        // Return from memory if already loaded
        if (isset($memoryCache[$directory])) {
            return $memoryCache[$directory];
        }

        $files = [];
        $fingerprint = md5(json_encode(scandir($directory)));

        // Try Redis first
        if (class_exists('Redis')) {
            try {
                $cached = Redis::get($cacheKey);
                if ($cached) {
                    $cached = json_decode($cached, true);
                    if (isset($cached['fingerprint']) && $cached['fingerprint'] === $fingerprint) {
                        $memoryCache[$directory] = $cached['files'];
                        return $cached['files'];
                    }
                }
            } catch (\Exception $e) {
                // Redis not available, fallback to local memory
            }
        }

        // Scan directory and cache
        $files = scandir($directory);

        // Cache in Redis if possible
        $data = [
            'fingerprint' => $fingerprint,
            'files' => $files
        ];
        if (class_exists('Redis')) {
            try {
                Redis::setex($cacheKey, $ttl, json_encode($data));
            } catch (\Exception $e) {
                // fallback silently
            }
        } else {
            // Laravel cache fallback
            Cache::put($cacheKey, $data, $ttl);
        }

        $memoryCache[$directory] = $files;

        return $files;
    }
}

function findFile($name)
{
    $directory = base_path("/assets/linkstack/images/");
    $files = preloadDirectoryFiles($directory, 'linkstack_images_files');

    $pattern = '/^' . preg_quote($name, '/') . '(_\w+)?\.\w+$/i';
    foreach ($files as $file) {
        if (preg_match($pattern, $file)) {
            return $file;
        }
    }
    return "error.error";
}

function findAvatar($name)
{
    $directory = base_path("assets/img");
    $files = preloadDirectoryFiles($directory, 'assets_img_files');

    $pattern = '/^' . preg_quote($name, '/') . '(_\w+)?\.\w+$/i';
    foreach ($files as $file) {
        if (preg_match($pattern, $file)) {
            return "assets/img/" . $file;
        }
    }
    return "error.error";
}

function findBackground($name)
{
    $directory = base_path("assets/img/background-img/");
    $files = preloadDirectoryFiles($directory, 'assets_img_background_files');

    $pattern = '/^' . preg_quote($name, '/') . '(_\w+)?\.\w+$/i';
    foreach ($files as $file) {
        if (preg_match($pattern, $file)) {
            return $file;
        }
    }
    return "error.error";
}

function analyzeImageBrightness($file) {
    try {
    $file = base_path('assets/img/background-img/'.$file);
  
    // Get image information using getimagesize
    $imageInfo = getimagesize($file);
    if (!$imageInfo) {
      return 'dark';
    }
  
    // Get the image type
    $type = $imageInfo[2];
  
    // Load the image based on its type
    switch ($type) {
      case IMAGETYPE_JPEG:
      case IMAGETYPE_JPEG2000:
        $img = imagecreatefromjpeg($file);
        break;
      case IMAGETYPE_PNG:
        $img = imagecreatefrompng($file);
        break;
      default:
        return 'dark';
    }
  
    // Get image dimensions
    $width = imagesx($img);
    $height = imagesy($img);
  
    // Calculate the average brightness of the image
    $total_brightness = 0;
    for ($x=0; $x<$width; $x++) {
      for ($y=0; $y<$height; $y++) {
        $rgb = imagecolorat($img, $x, $y);
        $r = ($rgb >> 16) & 0xFF;
        $g = ($rgb >> 8) & 0xFF;
        $b = $rgb & 0xFF;
        $brightness = (int)(($r + $g + $b) / 3);
        $total_brightness += $brightness;
      }
    }
    $avg_brightness = $total_brightness / ($width * $height);
  
    // Determine if the image is more dark or light
    if ($avg_brightness < 128) {
      return 'dark';
    } else {
      return 'light';
    }
      } catch (\Throwable $th) {
          return null;
      }
  }
  
  function infoIcon($tip) {
    echo '
      <div class="d-flex justify-content-center align-items-center">
        <a data-bs-toggle="tooltip" data-bs-placement="bottom" title="' . $tip . '">
          <svg class="icon-32" width="32" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path fill-rule="evenodd" clip-rule="evenodd" d="M7.67 1.99927H16.34C19.73 1.99927 22 4.37927 22 7.91927V16.0903C22 19.6203 19.73 21.9993 16.34 21.9993H7.67C4.28 21.9993 2 19.6203 2 16.0903V7.91927C2 4.37927 4.28 1.99927 7.67 1.99927ZM11.99 9.06027C11.52 9.06027 11.13 8.66927 11.13 8.19027C11.13 7.70027 11.52 7.31027 12.01 7.31027C12.49 7.31027 12.88 7.70027 12.88 8.19027C12.88 8.66927 12.49 9.06027 11.99 9.06027ZM12.87 15.7803C12.87 16.2603 12.48 16.6503 11.99 16.6503C11.51 16.6503 11.12 16.2603 11.12 15.7803V11.3603C11.12 10.8793 11.51 10.4803 11.99 10.4803C12.48 10.4803 12.87 10.8793 12.87 11.3603V15.7803Z" fill="currentColor"></path>
          </svg>
        </a>
      </div>
    ';
  }

function external_file_get_contents($url) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, false);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:80.0) Gecko/20100101 Firefox/80.0');
    $data = curl_exec($ch);
    curl_close($ch);
    return $data;
}

function uri($path) {
    $url = str_replace(['http://', 'https://'], '', url(''));
    return "//" . $url . "/" . $path;
}

function footer($key)
{
    $upperStr = strtoupper($key);
    if (env('TITLE_FOOTER_'.$upperStr) == "") {
        $title = __('messages.footer.'.$key);
    } else {
        $title = env('TITLE_FOOTER_'.$upperStr);
    }
    return $title;
}

function strip_tags_except_allowed_protocols($str) {
    preg_match_all('/<a[^>]+>(.*?)<\/a>/i', $str, $matches, PREG_SET_ORDER);

    foreach ($matches as $val) {
        if (!preg_match('/href=["\'](http:|https:|mailto:|tel:)[^"\']*["\']/', $val[0])) {
            $str = str_replace($val[0], $val[1], $str);
        }
    }

    return $str;
}

if(!function_exists('setBlockAssetContext')) {
  function setBlockAssetContext($type = null) {
      static $currentType = null;
      if ($type !== null) {
          $currentType = $type;
      }
      return $currentType;
  }
}

// Get custom block assets
if(!function_exists('block_asset')) {
  function block_asset($file) {
      $type = setBlockAssetContext(); // Retrieve the current type context
      return url("block-asset/$type?asset=$file");
  }
}

if(!function_exists('get_block_file_contents')) {
  function get_block_file_contents($file) {
      $type = setBlockAssetContext(); // Retrieve the current type context
      return file_get_contents(base_path("blocks/$type/$file"));
  }
}

function block_text_translation_check($text) {
  if (empty($text)) {
    return false;
  }
  $translate = __("messages.$text");
  return $translate === "messages.$text" ? true : false;
}

function block_text($text) {
  $translate = __("messages.$text");
  return $translate === "messages.$text" ? $text : $translate;
}

function bt($text) {
  return block_text($text);
}

/* --------------------------------------------------------------------------
 | Database-backed profile pictures
 | --------------------------------------------------------------------------
 | The Vercel container has a read-only, ephemeral filesystem, so uploading to
 | assets/img cannot work there. On Vercel profile pictures are persisted in
 | the public.user_avatars table and served through /avatar/{id}. Local
 | installations keep the original file-based behaviour.
 * -------------------------------------------------------------------------- */

if (!function_exists('dbAvatarMeta')) {
    /**
     * Cheap lookup: deliberately does not select the (large) base64 payload.
     */
    function dbAvatarMeta($userId): ?object
    {
        if (!env('VERCEL')) {
            return null;
        }

        try {
            return \Illuminate\Support\Facades\DB::table('user_avatars')
                ->select('user_id', 'mime', 'extension', 'updated_at')
                ->where('user_id', (int) $userId)
                ->first();
        } catch (\Throwable $e) {
            return null;
        }
    }
}

if (!function_exists('dbAvatarData')) {
    function dbAvatarData($userId): ?object
    {
        try {
            return \Illuminate\Support\Facades\DB::table('user_avatars')
                ->where('user_id', (int) $userId)
                ->first();
        } catch (\Throwable $e) {
            return null;
        }
    }
}

if (!function_exists('hasAvatar')) {
    function hasAvatar($userId): bool
    {
        if (dbAvatarMeta($userId) !== null) {
            return true;
        }

        $legacy = findAvatar($userId);
        return $legacy !== 'error.error' && is_file(base_path($legacy));
    }
}

if (!function_exists('avatarUrl')) {
    function avatarUrl($userId): string
    {
        $row = dbAvatarMeta($userId);
        if ($row !== null) {
            $version = strtotime((string) $row->updated_at);
            return url('avatar/' . (int) $userId) . '?v=' . ($version ?: 0);
        }

        $legacy = findAvatar($userId);
        if ($legacy !== 'error.error' && is_file(base_path($legacy))) {
            return url($legacy);
        }

        return asset('assets/linkstack/images/logo.svg');
    }
}

if (!function_exists('saveDbAvatar')) {
    /**
     * Persist an uploaded image in Postgres. Returns false when the file is
     * invalid or when the table is unavailable.
     */
    function saveDbAvatar($userId, $uploadedFile): bool
    {
        if (!env('VERCEL') || !$uploadedFile
            || !method_exists($uploadedFile, 'isValid')
            || !$uploadedFile->isValid()) {
            return false;
        }

        $path = $uploadedFile->getRealPath();
        $binary = $path ? file_get_contents($path) : false;
        if ($binary === false || $binary === '' || strlen($binary) > 4 * 1024 * 1024) {
            return false;
        }

        $mimeMap = [
            'image/jpeg' => 'jpg',
            'image/jpg'  => 'jpg',
            'image/pjpeg' => 'jpg',
            'image/png'  => 'png',
            'image/webp' => 'webp',
        ];
        $mime = strtolower((string) $uploadedFile->getMimeType());
        $extension = $mimeMap[$mime] ?? strtolower((string) $uploadedFile->getClientOriginalExtension());
        if (!in_array($extension, ['jpg', 'jpeg', 'png', 'webp'], true)) {
            $extension = 'png';
        }
        if ($extension === 'jpeg') {
            $extension = 'jpg';
        }
        if (!isset($mimeMap[$mime])) {
            $mime = $extension === 'jpg' ? 'image/jpeg' : 'image/' . $extension;
        }

        $id = (int) $userId;
        $now = \Carbon\Carbon::now();
        $payload = [
            'mime' => $mime,
            'extension' => $extension,
            'data' => base64_encode($binary),
            'updated_at' => $now,
        ];

        try {
            $exists = \Illuminate\Support\Facades\DB::table('user_avatars')
                ->where('user_id', $id)->exists();

            if ($exists) {
                \Illuminate\Support\Facades\DB::table('user_avatars')
                    ->where('user_id', $id)->update($payload);
            } else {
                \Illuminate\Support\Facades\DB::table('user_avatars')->insert(
                    $payload + ['user_id' => $id, 'created_at' => $now]
                );
            }

            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }
}

if (!function_exists('deleteDbAvatar')) {
    function deleteDbAvatar($userId): bool
    {
        try {
            \Illuminate\Support\Facades\DB::table('user_avatars')
                ->where('user_id', (int) $userId)->delete();
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }
}
/* --------------------------------------------------------------------------
 | Database-backed uploads (Vercel / read-only filesystems)
 | --------------------------------------------------------------------------
 | Site logo, favicon, per-user custom backgrounds and uploaded theme ZIPs are
 | persisted in Postgres on Vercel and served through public HTTP routes.
 | Local installations keep using the original files on disk; every helper
 | falls back to those files when no database row exists.
 * -------------------------------------------------------------------------- */

if (!function_exists('dbAssetMeta')) {
    /**
     * Cheap lookup that deliberately avoids selecting the base64 payload.
     */
    function dbAssetMeta($key): ?object
    {
        // Per-request cache: views ask for the site logo/favicon several times.
        $key = (string) $key;
        if (!isset($GLOBALS['linkstack_db_asset_meta'])) {
            $GLOBALS['linkstack_db_asset_meta'] = [];
        }
        if (array_key_exists($key, $GLOBALS['linkstack_db_asset_meta'])) {
            return $GLOBALS['linkstack_db_asset_meta'][$key];
        }

        try {
            $row = \Illuminate\Support\Facades\DB::table('site_assets')
                ->select('key', 'mime', 'extension', 'updated_at')
                ->where('key', $key)
                ->first();
        } catch (\Throwable $e) {
            $row = null;
        }

        return $GLOBALS['linkstack_db_asset_meta'][$key] = $row;
    }
}

if (!function_exists('dbAssetInvalidate')) {
    function dbAssetInvalidate($key): void
    {
        unset($GLOBALS['linkstack_db_asset_meta'][(string) $key]);
    }
}

if (!function_exists('dbAssetData')) {
    function dbAssetData($key): ?object
    {
        try {
            return \Illuminate\Support\Facades\DB::table('site_assets')
                ->where('key', (string) $key)
                ->first();
        } catch (\Throwable $e) {
            return null;
        }
    }
}

if (!function_exists('dbAssetExists')) {
    function dbAssetExists($key): bool
    {
        return dbAssetMeta($key) !== null;
    }
}

if (!function_exists('dbAssetUrl')) {
    function dbAssetUrl($key): string
    {
        $row = dbAssetMeta($key);
        if ($row === null) {
            return '';
        }

        $version = strtotime((string) $row->updated_at);
        return url('db-asset/' . rawurlencode((string) $key)) . '?v=' . ($version ?: 0);
    }
}

if (!function_exists('saveDbAssetBinary')) {
    /**
     * Upsert arbitrary bytes into public.site_assets.
     */
    function saveDbAssetBinary($key, $binary, $mime, $extension = ''): bool
    {
        if ($binary === false || $binary === '' || strlen($binary) > 16 * 1024 * 1024) {
            return false;
        }

        $key = (string) $key;
        if (!preg_match('/^[A-Za-z0-9_-]+$/', $key)) {
            return false;
        }

        $now = \Carbon\Carbon::now();
        $payload = [
            'mime' => (string) $mime,
            'extension' => (string) $extension,
            'data' => base64_encode($binary),
            'updated_at' => $now,
        ];

        try {
            $exists = \Illuminate\Support\Facades\DB::table('site_assets')
                ->where('key', $key)->exists();

            if ($exists) {
                \Illuminate\Support\Facades\DB::table('site_assets')
                    ->where('key', $key)->update($payload);
            } else {
                \Illuminate\Support\Facades\DB::table('site_assets')->insert(
                    $payload + ['key' => $key, 'created_at' => $now]
                );
            }

            dbAssetInvalidate($key);
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }
}

if (!function_exists('saveDbAsset')) {
    /**
     * Persist an uploaded image in public.site_assets.
     *
     * @param  string  $key           e.g. site_logo, site_favicon, background_42
     * @param  mixed   $uploadedFile  Symfony UploadedFile
     * @param  array   $allowedMimes  optional whitelist of MIME types
     */
    function saveDbAsset($key, $uploadedFile, array $allowedMimes = [], $maxBytes = 4 * 1024 * 1024): bool
    {
        if (!$uploadedFile || !method_exists($uploadedFile, 'isValid') || !$uploadedFile->isValid()) {
            return false;
        }

        $path = $uploadedFile->getRealPath();
        $binary = $path ? file_get_contents($path) : false;
        if ($binary === false || $binary === '' || strlen($binary) > (int) $maxBytes) {
            return false;
        }

        $mimeMap = [
            'image/jpeg' => 'jpg',
            'image/jpg' => 'jpg',
            'image/pjpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
            'image/svg+xml' => 'svg',
            'image/x-icon' => 'ico',
            'image/vnd.microsoft.icon' => 'ico',
        ];

        $mime = strtolower((string) $uploadedFile->getMimeType());
        if ($allowedMimes !== [] && !in_array($mime, $allowedMimes, true)) {
            return false;
        }

        $extension = $mimeMap[$mime] ?? strtolower((string) $uploadedFile->getClientOriginalExtension());
        if (!preg_match('/^[a-z0-9]+$/', $extension)) {
            return false;
        }
        if (!isset($mimeMap[$mime])) {
            $mime = 'application/octet-stream';
        }

        return saveDbAssetBinary($key, $binary, $mime, $extension);
    }
}

if (!function_exists('deleteDbAsset')) {
    function deleteDbAsset($key): bool
    {
        try {
            \Illuminate\Support\Facades\DB::table('site_assets')
                ->where('key', (string) $key)->delete();
            dbAssetInvalidate($key);
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }
}

if (!function_exists('siteImageKey')) {
    function siteImageKey($name): string
    {
        return $name === 'favicon' ? 'site_favicon' : 'site_logo';
    }
}

if (!function_exists('siteImageExists')) {
    function siteImageExists($name): bool
    {
        if (dbAssetMeta(siteImageKey($name)) !== null) {
            return true;
        }

        $legacy = findFile($name);
        return $legacy !== 'error.error'
            && is_file(base_path('assets/linkstack/images/' . $legacy));
    }
}

if (!function_exists('siteImageUrl')) {
    function siteImageUrl($name): string
    {
        $key = siteImageKey($name);
        if (dbAssetMeta($key) !== null) {
            return dbAssetUrl($key);
        }

        $legacy = findFile($name);
        if ($legacy !== 'error.error' && is_file(base_path('assets/linkstack/images/' . $legacy))) {
            return asset('assets/linkstack/images/' . $legacy);
        }

        return asset('assets/linkstack/images/logo.svg');
    }
}

if (!function_exists('backgroundKey')) {
    function backgroundKey($userId): string
    {
        return 'background_' . (int) $userId;
    }
}

if (!function_exists('hasBackground')) {
    function hasBackground($userId): bool
    {
        if (dbAssetMeta(backgroundKey($userId)) !== null) {
            return true;
        }

        $legacy = findBackground($userId);
        return $legacy !== 'error.error'
            && is_file(base_path('assets/img/background-img/' . $legacy));
    }
}

if (!function_exists('backgroundUrl')) {
    function backgroundUrl($userId): string
    {
        $key = backgroundKey($userId);
        if (dbAssetMeta($key) !== null) {
            return dbAssetUrl($key);
        }

        $legacy = findBackground($userId);
        if ($legacy !== 'error.error' && is_file(base_path('assets/img/background-img/' . $legacy))) {
            return url('assets/img/background-img/' . $legacy);
        }

        return url('/assets/linkstack/images/themes/no-preview.png');
    }
}

/* --------------------------------------------------------------------------
 | Database-backed themes
 | -------------------------------------------------------------------------- */

if (!function_exists('safeThemeSlug')) {
    function safeThemeSlug($slug): string
    {
        return substr((string) preg_replace('/[^A-Za-z0-9_-]/', '', (string) $slug), 0, 60);
    }
}

if (!function_exists('safeThemePath')) {
    function safeThemePath($path): string
    {
        $path = str_replace('\\', '/', (string) $path);
        $parts = [];
        foreach (explode('/', $path) as $part) {
            if ($part === '' || $part === '.' || $part === '..') {
                continue;
            }
            $parts[] = $part;
        }
        return implode('/', $parts);
    }
}

if (!function_exists('themeFileMime')) {
    function themeFileMime($path): string
    {
        $map = [
            'css' => 'text/css',
            'js' => 'application/javascript',
            'json' => 'application/json',
            'map' => 'application/json',
            'html' => 'text/html',
            'htm' => 'text/html',
            'txt' => 'text/plain',
            'md' => 'text/plain',
            'php' => 'text/plain',
            'svg' => 'image/svg+xml',
            'png' => 'image/png',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'ico' => 'image/x-icon',
            'woff' => 'font/woff',
            'woff2' => 'font/woff2',
            'ttf' => 'font/ttf',
            'eot' => 'application/vnd.ms-fontobject',
        ];

        $ext = strtolower(pathinfo((string) $path, PATHINFO_EXTENSION));
        return $map[$ext] ?? 'application/octet-stream';
    }
}

if (!function_exists('dbThemeFileMeta')) {
    function dbThemeFileMeta($slug, $path): ?object
    {
        // Per-request cache: a themed page resolves several CSS/icon URLs.
        $cacheKey = (string) $slug . "\0" . (string) $path;
        if (!isset($GLOBALS['linkstack_theme_meta'])) {
            $GLOBALS['linkstack_theme_meta'] = [];
        }
        if (array_key_exists($cacheKey, $GLOBALS['linkstack_theme_meta'])) {
            return $GLOBALS['linkstack_theme_meta'][$cacheKey];
        }

        try {
            $row = \Illuminate\Support\Facades\DB::table('theme_files')
                ->select('theme', 'path', 'mime', 'size', 'updated_at')
                ->where('theme', (string) $slug)
                ->where('path', (string) $path)
                ->first();
        } catch (\Throwable $e) {
            $row = null;
        }

        return $GLOBALS['linkstack_theme_meta'][$cacheKey] = $row;
    }
}

if (!function_exists('dbThemeInvalidate')) {
    function dbThemeInvalidate($slug = null): void
    {
        if ($slug === null) {
            $GLOBALS['linkstack_theme_meta'] = [];
            $GLOBALS['linkstack_theme_config'] = [];
            return;
        }

        foreach (array_keys($GLOBALS['linkstack_theme_meta'] ?? []) as $cacheKey) {
            if (strpos($cacheKey, $slug . "\0") === 0) {
                unset($GLOBALS['linkstack_theme_meta'][$cacheKey]);
            }
        }
        unset($GLOBALS['linkstack_theme_config'][$slug]);
    }
}

if (!function_exists('dbThemeFileData')) {
    function dbThemeFileData($slug, $path): ?object
    {
        try {
            return \Illuminate\Support\Facades\DB::table('theme_files')
                ->where('theme', (string) $slug)
                ->where('path', (string) $path)
                ->first();
        } catch (\Throwable $e) {
            return null;
        }
    }
}

if (!function_exists('dbThemeExists')) {
    function dbThemeExists($slug): bool
    {
        try {
            return \Illuminate\Support\Facades\DB::table('theme_files')
                ->where('theme', (string) $slug)->exists();
        } catch (\Throwable $e) {
            return false;
        }
    }
}

if (!function_exists('dbThemeSlugs')) {
    function dbThemeSlugs(): array
    {
        try {
            return \Illuminate\Support\Facades\DB::table('theme_files')
                ->distinct()->pluck('theme')->all();
        } catch (\Throwable $e) {
            return [];
        }
    }
}

if (!function_exists('themeFileExists')) {
    function themeFileExists($slug, $path): bool
    {
        $slug = safeThemeSlug($slug);
        $path = safeThemePath($path);
        if ($slug === '' || $path === '') {
            return false;
        }

        if (dbThemeFileMeta($slug, $path) !== null) {
            return true;
        }

        return is_file(base_path('themes/' . $slug . '/' . $path));
    }
}

if (!function_exists('themeFileUrl')) {
    function themeFileUrl($slug, $path): string
    {
        $slug = safeThemeSlug($slug);
        $path = safeThemePath($path);
        if ($slug === '' || $path === '') {
            return '';
        }

        $row = dbThemeFileMeta($slug, $path);
        if ($row !== null) {
            $version = strtotime((string) $row->updated_at);
            $encoded = implode('/', array_map('rawurlencode', explode('/', $path)));
            return url('theme-file/' . $slug . '/' . $encoded) . '?v=' . ($version ?: 0);
        }

        return url('themes/' . $slug . '/' . $path);
    }
}

if (!function_exists('themeFileContents')) {
    function themeFileContents($slug, $path): ?string
    {
        $slug = safeThemeSlug($slug);
        $path = safeThemePath($path);
        if ($slug === '' || $path === '') {
            return null;
        }

        $row = dbThemeFileData($slug, $path);
        if ($row !== null) {
            $binary = base64_decode((string) $row->data, true);
            return $binary === false ? null : $binary;
        }

        $file = base_path('themes/' . $slug . '/' . $path);
        return is_file($file) ? @file_get_contents($file) : null;
    }
}

if (!function_exists('themeConfigValue')) {
    /**
     * Read a theme setting from the database (config.json generated at upload
     * time) or from a legacy config.php on disk. Never evaluates uploaded PHP.
     */
    function themeConfigValue($slug, $key)
    {
        $slug = safeThemeSlug($slug);
        if ($slug === '') {
            return null;
        }

        if (!isset($GLOBALS['linkstack_theme_config'])) {
            $GLOBALS['linkstack_theme_config'] = [];
        }
        if (!array_key_exists($slug, $GLOBALS['linkstack_theme_config'])) {
            $json = themeFileContents($slug, 'config.json');
            if ($json !== null) {
                $config = json_decode($json, true);
                $GLOBALS['linkstack_theme_config'][$slug] = is_array($config) ? $config : [];
            } else {
                $file = base_path('themes/' . $slug . '/config.php');
                $config = is_file($file) ? include $file : [];
                $GLOBALS['linkstack_theme_config'][$slug] = is_array($config) ? $config : [];
            }
        }

        return $GLOBALS['linkstack_theme_config'][$slug][$key] ?? null;
    }
}

if (!function_exists('themeCustomCode')) {
    /**
     * Render an uploaded theme's custom-head / custom-body Blade fragment.
     * Only reachable for themes uploaded by an administrator.
     */
    function themeCustomCode($slug, $which): string
    {
        $slug = safeThemeSlug($slug);
        $which = (string) preg_replace('/[^a-z-]/', '', (string) $which);
        $path = 'extra/custom-' . $which . '.blade.php';

        $contents = themeFileContents($slug, $path);
        if ($contents === null || $contents === '') {
            return '';
        }

        try {
            return (string) \Illuminate\Support\Facades\Blade::render($contents);
        } catch (\Throwable $e) {
            return '';
        }
    }
}

if (!function_exists('themeDisplayName')) {
    function themeDisplayName($slug, $readme): string
    {
        if (is_string($readme) && preg_match('/Theme Name:\s*(.+)/i', $readme, $matches)) {
            $name = trim($matches[1]);
            if ($name !== '') {
                return $name;
            }
        }

        return ucfirst(str_replace(['-', '_'], ' ', (string) $slug));
    }
}

if (!function_exists('availableThemes')) {
    /**
     * Built-in themes on disk plus themes uploaded into the database.
     *
     * @return array<int, array{slug: string, name: string, preview: string}>
     */
    function availableThemes(): array
    {
        $themes = [];
        $directory = base_path('themes');

        if (is_dir($directory)) {
            foreach (scandir($directory) as $entry) {
                if ($entry === '.' || $entry === '..' || !is_dir($directory . '/' . $entry)) {
                    continue;
                }
                $slug = safeThemeSlug($entry);
                if ($slug === '') {
                    continue;
                }
                $themes[$slug] = [
                    'slug' => $slug,
                    'name' => themeDisplayName($slug, themeFileContents($slug, 'readme.md')),
                ];
            }
        }

        foreach (dbThemeSlugs() as $entry) {
            $slug = safeThemeSlug($entry);
            if ($slug === '' || isset($themes[$slug])) {
                continue;
            }
            $themes[$slug] = [
                'slug' => $slug,
                'name' => themeDisplayName($slug, themeFileContents($slug, 'readme.md')),
            ];
        }

        ksort($themes);
        foreach ($themes as $slug => &$theme) {
            $theme['preview'] = themeFileExists($slug, 'preview.png')
                ? themeFileUrl($slug, 'preview.png')
                : url('/assets/linkstack/images/themes/no-preview.png');
        }
        unset($theme);

        return array_values($themes);
    }
}

if (!function_exists('importDbTheme')) {
    /**
     * Unpack a theme ZIP into public.theme_files without touching the disk.
     *
     * @return string|false theme slug on success
     */
    function importDbTheme($uploadedFile)
    {
        if (!$uploadedFile || !method_exists($uploadedFile, 'isValid') || !$uploadedFile->isValid()) {
            return false;
        }
        if (!class_exists('ZipArchive')) {
            return false;
        }

        $path = $uploadedFile->getRealPath();
        if (!$path) {
            return false;
        }

        $zip = new \ZipArchive();
        if ($zip->open($path) !== true) {
            return false;
        }

        $entries = [];
        $total = 0;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $stat = $zip->statIndex($i);
            if (!$stat) {
                continue;
            }
            $name = str_replace('\\', '/', (string) $stat['name']);
            if (substr($name, -1) === '/' || strpos($name, '__MACOSX') !== false) {
                continue;
            }
            if (in_array('..', explode('/', $name), true)) {
                continue;
            }

            $data = $zip->getFromIndex($i);
            if ($data === false) {
                continue;
            }
            $total += strlen($data);
            if ($total > 16 * 1024 * 1024 || count($entries) > 300) {
                $zip->close();
                return false;
            }

            $parts = array_values(array_filter(
                explode('/', $name),
                fn ($part) => $part !== '' && $part !== '.' && $part !== '..'
            ));
            if ($parts === []) {
                continue;
            }
            $entries[] = ['parts' => $parts, 'data' => $data];
        }
        $zip->close();

        if ($entries === []) {
            return false;
        }

        // Strip a single shared root folder ("MyTheme-v1.2/...").
        $roots = array_unique(array_map(fn ($entry) => $entry['parts'][0], $entries));
        $nested = count(array_filter($entries, fn ($entry) => count($entry['parts']) > 1));
        $root = '';
        if (count($roots) === 1 && $nested === count($entries)) {
            $root = (string) reset($roots);
            foreach ($entries as &$entry) {
                array_shift($entry['parts']);
            }
            unset($entry);
        }

        $files = [];
        foreach ($entries as $entry) {
            $relative = implode('/', $entry['parts']);
            if ($relative === '' || strlen($relative) > 500 || preg_match('/(^|\/)\./', $relative)) {
                continue;
            }
            $files[$relative] = $entry['data'];
        }
        if ($files === []) {
            return false;
        }

        $readme = null;
        $configPhp = null;
        foreach ($files as $relative => $data) {
            $basename = strtolower(basename($relative));
            if ($basename === 'readme.md' && $readme === null) {
                $readme = $data;
            }
            if ($basename === 'config.php' && $configPhp === null) {
                $configPhp = $data;
            }
        }

        $name = '';
        if (is_string($readme) && preg_match('/Theme Name:\s*(.+)/i', $readme, $matches)) {
            $name = trim($matches[1]);
        }
        if ($name === '') {
            $name = (string) preg_replace('/[-_]?v?[0-9][0-9.\-]*$/i', '', $root);
            if ($name === '') {
                $name = 'theme';
            }
        }

        $slug = safeThemeSlug(preg_replace('/\s+/', '-', $name));
        if ($slug === '' || strtolower($slug) === 'default') {
            $slug = 'theme-' . substr(md5($name), 0, 8);
        }
        if (is_dir(base_path('themes/' . $slug))) {
            return false; // never shadow a built-in theme
        }

        // Parse the PHP array literal into JSON instead of executing uploaded code.
        $config = [];
        if (is_string($configPhp) && preg_match_all(
            '/[\'"]([^\'"]+)[\'"]\s*=>\s*[\'"]([^\'"]*)[\'"]/',
            $configPhp,
            $pairs,
            PREG_SET_ORDER
        )) {
            foreach ($pairs as $pair) {
                $config[$pair[1]] = $pair[2];
            }
        }
        $files['config.json'] = json_encode($config);

        $now = \Carbon\Carbon::now();
        try {
            \Illuminate\Support\Facades\DB::transaction(function () use ($slug, $files, $now) {
                \Illuminate\Support\Facades\DB::table('theme_files')
                    ->where('theme', $slug)->delete();

                $rows = [];
                foreach ($files as $relative => $data) {
                    $rows[] = [
                        'theme' => $slug,
                        'path' => $relative,
                        'mime' => themeFileMime($relative),
                        'size' => strlen($data),
                        'data' => base64_encode($data),
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }

                foreach (array_chunk($rows, 20) as $chunk) {
                    \Illuminate\Support\Facades\DB::table('theme_files')->insert($chunk);
                }
            });
        } catch (\Throwable $e) {
            return false;
        }

        dbThemeInvalidate($slug);
        return $slug;
    }
}

if (!function_exists('deleteDbTheme')) {
    function deleteDbTheme($slug): bool
    {
        $slug = safeThemeSlug($slug);
        if ($slug === '') {
            return false;
        }

        try {
            \Illuminate\Support\Facades\DB::table('theme_files')
                ->where('theme', $slug)->delete();
            dbThemeInvalidate($slug);
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }
}

if (!function_exists('theme')) {
    /**
     * Theme setting lookup used by the public page templates. $GLOBALS['themeName']
     * is set by the theme view before any theme() call.
     */
    function theme($key)
    {
        return themeConfigValue($GLOBALS['themeName'] ?? '', $key);
    }
}

if (!function_exists('themeAsset')) {
    function themeAsset($path)
    {
        return themeFileUrl(
            $GLOBALS['themeName'] ?? '',
            'extra/custom-assets/' . ltrim((string) $path, '/')
        );
    }
}
