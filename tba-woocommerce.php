<?php
/**
 * WooCommerce integration for Token-based access
 *
 * @package TokenBasedAccess
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Check if hide address fields feature is enabled
 *
 * @return bool True if enabled, false otherwise    
 */
function tba_is_hide_address_fields_enabled() {

    return true;
    return get_option('tba_hide_address_fields', 'no') === 'yes';
}

/**
 * Make all address fields optional and hide them on checkout
 *
 * @param array $fields Checkout fields
 * @return array Modified checkout fields
 */
function tba_modify_checkout_address_fields($fields) {
    // Only modify if feature is enabled
    if (!tba_is_hide_address_fields_enabled()) {
        return $fields;
    }

    // Address fields to hide (billing and shipping)
    $address_fields = array(
        'billing_address_1',
        'billing_address_2',
        'billing_city',
        'billing_state',
        'billing_postcode',
        'billing_country',
        'shipping_address_1',
        'shipping_address_2',
        'shipping_city',
        'shipping_state',
        'shipping_postcode',
        'shipping_country',
    );

    // Make billing address fields optional and hide them
    if (isset($fields['billing'])) {
        foreach ($address_fields as $field_key) {
            if (isset($fields['billing'][$field_key])) {
                $fields['billing'][$field_key]['required'] = false;
                $fields['billing'][$field_key]['class'][] = 'tba-hidden-field';
            }
        }
    }

    // Make shipping address fields optional and hide them
    if (isset($fields['shipping'])) {
        foreach ($address_fields as $field_key) {
            if (isset($fields['shipping'][$field_key])) {
                $fields['shipping'][$field_key]['required'] = false;
                $fields['shipping'][$field_key]['class'][] = 'tba-hidden-field';
            }
        }
    }

    return $fields;
}
add_filter('woocommerce_checkout_fields', 'tba_modify_checkout_address_fields', 999);

/**
 * Skip validation for hidden address fields
 *
 * @param array $data Checkout posted data
 * @param WP_Error $errors Validation errors
 */
function tba_skip_address_fields_validation($data, $errors) {
    // Only skip validation if feature is enabled
    if (!tba_is_hide_address_fields_enabled()) {
        return;
    }

    // Address fields to skip validation
    $address_fields = array(
        'billing_address_1',
        'billing_address_2',
        'billing_city',
        'billing_state',
        'billing_postcode',
        'billing_country',
        'shipping_address_1',
        'shipping_address_2',
        'shipping_city',
        'shipping_state',
        'shipping_postcode',
        'shipping_country',
    );

    // Remove validation errors for hidden address fields
    foreach ($address_fields as $field_key) {
        // Remove errors by error code (field key)
        $errors->remove($field_key);
        // Also remove errors with field key in the error code
        $error_codes = $errors->get_error_codes();
        foreach ($error_codes as $error_code) {
            if (strpos($error_code, $field_key) !== false) {
                $errors->remove($error_code);
            }
        }
    }
}
add_action('woocommerce_after_checkout_validation', 'tba_skip_address_fields_validation', 10, 2);

/**
 * Create token when order contains the selected product
 *
 * @param int $order_id Order ID
 * @param array $data Order data
 */
