<?php
/**
 * Plugin Name: Token-based access
 * Plugin URI: https://example.com/token-based-access
 * Description: A plugin that adds token-based access functionality to WooCommerce.
 * Version: 1.0.3
 * Author: Artem Avvakumov
 * Author URI: https://example.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: token-based-access
 * Domain Path: /languages
 * Requires at least: 5.0
 * Requires PHP: 7.4
 * Requires Plugins: woocommerce
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('TBA_VERSION', '1.0.3');
define('TBA_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('TBA_PLUGIN_URL', plugin_dir_url(__FILE__));
define('TBA_PLUGIN_FILE', __FILE__);
define('TBA_TEXTDOMAIN', 'token-based-access');

/**
 * Check if WooCommerce is active
 */
function tba_check_woocommerce_active() {
    if (!class_exists('WooCommerce')) {
        add_action('admin_notices', 'tba_woocommerce_missing_notice');
        return false;
    }
    return true;
}

/**
 * Display notice if WooCommerce is not active
 */
function tba_woocommerce_missing_notice() {
    ?>
    <div class="error">
        <p><?php esc_html_e('Token-based access requires WooCommerce to be installed and active.',  TBA_TEXTDOMAIN); ?></p>
    </div>
    <?php
}

/**
 * Initialize the plugin
 */
function tba_init() {
    // Check if WooCommerce is active
    if (!tba_check_woocommerce_active()) {
        return;
    }

    // Include model files
    require_once TBA_PLUGIN_DIR . 'tba-token-model.php';
    require_once TBA_PLUGIN_DIR . 'tba-token-session-model.php';
    
    // Include settings file
    require_once TBA_PLUGIN_DIR . 'tba-settings.php';
    
    // Include admin view tokens file
    require_once TBA_PLUGIN_DIR . 'tba-admin-view-tokens.php';
    
    // Include WooCommerce integration file
    require_once TBA_PLUGIN_DIR . 'tba-woocommerce.php';
}

// Initialize plugin after WooCommerce is loaded
add_action('plugins_loaded', 'tba_init', 20);

/**
 * Create database tables on plugin activation
 */
function tba_create_tokens_table() {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'tba_tokens';
    $charset_collate = $wpdb->get_charset_collate();
    
    // Check if table already exists
    if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
        $sql = "CREATE TABLE $table_name (
            token_id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            order_id bigint(20) UNSIGNED NOT NULL,
            token_email varchar(255) NOT NULL,
            user_agent varchar(255) NOT NULL,
            ip_address varchar(45) NOT NULL,
            created_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            started_at timestamp NULL DEFAULT NULL,
            hours_allowed int(11) NOT NULL DEFAULT 0,
            is_blocked int(1) NOT NULL DEFAULT 0,
            is_expired int(1) NOT NULL DEFAULT 0,
            PRIMARY KEY (token_id)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
}

/**
 * Create token sessions table on plugin activation
 */
function tba_create_token_sessions_table() {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'tba_token_sessions';
    $charset_collate = $wpdb->get_charset_collate();
    
    // Check if table already exists
    if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
        $sql = "CREATE TABLE $table_name (
            session_id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            token_id bigint(20) UNSIGNED NOT NULL,
            started_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            expires_at timestamp NULL DEFAULT NULL,
            fingerprint varchar(255) NOT NULL DEFAULT '',
            is_blocked int(1) NOT NULL DEFAULT 0,
            ip_address varchar(45) NOT NULL DEFAULT '',
            user_agent varchar(255) NOT NULL DEFAULT '',
            PRIMARY KEY (session_id),
            KEY token_id (token_id)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
}

/**
 * Create all database tables
 */
function tba_create_all_tables() {
    tba_create_tokens_table();
    tba_create_token_sessions_table();
}

// Register activation hook
register_activation_hook(TBA_PLUGIN_FILE, 'tba_create_all_tables');


/**
 * Shortcode: show_access_details
 *
 * @param array $atts Shortcode attributes
 * @return string Output string
 */
