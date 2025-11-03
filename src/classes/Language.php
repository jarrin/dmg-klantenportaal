<?php

class Language {
    private static $language = 'en'; // Default English
    private static $translations = [];
    
    public static function init($lang = 'en') {
        self::$language = $lang;
        self::loadTranslations();
    }
    
    private static function loadTranslations() {
        $langFile = __DIR__ . '/../lang/' . self::$language . '.php';
        if (file_exists($langFile)) {
            self::$translations = include $langFile;
        } else {
            // Fallback to English if language file not found
            self::$translations = include __DIR__ . '/../lang/en.php';
        }
    }
    
    public static function get($key, $default = null) {
        if (isset(self::$translations[$key])) {
            return self::$translations[$key];
        }
        return $default ?? $key;
    }
    
    public static function setLanguage($lang) {
        self::$language = $lang;
        self::loadTranslations();
    }
    
    public static function getLanguage() {
        return self::$language;
    }
}

// Convenience function
function t($key, $default = null) {
    return Language::get($key, $default);
}
