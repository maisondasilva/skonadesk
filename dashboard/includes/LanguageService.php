<?php
// SPDX-License-Identifier: AGPL-3.0-or-later
// Created by Mike Hayward — github.com/Skonamonkey

/**
 * LanguageService — loads JSON language files and resolves translations
 * with a fallback chain: user preference → dashboard default → 'en'.
 *
 * Usage:
 *   $lang = LanguageService::getLanguage();
 *   echo $lang('nav.dashboard');
 *
 * Or via the __() helper:
 *   echo __('nav.dashboard');
 */

class LanguageService {
    private static ?string $langCode = null;
    private static ?array $strings = null;
    private static ?array $available = null;

    /** Directory containing language JSON files */
    private static string $langDir = '';

    /**
     * Initialise with a language code. Called once per request from layout.php
     * or the login page. Call with null to re-initialise with current session.
     */
    public static function init(?string $code = null): void {
        self::ensureLangDir();

        if ($code !== null) {
            self::$langCode = $code;
        } else {
            // Resolve from session or cookie
            self::$langCode = self::resolveLanguage();
        }

        // Re-scan available languages on next getAvailable() call
        self::$available = null;

        self::$strings = self::load(self::$langCode);
    }

    /**
     * Ensure langDir is set based on LANG_DIR constant or default path.
     * Safe to call before init().
     */
    private static function ensureLangDir(): void {
        if (self::$langDir === '') {
            self::$langDir = defined('LANG_DIR') ? LANG_DIR : __DIR__ . '/../lang';
        }
    }

    /**
     * Resolve the language to use via fallback chain:
     * 1. Session language (set at login or when user changes preference)
     * 2. Cookie language (for login page when not logged in)
     * 3. Dashboard default language (from settings API, best-effort)
     * 4. 'en'
     */
    private static function resolveLanguage(): string {
        // Check session (set at login and on language change)
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }
        $sessionLang = $_SESSION['language'] ?? '';
        if ($sessionLang && self::fileExists($sessionLang)) {
            return $sessionLang;
        }

        // Check cookie (persists across logouts for the login page)
        $cookieLang = $_COOKIE['skona_lang'] ?? '';
        if ($cookieLang && self::fileExists($cookieLang)) {
            return $cookieLang;
        }

        // Fallback to 'en'
        return 'en';
    }

    /**
     * Load a language file, with fallback to 'en', then to empty array.
     * Returns the loaded strings array.
     */
    private static function load(string $code): array {
        self::ensureLangDir();
        $path = self::$langDir . '/' . $code . '.json';
        if (is_readable($path)) {
            $strings = json_decode(file_get_contents($path), true);
            if (is_array($strings)) {
                return $strings;
            }
        }

        // File not found or invalid — try fallback
        if ($code !== 'en') {
            return self::load('en');
        }

        // en.json itself is missing — return empty
        return [];
    }

    /**
     * Check if a language file exists on disk.
     */
    private static function fileExists(string $code): bool {
        self::ensureLangDir();
        return is_readable(self::$langDir . '/' . $code . '.json');
    }

    /**
     * Scan the lang directory and return available languages.
     * Returns array of ['code' => 'en', 'name' => 'English', 'name_native' => 'English']
     */
    public static function getAvailable(): array {
        if (self::$available !== null) {
            return self::$available;
        }

        self::ensureLangDir();
        $files = glob(self::$langDir . '/*.json');
        $languages = [];

        foreach ($files as $file) {
            $code = pathinfo($file, PATHINFO_FILENAME);
            if ($code === 'en') {
                // Always put English first
                continue;
            }
            $meta = self::loadMeta($code);
            $languages[] = [
                'code' => $code,
                'name' => $meta['name'] ?? $code,
                'name_native' => $meta['name_native'] ?? $meta['name'] ?? $code,
            ];
        }

        // Sort alphabetically by name
        usort($languages, fn($a, $b) => strcmp($a['name'], $b['name']));

        // Prepend English
        $enMeta = self::loadMeta('en');
        array_unshift($languages, [
            'code' => 'en',
            'name' => $enMeta['name'] ?? 'English',
            'name_native' => $enMeta['name_native'] ?? $enMeta['name'] ?? 'English',
        ]);

        self::$available = $languages;
        return $languages;
    }

    /**
     * Load just the _meta block from a language file.
     */
    private static function loadMeta(string $code): array {
        self::ensureLangDir();
        $path = self::$langDir . '/' . $code . '.json';
        if (!is_readable($path)) {
            return [];
        }
        $content = file_get_contents($path);
        $data = json_decode($content, true);
        return $data['_meta'] ?? [];
    }

    /**
     * Get the current language code.
     */
    public static function getCode(): string {
        if (self::$langCode === null) {
            self::init();
        }
        return self::$langCode;
    }

    /**
     * Translate a dot-notation key, e.g. 'nav.dashboard'.
     * Returns the key itself if not found (visible but not broken).
     *
     * Supports sprintf-style placeholders:
     *   __('devices.count', 5) -> "5 Devices"
     *   __('devices.confirm_remove', 'ABC123') -> "Remove device ABC123?"
     */
    public static function translate(string $key, mixed ...$args): string {
        if (self::$strings === null) {
            self::init();
        }

        $value = self::resolveKey(self::$strings ?? [], $key);

        if ($value === null) {
            // Key not found — return the key itself for debugging visibility
            return $key;
        }

        if ($args) {
            return sprintf($value, ...$args);
        }

        return $value;
    }

    /**
     * Plural-aware translation. Pass the count and the key prefix.
     * Looks for {$key} and {$key}_plural keys.
     *
     *   __('plural', 'devices.count', 5)
     *   -> looks for 'devices.count' (if n=1) or 'devices.count_plural' (if n!=1)
     */
    public static function translatePlural(string $key, int $count): string {
        if ($count === 1) {
            return self::translate($key, $count);
        }
        $pluralKey = $key . '_plural';
        $plural = self::resolveKey(self::$strings ?? [], $pluralKey);
        if ($plural !== null) {
            return sprintf($plural, $count);
        }
        // Fall back to singular with count
        return self::translate($key, $count);
    }

    /**
     * Resolve a dot-notation key in a nested array.
     */
    private static function resolveKey(array $strings, string $key): mixed {
        $parts = explode('.', $key);
        $current = $strings;
        foreach ($parts as $part) {
            if (!is_array($current) || !array_key_exists($part, $current)) {
                return null;
            }
            $current = $current[$part];
        }
        return is_string($current) ? $current : null;
    }
}

/**
 * Global helper function for translation.
 * Short name for use throughout templates.
 *
 * @param string $key Dot-notation key (e.g. 'nav.dashboard')
 * @param mixed ...$args Optional sprintf arguments
 * @return string
 */
function __(string $key, mixed ...$args): string {
    return LanguageService::translate($key, ...$args);
}

/**
 * Plural-aware helper.
 */
function __p(string $key, int $count): string {
    return LanguageService::translatePlural($key, $count);
}
