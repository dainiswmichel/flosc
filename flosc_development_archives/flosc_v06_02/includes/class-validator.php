<?php
/**
 * FLOSC Input Validator
 * 
 * Provides comprehensive input validation for REST endpoints.
 * Supports schema validation, type checking, and content limits.
 * 
 * @since 6.0.1
 */

if (!defined('ABSPATH')) exit;

class FLOSC_Validator {
    
    /**
     * Default limits
     */
    const MAX_MESSAGE_LENGTH = 10000;
    const MAX_ARRAY_DEPTH = 5;
    const MAX_ARRAY_SIZE = 100;
    const MAX_STRING_LENGTH = 50000;
    
    /**
     * Validation errors
     */
    private static $errors = [];
    
    /**
     * Validate a value against a schema
     * 
     * @param mixed $value The value to validate
     * @param array $schema The validation schema
     * @param string $field_name The field name for error messages
     * @return bool True if valid, false otherwise
     */
    public static function validate($value, $schema, $field_name = 'field') {
        self::$errors = [];
        return self::validate_field($value, $schema, $field_name);
    }
    
    /**
     * Get validation errors from last validate() call
     */
    public static function get_errors() {
        return self::$errors;
    }
    
    /**
     * Get first error message
     */
    public static function get_error() {
        return reset(self::$errors) ?: null;
    }
    
