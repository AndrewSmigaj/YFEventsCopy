<?php

declare(strict_types=1);

namespace YakimaFinds\Application\Validation;

class ConfigValidator extends BaseValidator
{
    /**
     * Validate configuration update
     */
    public function validateUpdate(array $data): array
    {
        $errors = [];

        foreach ($data as $category => $settings) {
            $categoryErrors = $this->validateCategory($category, $settings);
            if (!empty($categoryErrors)) {
                $errors[$category] = $categoryErrors;
            }
        }

        return $errors;
    }

    /**
     * Validate email settings
     */
    public function validateEmailSettings(array $data): array
    {
        $errors = [];

        if (isset($data['smtp_host']) && empty($data['smtp_host'])) {
            $errors['smtp_host'] = 'SMTP host is required';
        }

        if (isset($data['smtp_port'])) {
            if (!is_numeric($data['smtp_port']) || $data['smtp_port'] < 1 || $data['smtp_port'] > 65535) {
                $errors['smtp_port'] = 'SMTP port must be between 1 and 65535';
            }
        }

        if (isset($data['smtp_encryption']) && !in_array($data['smtp_encryption'], ['tls', 'ssl', 'none'])) {
            $errors['smtp_encryption'] = 'Invalid encryption type';
        }

        if (isset($data['from_email']) && !filter_var($data['from_email'], FILTER_VALIDATE_EMAIL)) {
            $errors['from_email'] = 'Invalid email format';
        }

        if (isset($data['from_name']) && empty($data['from_name'])) {
            $errors['from_name'] = 'From name is required';
        }

        return $errors;
    }

    /**
     * Validate database settings
     */
    public function validateDatabaseSettings(array $data): array
    {
        $errors = [];

        if (isset($data['max_connections'])) {
            if (!is_numeric($data['max_connections']) || $data['max_connections'] < 1) {
                $errors['max_connections'] = 'Max connections must be a positive integer';
            }
        }

        if (isset($data['timeout'])) {
            if (!is_numeric($data['timeout']) || $data['timeout'] < 1) {
                $errors['timeout'] = 'Timeout must be a positive integer';
            }
        }

        if (isset($data['charset']) && !in_array($data['charset'], ['utf8', 'utf8mb4', 'latin1'])) {
            $errors['charset'] = 'Invalid charset';
        }

        return $errors;
    }

    /**
     * Validate cache settings
     */
    public function validateCacheSettings(array $data): array
    {
        $errors = [];

        if (isset($data['driver']) && !in_array($data['driver'], ['file', 'redis', 'memcached'])) {
            $errors['driver'] = 'Invalid cache driver';
        }

        if (isset($data['ttl'])) {
            if (!is_numeric($data['ttl']) || $data['ttl'] < 0) {
                $errors['ttl'] = 'TTL must be a non-negative integer';
            }
        }

        if (isset($data['prefix']) && !preg_match('/^[a-zA-Z0-9_]+$/', $data['prefix'])) {
            $errors['prefix'] = 'Cache prefix can only contain letters, numbers, and underscores';
        }

        return $errors;
    }

    /**
     * Validate API settings
     */
    public function validateApiSettings(array $data): array
    {
        $errors = [];

        if (isset($data['max_requests_per_minute'])) {
            if (!is_numeric($data['max_requests_per_minute']) || $data['max_requests_per_minute'] < 1) {
                $errors['max_requests_per_minute'] = 'Max requests per minute must be a positive integer';
            }
        }

        return $errors;
    }

    /**
     * Validate scraper settings
     */
    public function validateScraperSettings(array $data): array
    {
        $errors = [];

        if (isset($data['schedule']) && !$this->isValidCronExpression($data['schedule'])) {
            $errors['schedule'] = 'Invalid cron expression';
        }

        if (isset($data['timeout'])) {
            if (!is_numeric($data['timeout']) || $data['timeout'] < 1) {
                $errors['timeout'] = 'Timeout must be a positive integer';
            }
        }

        if (isset($data['max_concurrent'])) {
            if (!is_numeric($data['max_concurrent']) || $data['max_concurrent'] < 1) {
                $errors['max_concurrent'] = 'Max concurrent must be a positive integer';
            }
        }

        if (isset($data['user_agent']) && empty($data['user_agent'])) {
            $errors['user_agent'] = 'User agent is required';
        }

        return $errors;
    }