function tba_create_token_on_order($order_id, $data) {
    // Get the selected product ID from settings
    $selected_product_id = tba_get_setting('selected_product');
    
    // If no product is selected, skip
    if (empty($selected_product_id)) {
        tba_log_debug('TBA: No product selected for order #' . $order_id);
        return;
    }
    
    // Get the order object
    $order = wc_get_order($order_id);
    if (!$order) {
        tba_log_debug('TBA: Order not found for ID: ' . $order_id);
        return;
    }
    
    // Check if token was already created for this order (to avoid duplicates)
    $existing_token = $order->get_meta('_tba_token_created');
    if ($existing_token) {
        tba_log_debug('TBA: Token already created for order #' . $order_id);
        return;
    }
    
    // Check if order contains the selected product
    $has_selected_product = false;
    foreach ($order->get_items() as $item) {
        // Only check product items
        if (!is_a($item, 'WC_Order_Item_Product')) {
            continue;
        }
        
        $product_id = $item->get_product_id();
        $variation_id = $item->get_variation_id();
        
        // Check if this item matches the selected product (including variations)
        if ($product_id == $selected_product_id || $variation_id == $selected_product_id) {
            $has_selected_product = true;
            break;
        }
    }
    
    // If order doesn't contain the selected product, skip
    if (!$has_selected_product) {
        tba_log_debug('TBA: Order #' . $order_id . ' does not contain the selected product');
        return;
    }
    
    // Get customer information
    $customer_email = $order->get_billing_email();
    $customer_ip = $order->get_customer_ip_address();
    $customer_user_agent = $order->get_customer_user_agent();
    
    // Validate email
    if (empty($customer_email) || !is_email($customer_email)) {
        tba_log_debug('TBA: Invalid customer email for order #' . $order_id);
        return;
    }
    
    // Get hours of access from settings
    $hours_allowed = tba_get_setting('hours_of_access');
    
    $token_string = tba_generate_token_string($customer_ip, $customer_user_agent, $customer_email);
 
    
    // Prepare token data
    $token_data = array(
        'order_id' => $order_id,
        'token_email' => $customer_email,
        'user_agent' => $customer_user_agent,
        'ip_address' => $customer_ip,
        'hours_allowed' => $hours_allowed,
        'is_blocked' => 0,
        'is_expired' => 0,
    );
    
    // Create the token
    $token_id = TBA_Token_Model::create($token_data);
    
    // Mark that token was created for this order
    if ($token_id !== false) {
        $order->update_meta_data('_tba_token_created', 'yes');
        $order->update_meta_data('_tba_token_id', $token_id);

        // Add note to the order
        $order->add_order_note(sprintf(__('Token created for order #%s: %s', TBA_TEXTDOMAIN), $order_id, $token_id));

        tba_send_token_email($token_id, $order_id);
        
        $order->save();
    } else {
        // Log if token creation failed (optional, for debugging)
        tba_log_debug('TBA: Failed to create token for order #' . $order_id);
    }
}

/**
 * Create token when order status changes to processing or completed (for admin/manual orders)
 * This ensures order items are fully saved before processing
 *
 * @param int $order_id Order ID
 * @param string $old_status Old order status
 * @param string $new_status New order status
 */
function tba_create_token_on_order_status_change($order_id, $old_status, $new_status) {
    // Only process when order moves to processing or completed status
    if (!in_array($new_status, array('processing', 'completed'))) {
        return;
    }
  
    $order = wc_get_order($order_id);
    if (!$order) {
        return;
    }
    
    // Process the order (duplicate check is handled in tba_create_token_on_order)
    tba_create_token_on_order($order_id, array());
    
}
add_action('woocommerce_order_status_changed', 'tba_create_token_on_order_status_change', 10, 3);

// Use WC_Looger to save log message
function tba_log_debug($message) {
    $logger = wc_get_logger();
    $logger->debug($message, array('source' => 'tba'));
}

/**
 * Add metabox to WooCommerce order page to display associated tokens
 * Works with both legacy post-based orders and HPOS (High-Performance Order Storage)
 */
function tba_add_order_tokens_metabox($post_type_or_screen_id, $post_or_order = null) {
    // Get current screen
    $screen = function_exists('get_current_screen') ? get_current_screen() : null;
    
    if (!$screen) {
        return;
    }
    
    $screen_id = $screen->id;
    
    // Use WooCommerce utility function if available (WooCommerce 7.9+)
    $is_order_edit_screen = false;
    if (class_exists('\Automattic\WooCommerce\Utilities\OrderUtil') && 
        method_exists('\Automattic\WooCommerce\Utilities\OrderUtil', 'is_order_edit_screen')) {
        $is_order_edit_screen = \Automattic\WooCommerce\Utilities\OrderUtil::is_order_edit_screen();
    }
    
    // Check if we're on a WooCommerce order edit screen (HPOS)
    // Screen IDs for HPOS: 'woocommerce_page_wc-orders', 'woocommerce_page_wc-orders-edit', etc.
    if ($is_order_edit_screen || strpos($screen_id, 'wc-orders') !== false || strpos($screen_id, 'woocommerce_page_wc-orders') !== false) {
        add_meta_box(
            'tba-order-tokens',
            __('Access Tokens', TBA_TEXTDOMAIN),
            'tba_render_order_tokens_metabox',
            $screen_id,
            'normal',
            'default'
        );
        return;
    }
    
    // Handle legacy post-based orders
    if ($screen_id === 'shop_order' || ($post_type_or_screen_id === 'shop_order' && $post_or_order instanceof WP_Post)) {
        add_meta_box(
            'tba-order-tokens',
            __('Access Tokens', TBA_TEXTDOMAIN),
            'tba_render_order_tokens_metabox',
            'shop_order',
            'normal',
            'default'
        );
    }
}
add_action('add_meta_boxes', 'tba_add_order_tokens_metabox', 10, 2);

