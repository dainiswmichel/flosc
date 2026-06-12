<?php
/**
 * FLOSC Quiz Registry
 *
 * The single place that maps quiz IDs to their quiz classes.
 * To add a new quiz: require its class file below and add it to the map in init().
 *
 * @package FLOSC
 * @version 4.0.7
 */

if ( ! defined( 'ABSPATH' ) ) exit;

require_once FLOSC_PLUGIN_DIR . 'includes/quiz-types/abstract-quiz-type.php';
require_once FLOSC_PLUGIN_DIR . 'includes/quiz-types/class-flosc-lesaep-pronunciation-quiz.php';
require_once FLOSC_PLUGIN_DIR . 'includes/quiz-types/class-flosc-sample-text-based-quiz.php';

class FLOSC_Quiz_Registry {

    private static $aliases = [
        'lesaep_text_based_pronunciation_quiz' => 'pronunciation_assessment_quiz',
    ];

    /** @var FLOSC_Abstract_Quiz_Type[]|null */
    private static $quizzes = null;

    private static function init() {
        if ( self::$quizzes !== null ) return;
        self::$quizzes = [
            'pronunciation_assessment_quiz'        => new FLOSC_LeSAEp_Pronunciation_Quiz(),
            'flosc_sample_data_numbers_quiz'        => new FLOSC_Sample_Text_Based_Quiz(),
        ];
    }

    private static function resolve_quiz_id( $quiz_id ) {
        return self::$aliases[ $quiz_id ] ?? $quiz_id;
    }

    /**
     * Get a quiz by its ID.
     *
     * @param  string $quiz_id
     * @return FLOSC_Abstract_Quiz_Type|null  Null if the ID is not registered.
     */
    public static function get_quiz( $quiz_id ) {
        self::init();
        $quiz_id = self::resolve_quiz_id( $quiz_id );
        return self::$quizzes[ $quiz_id ] ?? null;
    }

    /**
     * Return all registered quizzes as [ quiz_id => quiz_instance ].
     *
     * @return FLOSC_Abstract_Quiz_Type[]
     */
    public static function get_all_quizzes() {
        self::init();
        return self::$quizzes;
    }

    /**
     * Check whether a quiz with the given ID is registered.
     *
     * @param  string $quiz_id
     * @return bool
     */
    public static function quiz_exists( $quiz_id ) {
        self::init();
        $quiz_id = self::resolve_quiz_id( $quiz_id );
        return isset( self::$quizzes[ $quiz_id ] );
    }
}
