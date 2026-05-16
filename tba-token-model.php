<?php
/**
 * Token Model Class
 *
 * Handles CRUD operations for tba_tokens table
 *
 * @package TokenBasedAccess
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class TBA_Token_Model
 */
class TBA_Token_Model {
    
    /**
     * Table name (without prefix)
     *
     * @var string
     */
    private static $table_name = 'tba_tokens';
    
    /**
     * Get full table name with WordPress prefix
     *
     * @return string
     */
    public static function get_table_name() {
        global $wpdb;
        return $wpdb->prefix . self::$table_name;
    }
    
    /**
     * Get token by ID
     *
     * @param int $token_id Token ID
     * @return object|null Token object or null if not found
     */
    public static function get_by_id($token_id) {
        global $wpdb;
        
        $table_name = self::get_table_name();
        $token_id = absint($token_id);
        
        $token = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$table_name} WHERE token_id = %d",
                $token_id
            ),
            OBJECT
        );
        
        return $token ? $token : null;
    }
    
    /**
     * Get token by email
     *
     * @param string $email Email address
     * @return array Array of token objects
     */
    public static function get_by_email($email) {
        global $wpdb;
        
        $table_name = self::get_table_name();
        
        $tokens = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table_name} WHERE token_email = %s ORDER BY created_at DESC",
                sanitize_email($email)
            ),
            OBJECT
        );
        
        return $tokens;
    }


    
    /**
     * Get token by order ID
     *
     * @param int $order_id Order ID
     * @return object|null Token object or null if not found
     */
    public static function get_by_order_id($order_id) {
        global $wpdb;
        
        $table_name = self::get_table_name();
        $order_id = absint($order_id);
        
        $token = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$table_name} WHERE order_id = %d ORDER BY created_at DESC LIMIT 1",
                $order_id
            ),
            OBJECT
        );
        
        return $token ? $token : null;
    }
    
    /**
     * Get active tokens (non-blocked and non-expired)
     *
     * @param array $args Optional arguments (email, is_blocked, is_expired)
     * @return array Array of token objects
     */
    public static function get_active_tokens($args = array()) {
        global $wpdb;
        
        $table_name = self::get_table_name();
        $where = array('is_blocked = 0', 'is_expired = 0');
        $where_values = array();
        
        if (!empty($args['email'])) {
            $where[] = 'token_email = %s';
            $where_values[] = sanitize_email($args['email']);
        }
        
        if (isset($args['is_blocked'])) {
            $where[] = 'is_blocked = %d';
            $where_values[] = absint($args['is_blocked']);
        }
        
        if (isset($args['is_expired'])) {
            $where[] = 'is_expired = %d';
            $where_values[] = absint($args['is_expired']);
        }
        
        $where_clause = implode(' AND ', $where);
        
        if (!empty($where_values)) {
            $query = $wpdb->prepare(
                "SELECT * FROM {$table_name} WHERE {$where_clause} ORDER BY created_at DESC",
                $where_values
            );
        } else {
            $query = "SELECT * FROM {$table_name} WHERE {$where_clause} ORDER BY created_at DESC";
        }
        
        return $wpdb->get_results($query, OBJECT);
    }
    
    /**
     * Create a new token
     *
     * @param array $data Token data
     * @return int|false Token ID on success, false on failure
     */
    public static function create($data) {
        global $wpdb;
        
        $table_name = self::get_table_name();
        

        tba_log_debug('TBA: Creating token with data: ' . print_r($data, true));

        // Prepare data with defaults
        $defaults = array(
            'order_id' => 0,
            'token_email' => '',
            'user_agent' => '',
            'ip_address' => '',
            'started_at' => null,
            'hours_allowed' => 0,
            'is_blocked' => 0,
            'is_expired' => 0,
        );
        
        $data = wp_parse_args($data, $defaults);
        
        // Sanitize data
        $insert_data = array(
            'order_id' => absint($data['order_id']),
            'token_email' => sanitize_email($data['token_email']),
            'user_agent' => sanitize_text_field($data['user_agent']),
            'ip_address' => sanitize_text_field($data['ip_address']),
            'started_at' => $data['started_at'] ? sanitize_text_field($data['started_at']) : null,
            'hours_allowed' => absint($data['hours_allowed']),
            'is_blocked' => absint($data['is_blocked']),
            'is_expired' => absint($data['is_expired']),
        );
        

        tba_log_debug('TBA: Insert data: ' . print_r($insert_data, true));
        // Validate required fields
        if (empty($insert_data['token_email']) || empty($insert_data['order_id'])) {
            tba_log_debug('TBA: Required fields are missing for token creation');
            return false;
        }
        
        // Insert into database
        $result = $wpdb->insert($table_name, $insert_data);
        
        if ($result === false) {
            return false;
        }
        
        return $wpdb->insert_id;
    }
    
    /**
     * Update token
     *
     * @param int $token_id Token ID
     * @param array $data Data to update
     * @return bool True on success, false on failure
     */
    public static function update($token_id, $data) {
        global $wpdb;
        
        $table_name = self::get_table_name();
        $token_id = absint($token_id);
        
        // Prepare update data
        $update_data = array();
        
        $allowed_fields = array(
            'order_id',
            'token_email',
            'user_agent',
            'ip_address',
            'started_at',
            'hours_allowed',
            'is_blocked',
            'is_expired',
        );
        
        foreach ($allowed_fields as $field) {
            if (isset($data[$field])) {
                if ($field === 'started_at') {
                    $update_data[$field] = $data[$field] ? sanitize_text_field($data[$field]) : null;
                } elseif (in_array($field, array('order_id', 'hours_allowed', 'is_blocked', 'is_expired'))) {
                    $update_data[$field] = absint($data[$field]);
                } elseif ($field === 'token_email') {
                    $update_data[$field] = sanitize_email($data[$field]);
                } else {
                    $update_data[$field] = sanitize_text_field($data[$field]);
                }
            }
        }
        
        if (empty($update_data)) {
            return false;
        }
        
        // Update database
        $result = $wpdb->update(
            $table_name,
            $update_data,
            array('token_id' => $token_id),
            null,
            array('%d')
        );
        
        return $result !== false;
    }
    
    /**
     * Delete token
     *
     * @param int $token_id Token ID
     * @return bool True on success, false on failure
     */
    public static function delete($token_id) {
        global $wpdb;
        
        $table_name = self::get_table_name();
        $token_id = absint($token_id);
        
        $result = $wpdb->delete(
            $table_name,
            array('token_id' => $token_id),
            array('%d')
        );
        
        return $result !== false;
    }
    
    /**
     * Activate token (set started_at timestamp)
     *
     * @param int $token_id Token ID
     * @param string $session_id Session ID (deprecated, kept for backward compatibility)
     * @return bool True on success, false on failure
     */
    public static function activate($token_id, $session_id = '') {
        $update_data = array(
            'started_at' => current_time('mysql'),
        );
        
        return self::update($token_id, $update_data);
    }
    
    /**
     * Deactivate token (clear started_at timestamp)
     *
     * @param int $token_id Token ID
     * @return bool True on success, false on failure
     */
    public static function deactivate($token_id) {
        return self::update($token_id, array('started_at' => null));
    }
    
    /**
     * Block token
     *
     * @param int $token_id Token ID
     * @return bool True on success, false on failure
     */
    public static function block($token_id) {
        $update_data = array(
            'is_blocked' => 1,
        );
        
        return self::update($token_id, $update_data);
    }
    
    /**
     * Unblock token
     *
     * @param int $token_id Token ID
     * @return bool True on success, false on failure
     */
    public static function unblock($token_id) {
        return self::update($token_id, array('is_blocked' => 0));
    }
    
    /**
     * Mark token as expired
     *
     * @param int $token_id Token ID
     * @return bool True on success, false on failure
     */
    public static function expire($token_id) {
        $update_data = array(
            'is_expired' => 1,
        );
        
        return self::update($token_id, $update_data);
    }
    
    
    /**
     * Check if token is expired based on hours_allowed and started_at
     *
     * @param object|int $token Token object or token ID
     * @return bool True if expired, false otherwise
     */
    public static function is_token_expired($token) {
        if (is_numeric($token)) {
            $token = self::get_by_id($token);
        }
        
        if (!$token) {
            return true;
        }
        
        // If already marked as expired
        if ($token->is_expired == 1) {
            return true;
        }
        
        // If not started yet
        if (empty($token->started_at)) {
            return false;
        }
        
        // If hours_allowed is 0, token never expires
        if ($token->hours_allowed == 0) {
            return false;
        }
        
        // Calculate expiration time
        $started_time = strtotime($token->started_at);
        $expiration_time = $started_time + ($token->hours_allowed * HOUR_IN_SECONDS);
        $current_time = current_time('timestamp');
        
        return $current_time > $expiration_time;
    }
    
    /**
     * Get all tokens with pagination
     *
     * @param array $args Query arguments
     * @return array Array with 'tokens' and 'total' count
     */
    public static function get_all($args = array()) {
        global $wpdb;
        
        $table_name = self::get_table_name();
        
        $defaults = array(
            'per_page' => 20,
            'page' => 1,
            'orderby' => 'created_at',
            'order' => 'DESC',
            'order_id' => null,
            'email' => '',
            'is_blocked' => null,
            'is_expired' => null,
        );
        
        $args = wp_parse_args($args, $defaults);
        
        $where = array();
        $where_values = array();
        
        if ($args['order_id'] !== null && $args['order_id'] !== '') {
            $where[] = 'order_id = %d';
            $where_values[] = absint($args['order_id']);
        }
        
        if (!empty($args['email'])) {
            $where[] = 'token_email = %s';
            $where_values[] = sanitize_email($args['email']);
        }
        
        if ($args['is_blocked'] !== null) {
            $where[] = 'is_blocked = %d';
            $where_values[] = absint($args['is_blocked']);
        }
        
        if ($args['is_expired'] !== null) {
            $where[] = 'is_expired = %d';
            $where_values[] = absint($args['is_expired']);
        }
        
        $where_clause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
        
        // Get total count
        if (!empty($where_values)) {
            $count_query = $wpdb->prepare(
                "SELECT COUNT(*) FROM {$table_name} {$where_clause}",
                $where_values
            );
        } else {
            $count_query = "SELECT COUNT(*) FROM {$table_name} {$where_clause}";
        }
        
        $total = $wpdb->get_var($count_query);
        
        // Get tokens with pagination
        $offset = ($args['page'] - 1) * $args['per_page'];
        $orderby = sanitize_sql_orderby($args['orderby'] . ' ' . $args['order']);
        
        if (!$orderby) {
            $orderby = 'created_at DESC';
        }
        
        if (!empty($where_values)) {
            $query = $wpdb->prepare(
                "SELECT * FROM {$table_name} {$where_clause} ORDER BY {$orderby} LIMIT %d OFFSET %d",
                array_merge($where_values, array($args['per_page'], $offset))
            );
        } else {
            $query = $wpdb->prepare(
                "SELECT * FROM {$table_name} {$where_clause} ORDER BY {$orderby} LIMIT %d OFFSET %d",
                $args['per_page'],
                $offset
            );
        }
        
        $tokens = $wpdb->get_results($query, OBJECT);
        
        return array(
            'tokens' => $tokens,
            'total' => (int) $total,
        );
    }
}
