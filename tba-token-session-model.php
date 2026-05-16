<?php
/**
 * Token Session Model Class
 *
 * Handles CRUD operations for tba_token_sessions table
 *
 * @package TokenBasedAccess
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class TBA_Token_Session_Model
 */
class TBA_Token_Session_Model {
    
    /**
     * Table name (without prefix)
     *
     * @var string
     */
    private static $table_name = 'tba_token_sessions';
    
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
     * Get session by ID
     *
     * @param int $session_id Session ID
     * @return object|null Session object or null if not found
     */
    public static function get_by_id($session_id) {
        global $wpdb;
        
        $table_name = self::get_table_name();
        $session_id = absint($session_id);
        
        $session = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$table_name} WHERE session_id = %d",
                $session_id
            ),
            OBJECT
        );
        
        return $session ? $session : null;
    }
    
    /**
     * Get sessions by token ID
     *
     * @param int $token_id Token ID
     * @return array Array of session objects
     */
    public static function get_by_token_id($token_id) {
        global $wpdb;
        
        $table_name = self::get_table_name();
        $token_id = absint($token_id);
        
        $sessions = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table_name} WHERE token_id = %d ORDER BY started_at DESC",
                $token_id
            ),
            OBJECT
        );
        
        return $sessions;
    }
    
    /**
     * Get active sessions by token ID
     *
     * @param int $token_id Token ID
     * @return array Array of active session objects
     */
    public static function get_active_by_token_id($token_id) {
        global $wpdb;
        
        $table_name = self::get_table_name();
        $token_id = absint($token_id);
        $current_time = current_time('mysql');
        $current_timestamp = current_time('timestamp');
        
        // Check test mode
        $test_mode_enabled = tba_get_setting('test_mode_enabled');
        $test_mode_condition = '';
        
        if ($test_mode_enabled === 'yes') {
            // In test mode, sessions expire after 1 minute (60 seconds)
            $one_minute_ago = date('Y-m-d H:i:s', $current_timestamp - 60);
            $test_mode_condition = " AND (started_at IS NULL OR started_at > %s)";
        }
        
        if ($test_mode_enabled === 'yes') {
            $sessions = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT * FROM {$table_name} WHERE token_id = %d AND is_blocked = 0 AND (expires_at IS NULL OR expires_at > %s)" . $test_mode_condition . " ORDER BY started_at DESC",
                    $token_id,
                    $current_time,
                    $one_minute_ago
                ),
                OBJECT
            );
        } else {
            $sessions = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT * FROM {$table_name} WHERE token_id = %d AND is_blocked = 0 AND (expires_at IS NULL OR expires_at > %s) ORDER BY started_at DESC",
                    $token_id,
                    $current_time
                ),
                OBJECT
            );
        }
        
        return $sessions;
    }
    
    /**
     * Get session by fingerprint
     *
     * @param string $fingerprint Fingerprint string
     * @param bool $expired_only If true, only return expired sessions
     * @return object|null Session object or null if not found
     */
    public static function get_by_fingerprint($fingerprint, $expired_only = false) {
        global $wpdb;
        
        $table_name = self::get_table_name();
        $current_time = current_time('mysql');
        $current_timestamp = current_time('timestamp');
        
        // Check test mode
        $test_mode_enabled = tba_get_setting('test_mode_enabled');
        
        if ( ! $expired_only ) {
            if ($test_mode_enabled === 'yes') {
                // In test mode, sessions expire after 1 minute (60 seconds)
                $one_minute_ago = date('Y-m-d H:i:s', $current_timestamp - 60);
                $session = $wpdb->get_row(
                    $wpdb->prepare(
                        "SELECT * FROM {$table_name} WHERE fingerprint = %s AND (expires_at IS NULL OR expires_at > %s) AND (started_at IS NULL OR started_at > %s) AND is_blocked = 0 ORDER BY started_at DESC LIMIT 1",
                        sanitize_text_field($fingerprint),
                        $current_time,
                        $one_minute_ago
                    ),
                    OBJECT
                );
            } else {
                $session = $wpdb->get_row(
                    $wpdb->prepare(
                        "SELECT * FROM {$table_name} WHERE fingerprint = %s AND (expires_at IS NULL OR expires_at > %s) AND is_blocked = 0 ORDER BY started_at DESC LIMIT 1",
                        sanitize_text_field($fingerprint),
                        $current_time
                    ),
                    OBJECT
                );
            }
        } else {
            if ($test_mode_enabled === 'yes') {
                // In test mode, sessions expire after 1 minute (60 seconds)
                $one_minute_ago = date('Y-m-d H:i:s', $current_timestamp - 60);
                $session = $wpdb->get_row(
                    $wpdb->prepare(
                        "SELECT * FROM {$table_name} WHERE fingerprint = %s AND ((expires_at IS NOT NULL AND expires_at < %s) OR (started_at IS NOT NULL AND started_at <= %s)) AND is_blocked = 0 ORDER BY started_at DESC LIMIT 1",
                        sanitize_text_field($fingerprint),
                        $current_time,
                        $one_minute_ago
                    ),
                    OBJECT
                );
            } else {
                $session = $wpdb->get_row(
                    $wpdb->prepare(
                        "SELECT * FROM {$table_name} WHERE fingerprint = %s AND (expires_at < %s) AND is_blocked = 0 ORDER BY started_at DESC LIMIT 1",
                        sanitize_text_field($fingerprint),
                        $current_time
                    ),
                    OBJECT
                );
            }
        }
        
        return $session ? $session : null;
    }

    /**
     * Get session by fingerprint where is_blocked is 1
     *
     * @param string $fingerprint Fingerprint string
     * @return object|null Session object or null if not found
     */
    public static function get_by_fingerprint_and_blocked($fingerprint) {
        global $wpdb;
        
        $table_name = self::get_table_name();
        
        $session = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$table_name} WHERE fingerprint = %s AND is_blocked = 1 ORDER BY started_at DESC LIMIT 1",
                sanitize_text_field($fingerprint)
            ),
            OBJECT
        );
        
        return $session ? $session : null;
    }

    /**
     * Create a new session
     *
     * @param array $data Session data
     * @return int|false Session ID on success, false on failure
     */
    public static function create($data) {
        global $wpdb;
        
        $table_name = self::get_table_name();
        
        // Prepare data with defaults
        $defaults = array(
            'token_id' => 0,
            'started_at' => null,
            'expires_at' => null,
            'fingerprint' => '',
            'is_blocked' => 0,
            'ip_address' => '',
            'user_agent' => '',
        );
        
        $data = wp_parse_args($data, $defaults);
        
        // Sanitize data
        $insert_data = array(
            'token_id' => absint($data['token_id']),
            'started_at' => $data['started_at'] ? sanitize_text_field($data['started_at']) : current_time('mysql'),
            'expires_at' => $data['expires_at'] ? sanitize_text_field($data['expires_at']) : null,
            'fingerprint' => sanitize_text_field($data['fingerprint']),
            'is_blocked' => absint($data['is_blocked']),
            'ip_address' => sanitize_text_field($data['ip_address']),
            'user_agent' => sanitize_text_field($data['user_agent']),
        );
        
        // Validate required fields
        if (empty($insert_data['token_id'])) {
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
     * Update session
     *
     * @param int $session_id Session ID
     * @param array $data Data to update
     * @return bool True on success, false on failure
     */
    public static function update($session_id, $data) {
        global $wpdb;
        
        $table_name = self::get_table_name();
        $session_id = absint($session_id);
        
        // Prepare update data
        $update_data = array();
        
        $allowed_fields = array(
            'token_id',
            'started_at',
            'expires_at',
            'fingerprint',
            'is_blocked',
            'ip_address',
            'user_agent',
        );
        
        foreach ($allowed_fields as $field) {
            if (isset($data[$field])) {
                if (in_array($field, array('started_at', 'expires_at'))) {
                    $update_data[$field] = $data[$field] ? sanitize_text_field($data[$field]) : null;
                } elseif (in_array($field, array('token_id', 'is_blocked'))) {
                    $update_data[$field] = absint($data[$field]);
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
            array('session_id' => $session_id),
            null,
            array('%d')
        );
        
        return $result !== false;
    }
    
    /**
     * Delete session
     *
     * @param int $session_id Session ID
     * @return bool True on success, false on failure
     */
    public static function delete($session_id) {
        global $wpdb;
        
        $table_name = self::get_table_name();
        $session_id = absint($session_id);
        
        $result = $wpdb->delete(
            $table_name,
            array('session_id' => $session_id),
            array('%d')
        );
        
        return $result !== false;
    }
    
    /**
     * Delete all sessions for a token
     *
     * @param int $token_id Token ID
     * @return bool True on success, false on failure
     */
    public static function delete_by_token_id($token_id) {
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
     * Block session
     *
     * @param int $session_id Session ID
     * @return bool True on success, false on failure
     */
    public static function block($session_id) {
        return self::update($session_id, array('is_blocked' => 1));
    }

     /**
     * Block all sessions for a token
     *
     * @param int $token_id Token ID
     * @return bool True on success, false on failure
     */
    public static function block_by_token_id($token_id) {
        global $wpdb;
        
        $table_name = self::get_table_name();
        $token_id = absint($token_id);
        
        $result = $wpdb->update(
            $table_name,
            array('is_blocked' => 1),
            array('token_id' => $token_id),
            array('%d')
        );
    }
    
    /**
     * Unblock session
     *
     * @param int $session_id Session ID
     * @return bool True on success, false on failure
     */
    public static function unblock($session_id) {
        return self::update($session_id, array('is_blocked' => 0));
    }
    
    /**
     * Check if session is expired
     *
     * @param object|int $session Session object or session ID
     * @return bool True if expired, false otherwise
     */
    public static function is_session_expired($session) {
        if (is_numeric($session)) {
            $session = self::get_by_id($session);
        }
        
        if (!$session) {
            return true;
        }
        
        // If blocked, consider it expired
        if ($session->is_blocked == 1) {
            return true;
        }
        
        // Check test mode: if enabled, sessions expire after 1 minute
        $test_mode_enabled = tba_get_setting('test_mode_enabled');
        if ($test_mode_enabled === 'yes') {
            if (!empty($session->started_at)) {
                $started_time = strtotime($session->started_at);
                $current_time = current_time('timestamp');
                // If session was started more than 1 minute ago, consider it expired
                if (($current_time - $started_time) > 60) {
                    return true;
                }
            }
        }
        
        // If no expiration time, session never expires
        if (empty($session->expires_at)) {
            return false;
        }
        
        // Check if expiration time has passed
        $expires_time = strtotime($session->expires_at);
        $current_time = current_time('timestamp');
        
        return $current_time > $expires_time;
    }
    
    /**
     * Get all sessions with pagination
     *
     * @param array $args Query arguments
     * @return array Array with 'sessions' and 'total' count
     */
    public static function get_all($args = array()) {
        global $wpdb;
        
        $table_name = self::get_table_name();
        
        $defaults = array(
            'per_page' => 20,
            'page' => 1,
            'orderby' => 'started_at',
            'order' => 'DESC',
            'token_id' => null,
            'is_blocked' => null,
        );
        
        $args = wp_parse_args($args, $defaults);
        
        $where = array();
        $where_values = array();
        
        if ($args['token_id'] !== null && $args['token_id'] !== '') {
            $where[] = 'token_id = %d';
            $where_values[] = absint($args['token_id']);
        }
        
        if ($args['is_blocked'] !== null) {
            $where[] = 'is_blocked = %d';
            $where_values[] = absint($args['is_blocked']);
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
        
        // Get sessions with pagination
        $offset = ($args['page'] - 1) * $args['per_page'];
        $orderby = sanitize_sql_orderby($args['orderby'] . ' ' . $args['order']);
        
        if (!$orderby) {
            $orderby = 'started_at DESC';
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
        
        $sessions = $wpdb->get_results($query, OBJECT);
        
        return array(
            'sessions' => $sessions,
            'total' => (int) $total,
        );
    }
}