function tba_show_access_details_shortcode($atts) {
    // Get current post type
    $current_post_type = get_post_type();
    
    // Get configured post type for ads
    $post_type_for_ads = get_option('tba_post_type_for_ads', '');
    
    // Check if current post type matches the configured post type for ads
    if ($current_post_type === $post_type_for_ads) {

        if ( tba_visitor_has_access() ) {
            return tba_display_access_details( get_the_ID() );
        } elseif ( tba_visitor_has_expired_access() ) {
            return tba_get_setting('message_access_expired');
        } elseif ( tba_visitor_has_blocked_access() ) {
            return tba_get_setting('message_access_blocked');
        } else {
            return tba_get_setting('message_not_allowed_to_view');
        }
    }

    return sprintf(__('Incorrect post type - should be "%s"', TBA_TEXTDOMAIN), $post_type_for_ads);
}

/**
 * Display access details
 *
 * @return string Output string
 */
function tba_display_access_details( $post_id ) {
    
    $template = nl2br(tba_get_setting('access_details_template'));
    if (empty($template)) {
        return '';
    }

    $placeholders = array(
        '{{owner_name}}' => tba_get_setting('meta_field_owner'),
        '{{owner_email}}' => tba_get_setting('meta_field_email'),
        '{{owner_phone}}' => tba_get_setting('meta_field_phone'),
    );

    foreach ($placeholders as $placeholder => $meta_field) {
        if (empty($meta_field)) {
            continue;
        }
        $value = get_post_meta(get_the_ID(), $meta_field, true);
        if (empty($value)) {
            $value = __('N/A', TBA_TEXTDOMAIN);
        }
        $template = str_replace($placeholder, $value, $template);
    }

    $html = do_shortcode($template);

    return $html;
}

/**
 * Check if visitor has access
 *
 * @return bool True if visitor has access, false otherwise
 */
function tba_visitor_has_access() {

    $has_access = false;
    // Check if visitor has token in the local storage
    if (isset($_COOKIE['tba_session'])) {

        tba_log_debug('TBA: Session cookie found'); 
        $session_hash = $_COOKIE['tba_session'];
        $session_id = tba_validate_session($session_hash);
        
        if ($session_id) {
            $has_access = true;
        }
    }
    else {    
        $token_parameter_name = tba_get_setting('token_parameter_name');

        // Check if visitor has token in the URL
        if (isset($_GET[$token_parameter_name])) {
            $token = $_GET[$token_parameter_name];
            $token_id = tba_validate_token_by_hash($token);
            
            if ($token_id) {
                $has_access = true;
            }
        }
    }
    
    return $has_access;
}

function tba_visitor_has_expired_access() {
    $had_expired_access = false;
    // Check if visitor has token in the local storage
    if (isset($_COOKIE['tba_session'])) {

        $session_hash = $_COOKIE['tba_session'];
        $session_id = tba_validate_session($session_hash, true);
        
        if ($session_id) {
            $had_expired_access = true;
        }
    }
    return $had_expired_access;
}

function tba_visitor_has_blocked_access() {
    $had_blocked_access = false;

    // Check if visitor has token in the local storage
    if (isset($_COOKIE['tba_session'])) {

        $session_hash = $_COOKIE['tba_session'];
        
        // Get matchingsession where is_blocked is 1
        if ( TBA_Token_Session_Model::get_by_fingerprint_and_blocked( $session_hash ) ){
            $had_blocked_access = true;
        }
    }
    return $had_blocked_access;
}


function tba_validate_token_by_hash($token_hash) {

    // Get all active tokens and check if the hash matches
    $tokens = TBA_Token_Model::get_active_tokens();
    
    foreach ($tokens as $token) {
        // Generate hash from stored token data to compare
        $generated_hash = tba_generate_token_string($token->ip_address, $token->user_agent, $token->token_email);
        if ($generated_hash === $token_hash) {
            return $token->token_id;
        }
    }

    return false;
}



function tba_validate_session($session_hash, $expired_only = false) {

    // Get all active sessions and check if the hash matches
    $session = TBA_Token_Session_Model::get_by_fingerprint( $session_hash, $expired_only );
    
    tba_log_debug('TBA: found Session by hash: ' . $session_hash . ' - '  . print_r($session, true));
    // Get current IP and user agent for session generation
    $current_ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '';
    $current_user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
    
    if (empty($session)) {
        return false;
    }
    
    // Generate hash from stored session data to compare
    $generated_hash = tba_generate_session_string($session->token_id, $current_ip, $current_user_agent);

    if ($generated_hash === $session_hash) {
        return $session->session_id;
    }


    return false;
}


