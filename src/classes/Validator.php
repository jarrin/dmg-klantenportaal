<?php

class Validator {
    private static $errors = [];
    
    /**
     * Validate user data
     */
    public static function validateUser($data, $isUpdate = false) {
        self::$errors = [];
        
        // Email validation
        if (!$isUpdate || isset($data['email'])) {
            if (empty($data['email'] ?? '')) {
                self::$errors['email'] = 'Email is verplicht';
            } elseif (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
                self::$errors['email'] = 'Ongeldig emailadres';
            }
        }
        
        // First name validation
        if (!$isUpdate || isset($data['first_name'])) {
            if (empty($data['first_name'] ?? '')) {
                self::$errors['first_name'] = 'Voornaam is verplicht';
            } elseif (!self::isValidName($data['first_name'])) {
                self::$errors['first_name'] = 'Voornaam mag alleen letters en spaties bevatten';
            } elseif (strlen($data['first_name']) < 2 || strlen($data['first_name']) > 100) {
                self::$errors['first_name'] = 'Voornaam moet tussen 2 en 100 karakters lang zijn';
            }
        }
        
        // Last name validation
        if (!$isUpdate || isset($data['last_name'])) {
            if (empty($data['last_name'] ?? '')) {
                self::$errors['last_name'] = 'Achternaam is verplicht';
            } elseif (!self::isValidName($data['last_name'])) {
                self::$errors['last_name'] = 'Achternaam mag alleen letters en spaties bevatten';
            } elseif (strlen($data['last_name']) < 2 || strlen($data['last_name']) > 100) {
                self::$errors['last_name'] = 'Achternaam moet tussen 2 en 100 karakters lang zijn';
            }
        }
        
        // Company name validation (optional)
        if (isset($data['company_name']) && !empty($data['company_name'])) {
            if (strlen($data['company_name']) > 255 || strlen($data['company_name']) < 2) {
                self::$errors['company_name'] = 'Bedrijfsnaam moet tussen 2 en 255 karakters lang zijn';
            }
        }
        
        // Address validation (optional)
        if (isset($data['address']) && !empty($data['address'])) {
            if (strlen($data['address']) > 255 || strlen($data['address']) < 3) {
                self::$errors['address'] = 'Adres moet tussen 3 en 255 karakters lang zijn';
            }
        }
        
        // Postal code validation (optional)
        if (isset($data['postal_code']) && !empty($data['postal_code'])) {
            if (!self::isValidPostalCode($data['postal_code'])) {
                self::$errors['postal_code'] = 'Ongeldig postcode formaat (bijv: 1234 AB, 12345, SW1A 1AA)';
            }
        }
        
        // City validation (optional)
        if (isset($data['city']) && !empty($data['city'])) {
            if (!self::isValidName($data['city'])) {
                self::$errors['city'] = 'Plaats mag alleen letters en spaties bevatten';
            } elseif (strlen($data['city']) < 2 || strlen($data['city']) > 100) {
                self::$errors['city'] = 'Plaats moet tussen 2 en 100 karakters lang zijn';
            }
        }
        
        // Country validation (optional)
        if (isset($data['country']) && !empty($data['country'])) {
            if (!self::isValidName($data['country'])) {
                self::$errors['country'] = 'Land mag alleen letters en spaties bevatten';
            } elseif (strlen($data['country']) < 2 || strlen($data['country']) > 100) {
                self::$errors['country'] = 'Land moet tussen 2 en 100 karakters lang zijn';
            }
        }
        
        // Phone validation (optional)
        if (isset($data['phone']) && !empty($data['phone'])) {
            if (!self::isValidPhone($data['phone'])) {
                self::$errors['phone'] = 'Ongeldig telefoonnummer (alleen getallen, + en -)';
            } elseif (strlen($data['phone']) < 5 || strlen($data['phone']) > 50) {
                self::$errors['phone'] = 'Telefoonnummer moet tussen 5 en 50 karakters lang zijn';
            }
        }
        
        return empty(self::$errors);
    }
    
    /**
     * Validate name fields (first_name, last_name, city, country)
     * Supports international characters and accents
     */
    private static function isValidName($name) {
        // Allow letters (including accented), spaces, hyphens, apostrophes, and dots
        // Supports: Latin, accented characters, and many international scripts
        return preg_match('/^[a-zA-Z\s\-\'\.éèêëàâäùûüôöœæçñáàâäãåèéêëìíîïòóôõöùúûüýÿñ]+$/u', $name);
    }
    
    /**
     * Validate postal code (supports multiple formats)
     * - Dutch: 1234 AB
     * - US/Canada: 12345 or 12345-6789
     * - UK: Flexible format
     * - General: 3-10 alphanumeric characters
     */
    private static function isValidPostalCode($postalCode) {
        // Allow various international postal code formats
        // Dutch: 1234 AB or 1234AB
        // US: 12345 or 12345-6789
        // UK: SW1A 1AA
        // General: alphanumeric with spaces and hyphens
        return preg_match('/^[0-9A-Z]{3,10}(\s|-)?[0-9A-Z]{0,3}$/i', $postalCode) && strlen($postalCode) >= 3;
    }
    
    /**
     * Validate phone number
     */
    private static function isValidPhone($phone) {
        // Allow digits, +, -, (, ), and spaces
        return preg_match('/^[0-9\s\+\-\(\)]{5,}$/', $phone);
    }
    
    /**
     * Get validation errors
     */
    public static function getErrors() {
        return self::$errors;
    }
    
    /**
     * Get first error message
     */
    public static function getFirstError() {
        if (!empty(self::$errors)) {
            return reset(self::$errors);
        }
        return null;
    }
}
