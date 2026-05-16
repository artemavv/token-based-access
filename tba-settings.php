<?php
/**
 * Token-based access settings
 *
 * @package TokenBasedAccess
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Add Tokens tab to WooCommerce settings
 *
 * @param array $tabs Existing settings tabs
 * @return array Modified tabs array
 */
function tba_add_settings_tab($tabs) {
    $tabs['tba_tokens'] = __('Tokens', TBA_TEXTDOMAIN);
    return $tabs;
}
add_filter('woocommerce_settings_tabs_array', 'tba_add_settings_tab', 50);

/**
 * Get list of WooCommerce products for dropdown
 *
 * @return array Array of product ID => product name
 */
function tba_get_products_list() {
    $products = wc_get_products(array(
        'status' => 'publish',
        'limit'  => -1,
        'orderby' => 'title',
        'order'   => 'ASC',
    ));

    $products_list = array('' => __('Select a product', TBA_TEXTDOMAIN));

    foreach ($products as $product) {
        $products_list[$product->get_id()] = $product->get_name();
    }

    return $products_list;
}

/**
 * Get list of all registered post types for dropdown
 *
 * @return array Array of post type slug => post type label
 */
function tba_get_post_types_list() {
    $post_types = get_post_types(array(), 'objects');
    
    $post_types_list = array('' => __('Select a post type for ads', TBA_TEXTDOMAIN));
    
    foreach ($post_types as $post_type_slug => $post_type_object) {
        // Skip built-in private post types like nav_menu_item, revision, etc.
        if ($post_type_object->_builtin && !$post_type_object->public) {
            continue;
        }
        
        $label = $post_type_object->label ? $post_type_object->label : $post_type_object->name;
        $post_types_list[$post_type_slug] = $label;
    }
    
    // Sort alphabetically by label
    asort($post_types_list);
    
    // Ensure empty option is first
    $empty_option = array('' => __('Select a post type', TBA_TEXTDOMAIN));
    $post_types_list = $empty_option + $post_types_list;
    
    return $post_types_list;
}

/**
 * Get settings for Tokens tab
 *
 * @return array Settings array
 */