/**
 *
 * @param object $token_data Token data object
 * @return string Token string
 */
function tba_generate_token_string($ip_address, $user_agent, $user_email) {
    // Generate token string (simple string based on IP, user agent and user email)
    $token_string = $ip_address . $user_agent . $user_email . AUTH_SALT;
    $token_string = base64_encode(hash('haval128,5', $token_string, true));
    $token_string = rtrim(strtr($token_string, '+/', '-_'), '=');
    return $token_string;
}



/**
 *
 * @param object $token_data Token data object
 * @return string Session string
 */
function tba_generate_session_string($token_id, $ip_address, $user_agent) {
    // Generate fingerprint hash (simple hash based on IP and user agent)
    $session_data = $token_id . $ip_address . $user_agent . AUTH_SALT;
    $session_hash = base64_encode(hash('sha256', $session_data, false));
    
    return $session_hash;
}

/**
 * Send token email
 *
 * @param int $token_id Token ID
 * @param int $order_id Order ID
 * @return bool True if email was sent, false otherwise
 */
function tba_send_token_email($token_id, $order_id) {

    $order = wc_get_order($order_id);
    if (!$order) {
        tba_log_debug('Order not found for ID: ' . $order_id);
        return false;
    }

    
    // Get token data
    $token_data = TBA_Token_Model::get_by_id($token_id);
    if (!$token_data) {
        tba_log_debug('Token not found for ID: ' . $token_id);
        return false;
    }
    
    // Get customer email from order
    $customer_email = $order->get_billing_email();
    if (empty($customer_email) || !is_email($customer_email)) {
        tba_log_debug('Invalid customer email for order ID: ' . $order_id);
        return false;
    }
    
    // Get customer name information
    $customer_first_name = $order->get_billing_first_name();
    $customer_last_name = $order->get_billing_last_name();
    $customer_full_name = trim($customer_first_name . ' ' . $customer_last_name);
    if (empty($customer_full_name)) {
        $customer_full_name = $customer_email;
    }
    if (empty($customer_first_name)) {
        $customer_first_name = $customer_email;
    }
    
    // Get email subject and template from settings
    $email_subject = tba_get_setting('email_subject');
    $email_template = tba_get_setting('email_template');
    
    // If no template is set, skip sending
    if (empty($email_template)) {
        tba_log_debug('Email template not configured');
        return false;
    }

    $token_parameter_name = tba_get_setting('token_parameter_name');

    // Generate token string from stored token data
    $token_string = tba_generate_token_string($token_data->ip_address, $token_data->user_agent, $token_data->token_email);
    
    // Calculate expiration date
    $expiration_date = '';
    if ($token_data->hours_allowed > 0) {
        $start_time = time();
        $expiration_timestamp = $start_time + ($token_data->hours_allowed * HOUR_IN_SECONDS);
        $expiration_date = date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $expiration_timestamp);
    } else {
        $expiration_date = __('Never', TBA_TEXTDOMAIN);
    }
    
    // Replace placeholders in subject
    $email_subject = str_replace('{{token_string}}', $token_string, $email_subject);
    $email_subject = str_replace('{{customer_full_name}}', $customer_full_name, $email_subject);
    $email_subject = str_replace('{{customer_first_name}}', $customer_first_name, $email_subject);
    $email_subject = str_replace('{{expiration_date}}', $expiration_date, $email_subject);
    $email_subject = str_replace('{{order_id}}', $order_id, $email_subject);
    $email_subject = str_replace('{{token_parameter}}', $token_parameter_name, $email_subject);

    // Replace placeholders in template
    $email_template = str_replace('{{token_string}}', $token_string, $email_template);
    $email_template = str_replace('{{customer_full_name}}', $customer_full_name, $email_template);
    $email_template = str_replace('{{customer_first_name}}', $customer_first_name, $email_template);
    $email_template = str_replace('{{expiration_date}}', $expiration_date, $email_template);
    $email_template = str_replace('{{order_id}}', $order_id, $email_template);
    $email_template = str_replace('{{token_parameter}}', $token_parameter_name, $email_template);
    // Convert line breaks to HTML
    $email_template = nl2br($email_template);
    
    // Set email headers
    $headers = array(
        'Content-Type: text/html; charset=UTF-8',
        'From: ' . get_bloginfo('name') . ' <' . get_option('admin_email') . '>',
    );
    

    tba_log_debug('Sending token email to: ' . $customer_email . ' for token ID: ' . $token_id);
    tba_log_debug('Email subject: ' . $email_subject);
    tba_log_debug('Email template: ' . $email_template);

    // Send email using WordPress wp_mail
    $sent = wp_mail($customer_email, $email_subject, $email_template, $headers);
    
    if ($sent) {
        tba_log_debug('Token email sent successfully to: ' . $customer_email . ' for token ID: ' . $token_id);
    } else {
        tba_log_debug('Failed to send token email to: ' . $customer_email . ' for token ID: ' . $token_id);
    }
    
    return $sent;
}


