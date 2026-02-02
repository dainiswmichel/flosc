<?php
/**
 * Survey Handler Class
 * Validates and processes survey submissions
 */

if (!defined('ABSPATH')) {
    exit;
}

class Korboc_Survey_Handler {
    
    /**
     * Validate survey submission
     */
    public function validate_submission($data) {
        $errors = new WP_Error();
        
        // Required fields
        $required_fields = array(
            'choir_name' => 'Kora nosaukums ir obligāts',
            'contact_person' => 'Kontaktpersona ir obligāta',
            'email' => 'E-pasta adrese ir obligāta',
            'singer_count' => 'Dziedātāju skaits ir obligāts',
            'main_question' => 'Lūdzu, atbildiet uz galveno jautājumu'
        );
        
        foreach ($required_fields as $field => $message) {
            if (empty($data[$field])) {
                $errors->add('required_field', $message);
            }
        }
        
        // Validate email
        if (!empty($data['email']) && !is_email($data['email'])) {
            $errors->add('invalid_email', 'Lūdzu, ievadiet derīgu e-pasta adresi');
        }
        
        // Validate singer count
        if (!empty($data['singer_count']) && !is_numeric($data['singer_count'])) {
            $errors->add('invalid_number', 'Dziedātāju skaitam jābūt skaitlim');
        }
        
        if ($errors->has_errors()) {
            return $errors;
        }
        
        return true;
    }
    
    /**
     * Sanitize survey data
     */
    public function sanitize_data($data) {
        return array(
            'choir_name' => sanitize_text_field($data['choir_name']),
            'contact_person' => sanitize_text_field($data['contact_person']),
            'email' => sanitize_email($data['email']),
            'phone' => sanitize_text_field($data['phone'] ?? ''),
            'singer_count' => intval($data['singer_count']),
            'main_question' => sanitize_textarea_field($data['main_question']),
            'services' => isset($data['services']) ? array_map('sanitize_text_field', $data['services']) : array(),
            'additional_features' => sanitize_textarea_field($data['additional_features'] ?? ''),
            'pricing_feedback' => sanitize_text_field($data['pricing_feedback'] ?? ''),
            'other_comments' => sanitize_textarea_field($data['other_comments'] ?? ''),
            'interested_in_song' => sanitize_text_field($data['interested_in_song'] ?? ''),
            'receive_gift' => isset($data['receive_gift']) ? 'yes' : 'no'
        );
    }
}