    /**
     * Validate SEO settings
     */
    public function validateSeoSettings(array $data): array
    {
        $errors = [];

        if (isset($data['site_title']) && empty($data['site_title'])) {
            $errors['site_title'] = 'Site title is required';
        }

        if (isset($data['meta_description']) && strlen($data['meta_description']) > 160) {
            $errors['meta_description'] = 'Meta description should not exceed 160 characters';
        }

        if (isset($data['google_analytics']) && !empty($data['google_analytics'])) {
            if (!preg_match('/^(UA-\d+-\d+|G-[A-Z0-9]+)$/', $data['google_analytics'])) {
                $errors['google_analytics'] = 'Invalid Google Analytics tracking ID';
            }
        }

        return $errors;
    }

    /**
     * Validate security settings
     */
    public function validateSecuritySettings(array $data): array
    {
        $errors = [];

        if (isset($data['max_login_attempts'])) {
            if (!is_numeric($data['max_login_attempts']) || $data['max_login_attempts'] < 1) {
                $errors['max_login_attempts'] = 'Max login attempts must be a positive integer';
            }
        }

        if (isset($data['lockout_duration'])) {
            if (!is_numeric($data['lockout_duration']) || $data['lockout_duration'] < 60) {
                $errors['lockout_duration'] = 'Lockout duration must be at least 60 seconds';
            }
        }

        if (isset($data['password_min_length'])) {
            if (!is_numeric($data['password_min_length']) || $data['password_min_length'] < 6) {
                $errors['password_min_length'] = 'Password minimum length must be at least 6';
            }
        }

        return $errors;
    }

    private function validateCategory(string $category, array $settings): array
    {
        switch ($category) {
            case 'email':
                return $this->validateEmailSettings($settings);
            case 'database':
                return $this->validateDatabaseSettings($settings);
            case 'cache':
                return $this->validateCacheSettings($settings);
            case 'api':
                return $this->validateApiSettings($settings);
            case 'scraper':
                return $this->validateScraperSettings($settings);
            case 'seo':
                return $this->validateSeoSettings($settings);
            case 'security':
                return $this->validateSecuritySettings($settings);
            default:
                return []; // No validation for unknown categories
        }
    }

    private function isValidCronExpression(string $expression): bool
    {
        $parts = explode(' ', $expression);
        
        if (count($parts) !== 5) {
            return false;
        }

        $ranges = [
            [0, 59], // minute
            [0, 23], // hour
            [1, 31], // day
            [1, 12], // month
            [0, 6]   // day of week
        ];

        foreach ($parts as $index => $part) {
            if ($part === '*') {
                continue;
            }

            if (strpos($part, '/') !== false) {
                $stepParts = explode('/', $part);
                if (count($stepParts) !== 2) {
                    return false;
                }
                $part = $stepParts[0];
            }

            if (strpos($part, ',') !== false) {
                $values = explode(',', $part);
                foreach ($values as $value) {
                    if (!$this->isValidCronValue($value, $ranges[$index])) {
                        return false;
                    }
                }
            } elseif (strpos($part, '-') !== false) {
                $rangeParts = explode('-', $part);
                if (count($rangeParts) !== 2) {
                    return false;
                }
                foreach ($rangeParts as $value) {
                    if (!$this->isValidCronValue($value, $ranges[$index])) {
                        return false;
                    }
                }
            } else {
                if (!$this->isValidCronValue($part, $ranges[$index])) {
                    return false;
                }
            }
        }

        return true;
    }

    private function isValidCronValue(string $value, array $range): bool
    {
        if (!is_numeric($value)) {
            return false;
        }

        $intValue = (int)$value;
        return $intValue >= $range[0] && $intValue <= $range[1];
    }
}