function tba_get_token_expiration_timestamp($token_id) {
    $token = TBA_Token_Model::get_by_id($token_id);
    if (!$token) {
        return time() + 1; // token not found - should expire immediately
    }
        
    // If started_at is not set yet, return far future timestamp (token hasn't started)
    if (empty($token->started_at)) {
        return time() + (10 * YEAR_IN_SECONDS); // 10 years from now
    }
    
    // Calculate expiration as started_at + hours_allowed hours
    $started_timestamp = strtotime($token->started_at);
    $expiration_timestamp = $started_timestamp + ($token->hours_allowed * HOUR_IN_SECONDS);
    
    return $expiration_timestamp;
}

/**
 * Validate token from GET parameter and set session cookie
 * 
 * Checks for token in GET parameter (name from settings) and if valid,
 * sets a session cookie 'tba_session' with value from tba_generate_session_string()
 */
function tba_validate_token_and_set_session() {
    // Get token parameter name from settings
    $token_param_name = tba_get_setting('token_parameter_name');
    
    // Default to 't' if not set
    if (empty($token_param_name)) {
        $token_param_name = 't';
    }
    
    // Check if token parameter is present
    if (!isset($_GET[$token_param_name])) {
        return;
    }
    
    $token_value = sanitize_text_field($_GET[$token_param_name]);
    
    if (empty($token_value)) {
        return;
    }
    else {
        tba_log_debug('TBA: Token value provided in GET parameter: ' . $token_value);
    }
    
    // Get all non-expired tokens (query directly to get all non-expired, regardless of blocked status)
    global $wpdb;
    $table_name = $wpdb->prefix . 'tba_tokens';
    $tokens = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT * FROM {$table_name} WHERE is_expired = %d ORDER BY created_at DESC",
            0
        ),
        OBJECT
    );
    
    if (empty($tokens)) {
        return;
    }
    
    // Get current IP and user agent for session generation
    $current_ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '';
    $current_user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
    
    // Validate token by generating token strings for each non-expired token
    $valid_token = null;
    foreach ($tokens as $token) {
        // Generate token string from stored token data
        $generated_token = tba_generate_token_string($token->ip_address, $token->user_agent, $token->token_email);
        tba_log_debug('TBA: Generated token string: ' . $generated_token);
        // Compare with provided token value
        if ($generated_token === $token_value) {
            $valid_token = $token;
            break;
        }
    }
    
    // If token is valid, set session cookie
    if ($valid_token) {
        // Check if there are existing active sessions for this token
        $existing_sessions = TBA_Token_Session_Model::get_by_token_id($valid_token->token_id);
        
        tba_log_debug('TBA: Existing sessions for token ID: ' . $valid_token->token_id . ' - ' . print_r($existing_sessions, true));
        // If there are existing sessions, check if IP or user agent matches
        if (!empty($existing_sessions)) {
           

            // Check if current IP or user agent matches any existing session
            foreach ($existing_sessions as $session) {
                $ip_matches = false;
                $user_agent_matches = false;

                if ($session->ip_address === $current_ip) {
                    $ip_matches = true;
                }
                if ($session->user_agent === $current_user_agent) {
                    $user_agent_matches = true;
                }

                // If IP or user agent does not match existing sessions, block the token
                if ( ! $ip_matches || ! $user_agent_matches ) {
                    tba_log_debug('TBA: Token sessions for ' . $valid_token->token_id . ' blocked - IP and user agent do not match existing sessions');
                    TBA_Token_Session_Model::block($session->session_id);   
                }
            }
            
        }
        
        // Mark started_at if this is the first time the token is validated.
        // Use server time zone for the timestamp so it is consistent with other
        // server-based time calculations (e.g. those using PHP's date()).
        if (empty($valid_token->started_at)) {
            TBA_Token_Model::update($valid_token->token_id, array(
                'started_at' => date('Y-m-d H:i:s')
            ));
        }
        
        // Generate session string
        $session_string = tba_generate_session_string($valid_token->token_id, $current_ip, $current_user_agent);
        
        // Check if there's already a valid, non-expired session with this fingerprint
        $existing_session = TBA_Token_Session_Model::get_by_fingerprint($session_string, false);
        
        if ($existing_session) {
            // Session already exists and is valid, use it instead of creating a new one
            tba_log_debug('TBA: Valid session already exists for token ID: ' . $valid_token->token_id . ', session ID: ' . $existing_session->session_id);
            
            // Set session cookie with existing session data
            $token_expires = $existing_session->expires_at ? strtotime($existing_session->expires_at) : tba_get_token_expiration_timestamp($valid_token->token_id);
            $secure = is_ssl();
            $httponly = true;
            
            setcookie('tba_session', $session_string, $token_expires, '/', '', $secure, $httponly);
            return;
        }
        
        // No valid session exists, create a new one
        // Set session cookie (expires in 30 days, secure and httponly flags based on site settings)
        $token_expires = tba_get_token_expiration_timestamp($valid_token->token_id);
        $secure = is_ssl();
        $httponly = true;
        
        setcookie('tba_session', $session_string, $token_expires, '/', '', $secure, $httponly);

        TBA_Token_Session_Model::create(array(
            'token_id' => $valid_token->token_id,
            'fingerprint' => $session_string,
            'started_at' => current_time('mysql'),
            'expires_at' => date('Y-m-d H:i:s', $token_expires),
            'ip_address' => $current_ip,
            'user_agent' => $current_user_agent,
        ));
        tba_log_debug('TBA: Valid token found, new session created and cookie set for token ID: ' . $valid_token->token_id);
    }
}