/**
 * Also hook into the specific screen hooks for HPOS orders
 * WooCommerce fires these hooks with the order object
 */
function tba_add_order_tokens_metabox_hpos($order) {
    if (!($order instanceof WC_Order)) {
        return;
    }
    
    $screen = function_exists('get_current_screen') ? get_current_screen() : null;
    if (!$screen) {
        return;
    }
    
    $screen_id = $screen->id;
    
    // Register metabox for HPOS screens
    if (strpos($screen_id, 'wc-orders') !== false || strpos($screen_id, 'woocommerce_page_wc-orders') !== false) {
        add_meta_box(
            'tba-order-tokens',
            __('Access Tokens', TBA_TEXTDOMAIN),
            'tba_render_order_tokens_metabox',
            $screen_id,
            'normal',
            'default'
        );
    }
}
// Hook into WooCommerce HPOS specific hooks
// Priority 20 to ensure WooCommerce has set up the screen
add_action('add_meta_boxes_woocommerce_page_wc-orders', 'tba_add_order_tokens_metabox_hpos', 20);
add_action('add_meta_boxes_woocommerce_page_wc-orders-edit', 'tba_add_order_tokens_metabox_hpos', 20);

/**
 * Render the tokens metabox content
 * Handles both WP_Post (legacy) and WC_Order (HPOS) objects
 *
 * @param WP_Post|WC_Order $post_or_order The post or order object
 */