    /**
     * Internal field validation
     */
    private static function validate_field($value, $schema, $field_name, $depth = 0) {
        // Prevent infinite recursion
        if ($depth > self::MAX_ARRAY_DEPTH) {
            self::$errors[] = "{$field_name}: Maximum nesting depth exceeded";
            return false;
        }
        
        // Check required
        if (isset($schema['required']) && $schema['required']) {
            if ($value === null || $value === '') {
                self::$errors[] = "{$field_name} is required";
                return false;
            }
        }
        
        // Allow null if not required
        if ($value === null && (!isset($schema['required']) || !$schema['required'])) {
            return true;
        }
        
        // Type validation
        if (isset($schema['type'])) {
            if (!self::validate_type($value, $schema['type'], $field_name)) {
                return false;
            }
        }
        
        // String-specific validations
        if (is_string($value)) {
            if (!self::validate_string($value, $schema, $field_name)) {
                return false;
            }
        }
        
        // Number-specific validations
        if (is_numeric($value)) {
            if (!self::validate_number($value, $schema, $field_name)) {
                return false;
            }
        }
        
        // Array-specific validations
        if (is_array($value)) {
            if (!self::validate_array($value, $schema, $field_name, $depth)) {
                return false;
            }
        }
        
        // Enum validation
        if (isset($schema['enum'])) {
            if (!in_array($value, $schema['enum'], true)) {
                $allowed = implode(', ', $schema['enum']);
                self::$errors[] = "{$field_name} must be one of: {$allowed}";
                return false;
            }
        }
        
        // Pattern validation
        if (isset($schema['pattern']) && is_string($value)) {
            if (!preg_match($schema['pattern'], $value)) {
                self::$errors[] = "{$field_name} does not match required pattern";
                return false;
            }
        }
        
        // Custom validation callback
        if (isset($schema['callback']) && is_callable($schema['callback'])) {
            $result = call_user_func($schema['callback'], $value, $field_name);
            if ($result !== true) {
                self::$errors[] = is_string($result) ? $result : "{$field_name} failed custom validation";
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Validate type
     */
    private static function validate_type($value, $type, $field_name) {
        $valid = false;
        
        switch ($type) {
            case 'string':
                $valid = is_string($value);
                break;
            case 'integer':
            case 'int':
                $valid = is_int($value) || (is_string($value) && ctype_digit($value));
                break;
            case 'float':
            case 'number':
                $valid = is_numeric($value);
                break;
            case 'boolean':
            case 'bool':
                $valid = is_bool($value) || in_array($value, ['0', '1', 0, 1, 'true', 'false'], true);
                break;
            case 'array':
                $valid = is_array($value);
                break;
            case 'object':
                $valid = is_array($value) || is_object($value);
                break;
            case 'email':
                $valid = is_string($value) && is_email($value);
                break;
            case 'url':
                $valid = is_string($value) && filter_var($value, FILTER_VALIDATE_URL);
                break;
            default:
                $valid = true; // Unknown type, skip check
        }
        
        if (!$valid) {
            self::$errors[] = "{$field_name} must be of type {$type}";
        }
        
        return $valid;
    }
    
    /**
     * Validate string constraints
     */
    private static function validate_string($value, $schema, $field_name) {
        // Max length
        $max_length = $schema['maxLength'] ?? self::MAX_STRING_LENGTH;
        if (strlen($value) > $max_length) {
            self::$errors[] = "{$field_name} exceeds maximum length of {$max_length}";
            return false;
        }
        
        // Min length
        if (isset($schema['minLength']) && strlen($value) < $schema['minLength']) {
            self::$errors[] = "{$field_name} must be at least {$schema['minLength']} characters";
            return false;
        }
        
        // Check for null bytes and other dangerous characters
        if (strpos($value, "\0") !== false) {
            self::$errors[] = "{$field_name} contains invalid characters";
            return false;
        }
        
        return true;
    }
    
    /**
     * Validate number constraints
     */
    private static function validate_number($value, $schema, $field_name) {
        $num = floatval($value);
        
        // Minimum
        if (isset($schema['minimum']) && $num < $schema['minimum']) {
            self::$errors[] = "{$field_name} must be at least {$schema['minimum']}";
            return false;
        }
        
        // Maximum
        if (isset($schema['maximum']) && $num > $schema['maximum']) {
            self::$errors[] = "{$field_name} must be at most {$schema['maximum']}";
            return false;
        }
        
        return true;
    }
    
    /**
     * Validate array constraints
     */
    private static function validate_array($value, $schema, $field_name, $depth) {
        // Check size
        $max_size = $schema['maxItems'] ?? self::MAX_ARRAY_SIZE;
        if (count($value) > $max_size) {
            self::$errors[] = "{$field_name} exceeds maximum size of {$max_size}";
            return false;
        }
        
        // Min items
        if (isset($schema['minItems']) && count($value) < $schema['minItems']) {
            self::$errors[] = "{$field_name} requires at least {$schema['minItems']} items";
            return false;
        }
        
        // Validate items against item schema
        if (isset($schema['items'])) {
            foreach ($value as $index => $item) {
                if (!self::validate_field($item, $schema['items'], "{$field_name}[{$index}]", $depth + 1)) {
                    return false;
                }
            }
        }
        
        // Validate object properties
        if (isset($schema['properties'])) {
            foreach ($schema['properties'] as $prop_name => $prop_schema) {
                $prop_value = $value[$prop_name] ?? null;
                if (!self::validate_field($prop_value, $prop_schema, "{$field_name}.{$prop_name}", $depth + 1)) {
                    return false;
                }
            }
        }
        
        return true;
    }
    
    /**
     * Validate message content for AI queries
     */
    public static function validate_message($message) {
        return self::validate($message, [
            'type' => 'string',
            'required' => true,
            'minLength' => 1,
            'maxLength' => self::MAX_MESSAGE_LENGTH,
        ], 'message');
    }
    
    /**
     * Validate email address
     */
    public static function validate_email($email) {
        return self::validate($email, [
            'type' => 'email',
            'required' => true,
            'maxLength' => 254,
        ], 'email');
    }
    
    /**
     * Validate positive integer
     */
    public static function validate_positive_int($value, $field_name = 'value') {
        return self::validate($value, [
            'type' => 'integer',
            'required' => true,
            'minimum' => 1,
        ], $field_name);
    }
    
    /**
     * Validate REST request body
     */
    public static function validate_request($request, $schema) {
        $body = $request->get_json_params();
        
        if (empty($body) && !empty($request->get_body())) {
            // Try to get params from body if JSON parsing failed
            parse_str($request->get_body(), $body);
        }
        
        foreach ($schema as $field => $field_schema) {
            $value = $body[$field] ?? $request->get_param($field);
            if (!self::validate_field($value, $field_schema, $field)) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Sanitize and validate with schema, returning cleaned value
     */
    public static function sanitize($value, $schema) {
        // Validate first
        if (!self::validate($value, $schema, 'value')) {
            return null;
        }
        
        // Then sanitize based on type
        $type = $schema['type'] ?? 'string';
        
        switch ($type) {
            case 'string':
                return sanitize_text_field($value);
            case 'email':
                return sanitize_email($value);
            case 'url':
                return esc_url_raw($value);
            case 'integer':
            case 'int':
                return intval($value);
            case 'float':
            case 'number':
                return floatval($value);
            case 'boolean':
            case 'bool':
                return filter_var($value, FILTER_VALIDATE_BOOLEAN);
            case 'array':
            case 'object':
                return self::sanitize_array($value, $schema);
            default:
                return $value;
        }
    }
    
    /**
     * Recursively sanitize array
     */
    private static function sanitize_array($value, $schema) {
        if (!is_array($value)) {
            return [];
        }
        
        $sanitized = [];
        
        if (isset($schema['items'])) {
            // Indexed array
            foreach ($value as $item) {
                $sanitized[] = self::sanitize($item, $schema['items']);
            }
        } elseif (isset($schema['properties'])) {
            // Associative array/object
            foreach ($schema['properties'] as $key => $prop_schema) {
                if (isset($value[$key])) {
                    $sanitized[$key] = self::sanitize($value[$key], $prop_schema);
                }
            }
        } else {
            // Unknown structure, sanitize values as strings
            foreach ($value as $key => $item) {
                $sanitized[sanitize_key($key)] = is_array($item) 
                    ? self::sanitize_array($item, []) 
                    : sanitize_text_field($item);
            }
        }
        
        return $sanitized;
    }
}
