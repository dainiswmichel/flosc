<?php
if (!defined('ABSPATH')) exit;

class FLOSC_Analytics {
    private static $instance = null;
    
    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function render_page() {
        global $wpdb;
        $table = $wpdb->prefix . 'flosc_quiz_sessions';
        
        $total = $wpdb->get_var("SELECT COUNT(*) FROM $table");
        $completed = $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE completed = 1");
        $paid = $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE is_paid = 1");
        
        $conversion_rate = $completed > 0 ? round(($paid / $completed) * 100, 2) : 0;
        
        ?>
        <div class="wrap">
            <h1>FLOSC Analytics</h1>
            <div class="flosc-stats">
                <div class="stat-box">
                    <h3><?php echo $total; ?></h3>
                    <p>Total Quizzes</p>
                </div>
                <div class="stat-box">
                    <h3><?php echo $completed; ?></h3>
                    <p>Completed</p>
                </div>
                <div class="stat-box">
                    <h3><?php echo $paid; ?></h3>
                    <p>Purchases</p>
                </div>
                <div class="stat-box">
                    <h3><?php echo $conversion_rate; ?>%</h3>
                    <p>Conversion Rate</p>
                </div>
            </div>
        </div>
        <style>
        .flosc-stats { display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; margin-top: 20px; }
        .stat-box { background: white; padding: 20px; text-align: center; border: 1px solid #ccc; border-radius: 8px; }
        .stat-box h3 { font-size: 36px; margin: 0; color: #2563eb; }
        .stat-box p { margin: 10px 0 0; color: #666; }
        </style>
        <?php
    }
}