function tba_get_settings() {
    $settings = array(
        array(
            'title' => __('Token Settings', TBA_TEXTDOMAIN),
            'type'  => 'title',
            'desc'  => __('Configure token-based access settings.', TBA_TEXTDOMAIN),
            'id'    => 'tba_settings_title',
        ),
        array(
            'title'    => __('Hours of access', TBA_TEXTDOMAIN),
            'desc'     => __('Number of hours to be provided by token(1-1000).', TBA_TEXTDOMAIN),
            'id'       => 'tba_hours_of_access',
            'type'     => 'number',
            'default'  => 24,
            'css'      => 'width: 100px;',
            'custom_attributes' => array(
                'min'  => 1,
                'max'  => 1000,
                'step' => 1,
            ),
        ),
        array(
            'title'    => __('Product', TBA_TEXTDOMAIN),
            'desc'     => __('Select a WooCommerce product to be used as a token product.', TBA_TEXTDOMAIN),
            'id'       => 'tba_selected_product',
            'type'     => 'select',
            'class'    => 'wc-enhanced-select',
            'css'      => 'min-width: 300px;',
            'options'  => tba_get_products_list(),
            'default'  => '',
        ),
        array(
            'title'    => __('Meta field for owner name', TBA_TEXTDOMAIN),
            'id'       => 'tba_meta_field_owner',
            'type'     => 'text',
            'default'  => '',
            'css'      => 'min-width: 300px;',
        ),
        array(
            'title'    => __('Meta field for owner email', TBA_TEXTDOMAIN),
            'id'       => 'tba_meta_field_email',
            'type'     => 'text',
            'default'  => '',
            'css'      => 'min-width: 300px;',
        ),
        array(
            'title'    => __('Meta field for owner phone', TBA_TEXTDOMAIN),
            'id'       => 'tba_meta_field_phone',
            'type'     => 'text',
            'default'  => '',
            'css'      => 'min-width: 300px;',
        ),
        array(
            'title'    => __('Post type for ads', TBA_TEXTDOMAIN),
            'desc'     => __('Select the post type used for ads.', TBA_TEXTDOMAIN),
            'id'       => 'tba_post_type_for_ads',
            'type'     => 'select',
            'class'    => 'wc-enhanced-select',
            'css'      => 'min-width: 300px;',
            'options'  => tba_get_post_types_list(),
            'default'  => '',
        ),
        array(
            'title'    => __('Test mode enabled', TBA_TEXTDOMAIN),
            'desc'     => __('Enable test mode. When enabled, all tokens are evaluated as expired if they have been created more than 1 minute ago.', TBA_TEXTDOMAIN),
            'id'       => 'tba_test_mode_enabled',
            'type'     => 'checkbox',
            'default'  => 'no',
        ),
        array(
            'title'    => __('Token parameter name', TBA_TEXTDOMAIN),
            'desc'     => __('Name of the parameter for token in URLs.', TBA_TEXTDOMAIN),
            'id'       => 'tba_token_parameter_name',
            'type'     => 'text',
            'default'  => 't',
            'css'      => 'min-width: 300px;',
        ),
        array(
            'title'    => __('Access details template', TBA_TEXTDOMAIN),
            'desc'     => __('Template for access details. Available placeholders: {{owner_name}}, {{owner_email}}, {{owner_phone}}.', TBA_TEXTDOMAIN),
            'id'       => 'tba_access_details_template',
            'type'     => 'textarea',
            'default'  => '',
            'css'      => 'min-width: 500px; min-height: 150px;',
        ),
        array(
            'title'    => __('Message not allowed to view', TBA_TEXTDOMAIN),
            'desc'     => __('Message displayed when user is not allowed to view content.', TBA_TEXTDOMAIN),
            'id'       => 'tba_message_not_allowed_to_view',
            'type'     => 'textarea',
            'default'  => '',
            'css'      => 'min-width: 500px; min-height: 150px;',
        ),
        array(
            'title'    => __('Message when access expired', TBA_TEXTDOMAIN),
            'desc'     => __('Message displayed when access to content has expired.', TBA_TEXTDOMAIN),
            'id'       => 'tba_message_access_expired',
            'type'     => 'textarea',
            'default'  => '',
            'css'      => 'min-width: 500px; min-height: 150px;',
        ),
        array(
            'title'    => __('Message when access blocked', TBA_TEXTDOMAIN),
            'desc'     => __('Message displayed when access to content is blocked (token was used on another device)', TBA_TEXTDOMAIN),
            'id'       => 'tba_message_access_blocked',
            'type'     => 'textarea',
            'default'  => '',
            'css'      => 'min-width: 500px; min-height: 150px;',
        ),
        array(
            'title'    => __('Email subject', TBA_TEXTDOMAIN),
            'desc'     => __('Subject line for the token email. Available placeholders: {{order_id}}, {{token_string}}, {{customer_full_name}}, {{customer_first_name}}, {{expiration_date}}.', TBA_TEXTDOMAIN),
            'id'       => 'tba_email_subject',
            'type'     => 'text',
            'default'  => '',
            'css'      => 'min-width: 500px;',
        ),
        array(
            'title'    => __('Email template', TBA_TEXTDOMAIN),
            'desc'     => __('Email template for token delivery. Available placeholders: {{order_id}}, {{token_string}}, {{customer_full_name}}, {{customer_first_name}}, {{expiration_date}}, {{token_parameter}}', TBA_TEXTDOMAIN),
            'id'       => 'tba_email_template',
            'type'     => 'textarea',
            'default'  => '',
            'css'      => 'min-width: 500px; min-height: 150px;',
        ),
        array(
            'type' => 'sectionend',
            'id'   => 'tba_settings_section_end',
        ),
    );

    return apply_filters('tba_settings', $settings);
}