function tba_render_order_tokens_metabox($post_or_order) {
    // Get order ID from either WP_Post or WC_Order
    if ($post_or_order instanceof WC_Order) {
        $order_id = $post_or_order->get_id();
    } elseif ($post_or_order instanceof WP_Post) {
        $order_id = $post_or_order->ID;
    } else {
        // Try to get ID from object
        $order_id = isset($post_or_order->ID) ? $post_or_order->ID : (isset($post_or_order->id) ? $post_or_order->id : 0);
    }
    
    if (empty($order_id)) {
        echo '<p>' . esc_html__('Unable to determine order ID.', TBA_TEXTDOMAIN) . '</p>';
        return;
    }
    
    // Get all tokens for this order
    $tokens_result = TBA_Token_Model::get_all(array(
        'order_id' => $order_id,
        'per_page' => 100, // Get all tokens for this order
        'page' => 1,
    ));
    
    $tokens = $tokens_result['tokens'];
    $total = $tokens_result['total'];
    
    if (empty($tokens)) {
        echo '<p>' . esc_html__('No tokens found for this order.', TBA_TEXTDOMAIN) . '</p>';
        return;
    }
    
    ?>
    <style>
        .tba-token-sessions {
            margin-top: 10px;
            padding: 10px;
            background: #f9f9f9;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .tba-sessions-list {
            margin: 0;
            padding-left: 20px;
        }
        .tba-session-item {
            padding: 5px 0;
            border-bottom: 1px solid #eee;
        }
        .tba-session-item:last-child {
            border-bottom: none;
        }
        .tba-session-status {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 3px;
            font-size: 11px;
            font-weight: 600;
            margin-left: 8px;
        }
        .tba-session-active {
            background: #d4edda;
            color: #155724;
        }
        .tba-session-blocked {
            background: #f8d7da;
            color: #721c24;
        }
        .tba-session-expired {
            background: #fff3cd;
            color: #856404;
        }
    </style>
    <div class="tba-order-tokens-metabox">
        <p><strong><?php echo esc_html(sprintf(__('Total tokens: %d', TBA_TEXTDOMAIN), $total)); ?></strong></p>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php esc_html_e('Token ID', TBA_TEXTDOMAIN); ?></th>
                    <th><?php esc_html_e('Email', TBA_TEXTDOMAIN); ?></th>
                    <th><?php esc_html_e('Status', TBA_TEXTDOMAIN); ?></th>
                    <th><?php esc_html_e('Started', TBA_TEXTDOMAIN); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($tokens as $token) : 
                    // Get sessions for this token
                    $sessions = TBA_Token_Session_Model::get_by_token_id($token->token_id);
                    
                    // Determine token status
                    $status = array();
                    if ($token->is_blocked == 1) {
                        $status[] = '<span style="color: #d63638;">' . esc_html__('Blocked', TBA_TEXTDOMAIN) . '</span>';
                    }
                    if ($token->is_expired == 1) {
                        $status[] = '<span style="color: #d63638;">' . esc_html__('Expired', TBA_TEXTDOMAIN) . '</span>';
                    }
                    if (empty($token->started_at)) {
                        $status[] = '<span style="color: #2271b1;">' . esc_html__('Not Started', TBA_TEXTDOMAIN) . '</span>';
                    } else {
                        // Check if token is actually expired based on hours_allowed
                        if (TBA_Token_Model::is_token_expired($token)) {
                            $status[] = '<span style="color: #d63638;">' . esc_html__('Time Expired', TBA_TEXTDOMAIN) . '</span>';
                        } else {
                            $status[] = '<span style="color: #00a32a;">' . esc_html__('Active', TBA_TEXTDOMAIN) . '</span>';
                        }
                    }
                    if (empty($status)) {
                        $status[] = '<span style="color: #00a32a;">' . esc_html__('Active', TBA_TEXTDOMAIN) . '</span>';
                    }
                    
                    // Format dates
                    $created_date = $token->created_at ? date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($token->created_at)) : '-';
                    $started_date = $token->started_at ? date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($token->started_at)) : '-';
                    
                    // Calculate expiration if started
                    $expiration_info = '';
                    if ($token->started_at && $token->hours_allowed > 0) {
                        $started_timestamp = strtotime($token->started_at);
                        $expiration_timestamp = $started_timestamp + ($token->hours_allowed * HOUR_IN_SECONDS);
                        $expiration_info = '<br><small>' . esc_html__('Expires:', TBA_TEXTDOMAIN) . ' ' . date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $expiration_timestamp) . '</small>';
                    } elseif ($token->hours_allowed == 0) {
                        $expiration_info = '<br><small>' . esc_html__('Never expires', TBA_TEXTDOMAIN) . '</small>';
                    }
                ?>
                    <tr>
                        <td><strong>#<?php echo esc_html($token->token_id); ?></strong></td>
                        <td><?php echo esc_html($token->token_email); ?></td>
                        <td><?php echo implode(', ', $status); ?></td>
                        <td><?php echo esc_html($started_date); ?><?php echo $expiration_info; ?></td>
                    </tr>
                    <?php if (!empty($sessions)) : ?>
                        <tr>
                            <td colspan="4" style="padding: 0;">
                                <div class="tba-token-sessions">
                                    <strong><?php echo esc_html(sprintf(__('Sessions (%d):', TBA_TEXTDOMAIN), count($sessions))); ?></strong>
                                    <ul class="tba-sessions-list">
                                        <?php foreach ($sessions as $session) : 
                                            // Determine session status
                                            $session_status_class = 'tba-session-active';
                                            $session_status_text = __('Active', TBA_TEXTDOMAIN);
                                            
                                            if ($session->is_blocked == 1) {
                                                $session_status_class = 'tba-session-blocked';
                                                $session_status_text = __('Blocked', TBA_TEXTDOMAIN);
                                            } elseif (TBA_Token_Session_Model::is_session_expired($session)) {
                                                $session_status_class = 'tba-session-expired';
                                                $session_status_text = __('Expired', TBA_TEXTDOMAIN);
                                            }
                                            
                                            // Format dates
                                            $session_started = $session->started_at ? date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($session->started_at)) : '-';
                                            $session_expires = $session->expires_at ? date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($session->expires_at)) : __('Never', TBA_TEXTDOMAIN);
                                            
                                            // Truncate user agent if too long
                                            $user_agent = esc_html($session->user_agent);
                                            if (strlen($user_agent) > 157) {
                                                $user_agent = substr($user_agent, 0, 157) . '...';
                                            }
                                        ?>
                                            <li class="tba-session-item">
                                                <strong>Session #<?php echo esc_html($session->session_id); ?></strong>
                                                <span class="tba-session-status <?php echo esc_attr($session_status_class); ?>">
                                                    <?php echo esc_html($session_status_text); ?>
                                                </span>
                                                <br>
                                                <small>
                                                    <?php esc_html_e('Started:', TBA_TEXTDOMAIN); ?> <?php echo esc_html($session_started); ?> | 
                                                    <?php esc_html_e('Expires:', TBA_TEXTDOMAIN); ?> <?php echo esc_html($session_expires); ?>
                                                    <?php if (!empty($session->ip_address)) : ?>
                                                        | <?php esc_html_e('IP:', TBA_TEXTDOMAIN); ?> <?php echo esc_html($session->ip_address); ?>
                                                    <?php endif; ?>
                                                    <?php if (!empty($session->user_agent)) : ?>
                                                        <br><?php esc_html_e('User Agent:', TBA_TEXTDOMAIN); ?> <?php echo $user_agent; ?>
                                                    <?php endif; ?>
                                                </small>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php
}