/**
 * Test trigger for sending token email via GET parameter
 * 
 * Usage: ?tba_test_order=123 (where 123 is the order ID)
 */
function tba_test_trigger_token_email() {
    // Check if test parameter is set
    if (!isset($_GET['tba_test_order'])) {
        return;
    }
    
    // Get order ID from GET parameter
    $order_id = absint($_GET['tba_test_order']);
    
    if (empty($order_id)) {
        tba_log_debug('TBA Test: Invalid order ID provided');
        return;
    }
    
    // Get the last token for this order
    $token = TBA_Token_Model::get_by_order_id($order_id);
    
    if (!$token) {
        tba_log_debug('TBA Test: No token found for order ID: ' . $order_id);
        return;
    }
    
    // Send the token email
    tba_log_debug('TBA Test: Triggering email for order ID: ' . $order_id . ', token ID: ' . $token->token_id);
    $result = tba_send_token_email($token->token_id, $order_id);
    
    if ($result) {
        tba_log_debug('TBA Test: Email sent successfully for order ID: ' . $order_id);
    } else {
        tba_log_debug('TBA Test: Failed to send email for order ID: ' . $order_id);
    }
}

// Hook the test trigger to init action
add_action('init', 'tba_test_trigger_token_email');

// Register the shortcode
add_shortcode('show_access_details', 'tba_show_access_details_shortcode');

function save_session_data_for_tba() {
    if ( ! session_id() ) {
        session_start();
    }

    tba_validate_token_and_set_session();
}

add_action('init', 'save_session_data_for_tba');