/**
 * Output settings for Tokens tab
 */
function tba_output_settings() {
    global $current_tab;
    
    if ('tba_tokens' === $current_tab) {
        WC_Admin_Settings::output_fields(tba_get_settings());
    }
}
add_action('woocommerce_settings_tba_tokens', 'tba_output_settings');

/**
 * Save settings for Tokens tab
 */
function tba_save_settings() {
    global $current_tab;
    
    if ('tba_tokens' === $current_tab) {
        // Save settings using WooCommerce settings API
        WC_Admin_Settings::save_fields(tba_get_settings());
        
        // Get the hours of access value and validate
        $hours_of_access = isset($_POST['tba_hours_of_access']) ? intval($_POST['tba_hours_of_access']) : 24;
        
        // Validate the value (1-1000)
        if ($hours_of_access < 1) {
            $hours_of_access = 1;
        } elseif ($hours_of_access > 1000) {
            $hours_of_access = 1000;
        }
        
        // Save the validated option
        update_option('tba_hours_of_access', $hours_of_access);
    }
}
add_action('woocommerce_settings_save_tba_tokens', 'tba_save_settings');

/**
 * Get setting value by setting name
 *
 * @param string $setting_name Setting name (e.g., 'hours_of_access', 'selected_product', 'meta_field_owner', 'post_type_for_ads', 'access_details_template', 'message_not_allowed_to_view', 'message_access_expired', 'message_access_blocked', 'email_subject', 'email_template', 'test_mode_enabled', 'token_parameter_name')
 * @return mixed Setting value or default value
 */
function tba_get_setting($setting_name) {
    // Map setting names to option names and defaults
    $settings_map = array(
        'hours_of_access' => array(
            'option' => 'tba_hours_of_access',
            'default' => 24,
        ),
        'selected_product' => array(
            'option' => 'tba_selected_product',
            'default' => '',
        ),
        'post_type_for_ads' => array(
            'option' => 'tba_post_type_for_ads',
            'default' => '',
        ),
        'access_details_template' => array(
            'option' => 'tba_access_details_template',
            'default' => '',
        ),
        'message_not_allowed_to_view' => array(
            'option' => 'tba_message_not_allowed_to_view',
            'default' => '',
        ),
        'message_access_expired' => array(
            'option' => 'tba_message_access_expired',
            'default' => '',
        ),
        'message_access_blocked' => array(
            'option' => 'tba_message_access_blocked',
            'default' => '',
        ),
        'email_subject' => array(
            'option' => 'tba_email_subject',
            'default' => '',
        ),
        'email_template' => array(
            'option' => 'tba_email_template',
            'default' => '',
        ),
        'test_mode_enabled' => array(
            'option' => 'tba_test_mode_enabled',
            'default' => 'no',
        ),
        'token_parameter_name' => array(
            'option' => 'tba_token_parameter_name',
            'default' => 't',
        ),
    );
    
    // Handle meta fields (owner, email, phone)
    if (strpos($setting_name, 'meta_field_') === 0) {
        $field_name = str_replace('meta_field_', '', $setting_name);
        $valid_fields = array('owner', 'email', 'phone');
        
        if (in_array($field_name, $valid_fields, true)) {
            $option_name = 'tba_meta_field_' . $field_name;
            return get_option($option_name, '');
        }
        
        return '';
    }
    
    // Handle regular settings
    if (isset($settings_map[$setting_name])) {
        $value = get_option($settings_map[$setting_name]['option'], $settings_map[$setting_name]['default']);
        
        // Special validation for hours_of_access
        if ($setting_name === 'hours_of_access') {
            $value = intval($value);
            if ($value < 1) {
                $value = 1;
            } elseif ($value > 1000) {
                $value = 1000;
            }
        }
        
        return $value;
    }
    
    // Return empty string for unknown settings
    return '';
}
