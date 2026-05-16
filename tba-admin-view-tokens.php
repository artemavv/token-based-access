<?php
/**
 * Admin page for viewing access tokens
 *
 * @package TokenBasedAccess
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Register admin menu page under Tools
 */
function tba_add_admin_menu() {
    add_management_page(
        __('Access tokens', TBA_TEXTDOMAIN),
        __('Access tokens', TBA_TEXTDOMAIN),
        'manage_options',
        'tba-access-tokens',
        'tba_render_tokens_page'
    );
}
add_action('admin_menu', 'tba_add_admin_menu');

/**
 * Render the tokens admin page
 */
function tba_render_tokens_page() {
    // Check user capabilities
    if (!current_user_can('manage_options')) {
        wp_die(__('You do not have sufficient permissions to access this page.', TBA_TEXTDOMAIN));
    }

    // Handle actions (delete, block, unblock, activate, deactivate)
    if (isset($_GET['action']) && isset($_GET['token_id']) && check_admin_referer('tba_token_action')) {
        $token_id = absint($_GET['token_id']);
        $action = sanitize_text_field($_GET['action']);
        
        switch ($action) {
            case 'delete':
                if (TBA_Token_Model::delete($token_id)) {
                    echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Token deleted successfully.', TBA_TEXTDOMAIN) . '</p></div>';
                } else {
                    echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__('Failed to delete token.', TBA_TEXTDOMAIN) . '</p></div>';
                }
                break;
            case 'block':
                if (TBA_Token_Model::block($token_id)) {
                    echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Token blocked successfully.', TBA_TEXTDOMAIN) . '</p></div>';
                } else {
                    echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__('Failed to block token.', TBA_TEXTDOMAIN) . '</p></div>';
                }
                break;
            case 'unblock':
                if (TBA_Token_Model::unblock($token_id)) {
                    echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Token unblocked successfully.', TBA_TEXTDOMAIN) . '</p></div>';
                } else {
                    echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__('Failed to unblock token.', TBA_TEXTDOMAIN) . '</p></div>';
                }
                break;
            case 'activate':
                if (TBA_Token_Model::activate($token_id)) {
                    echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Token activated successfully.', TBA_TEXTDOMAIN) . '</p></div>';
                } else {
                    echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__('Failed to activate token.', TBA_TEXTDOMAIN) . '</p></div>';
                }
                break;
            case 'deactivate':
                if (TBA_Token_Model::deactivate($token_id)) {
                    echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Token deactivated successfully.', TBA_TEXTDOMAIN) . '</p></div>';
                } else {
                    echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__('Failed to deactivate token.', TBA_TEXTDOMAIN) . '</p></div>';
                }
                break;
        }
    }

    // Get pagination parameters
    $per_page = isset($_GET['per_page']) ? absint($_GET['per_page']) : 20;
    $current_page = isset($_GET['paged']) ? absint($_GET['paged']) : 1;
    
    // Get filter parameters
    $filter_email = isset($_GET['filter_email']) ? sanitize_email($_GET['filter_email']) : '';
    $filter_blocked = isset($_GET['filter_blocked']) && $_GET['filter_blocked'] !== '' ? absint($_GET['filter_blocked']) : null;
    $filter_expired = isset($_GET['filter_expired']) && $_GET['filter_expired'] !== '' ? absint($_GET['filter_expired']) : null;
    
    // Build query args
    $query_args = array(
        'per_page' => $per_page,
        'page' => $current_page,
        'orderby' => 'created_at',
        'order' => 'DESC',
    );
    
    if (!empty($filter_email)) {
        $query_args['email'] = $filter_email;
    }
    if ($filter_blocked !== null) {
        $query_args['is_blocked'] = $filter_blocked;
    }
    if ($filter_expired !== null) {
        $query_args['is_expired'] = $filter_expired;
    }
    
    // Get tokens
    $tokens_data = TBA_Token_Model::get_all($query_args);
    $tokens = $tokens_data['tokens'];
    $total_tokens = $tokens_data['total'];
    $total_pages = ceil($total_tokens / $per_page);
    
    ?>
    <div class="wrap">
        <h1><?php echo esc_html__('Access tokens', TBA_TEXTDOMAIN); ?></h1>
        
        <div class="tba-tokens-filters" style="margin: 20px 0;">
            <form method="get" action="">
                <input type="hidden" name="page" value="tba-access-tokens">
                
                <div style="display: flex; gap: 10px; align-items: flex-end; flex-wrap: wrap;">
                    <div>
                        <label for="filter_email"><?php esc_html_e('Email:', TBA_TEXTDOMAIN); ?></label><br>
                        <input type="email" id="filter_email" name="filter_email" value="<?php echo esc_attr($filter_email); ?>" style="width: 200px;">
                    </div>
                    
                    <div>
                        <label for="filter_blocked"><?php esc_html_e('Blocked:', TBA_TEXTDOMAIN); ?></label><br>
                        <select id="filter_blocked" name="filter_blocked" style="width: 150px;">
                            <option value=""><?php esc_html_e('All', TBA_TEXTDOMAIN); ?></option>
                            <option value="1" <?php selected($filter_blocked, 1); ?>><?php esc_html_e('Yes', TBA_TEXTDOMAIN); ?></option>
                            <option value="0" <?php selected($filter_blocked, 0); ?>><?php esc_html_e('No', TBA_TEXTDOMAIN); ?></option>
                        </select>
                    </div>
                    
                    <div>
                        <label for="filter_expired"><?php esc_html_e('Expired:', TBA_TEXTDOMAIN); ?></label><br>
                        <select id="filter_expired" name="filter_expired" style="width: 150px;">
                            <option value=""><?php esc_html_e('All', TBA_TEXTDOMAIN); ?></option>
                            <option value="1" <?php selected($filter_expired, 1); ?>><?php esc_html_e('Yes', TBA_TEXTDOMAIN); ?></option>
                            <option value="0" <?php selected($filter_expired, 0); ?>><?php esc_html_e('No', TBA_TEXTDOMAIN); ?></option>
                        </select>
                    </div>
                    
                    <div>
                        <input type="submit" class="button" value="<?php esc_attr_e('Filter', TBA_TEXTDOMAIN); ?>">
                        <?php if ($filter_email || $filter_blocked !== null || $filter_expired !== null): ?>
                            <a href="<?php echo esc_url(admin_url('tools.php?page=tba-access-tokens')); ?>" class="button"><?php esc_html_e('Clear', TBA_TEXTDOMAIN); ?></a>
                        <?php endif; ?>
                    </div>
                </div>
            </form>
        </div>
        
        <div class="tba-tokens-table-wrapper">
            <?php if (empty($tokens)): ?>
                <p><?php esc_html_e('No tokens found.', TBA_TEXTDOMAIN); ?></p>
            <?php else: ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th scope="col" style="width: 60px;"><?php esc_html_e('ID', TBA_TEXTDOMAIN); ?></th>
                            <th scope="col"><?php esc_html_e('Email', TBA_TEXTDOMAIN); ?></th>
                            <th scope="col" style="width: 120px;"><?php esc_html_e('Hours Allowed', TBA_TEXTDOMAIN); ?></th>
                            <th scope="col" style="width: 150px;"><?php esc_html_e('Created', TBA_TEXTDOMAIN); ?></th>
                            <th scope="col" style="width: 150px;"><?php esc_html_e('Started', TBA_TEXTDOMAIN); ?></th>
                            <th scope="col" style="width: 100px;"><?php esc_html_e('Status', TBA_TEXTDOMAIN); ?></th>
                            <th scope="col" style="width: 120px;"><?php esc_html_e('IP Address', TBA_TEXTDOMAIN); ?></th>
                            <th scope="col" style="width: 200px;"><?php esc_html_e('Actions', TBA_TEXTDOMAIN); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($tokens as $token): ?>
                            <?php
                            $is_expired = TBA_Token_Model::is_token_expired($token);
                            $status_labels = array();
                            
                            if ($token->is_blocked == 1) {
                                $status_labels[] = '<span style="color: #dc3232;">' . esc_html__('Blocked', TBA_TEXTDOMAIN) . '</span>';
                            } elseif ($is_expired || $token->is_expired == 1) {
                                $status_labels[] = '<span style="color: #f56e28;">' . esc_html__('Expired', TBA_TEXTDOMAIN) . '</span>';
                            } elseif (!empty($token->started_at)) {
                                $status_labels[] = '<span style="color: #46b450;">' . esc_html__('Started', TBA_TEXTDOMAIN) . '</span>';
                            } else {
                                $status_labels[] = '<span style="color: #999;">' . esc_html__('Not Started', TBA_TEXTDOMAIN) . '</span>';
                            }
                            
                            $status_html = implode(', ', $status_labels);
                            
                            $created_at = $token->created_at ? date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($token->created_at)) : '-';
                            $started_at = $token->started_at ? date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($token->started_at)) : '-';
                            
                            $action_url = admin_url('tools.php?page=tba-access-tokens');
                            $action_url = add_query_arg(array(
                                'action' => 'ACTION_PLACEHOLDER',
                                'token_id' => $token->token_id,
                                '_wpnonce' => wp_create_nonce('tba_token_action')
                            ), $action_url);
                            ?>
                            <tr>
                                <td><?php echo esc_html($token->token_id); ?></td>
                                <td><strong><?php echo esc_html($token->token_email); ?></strong></td>
                                <td><?php echo esc_html($token->hours_allowed); ?></td>
                                <td><?php echo esc_html($created_at); ?></td>
                                <td><?php echo esc_html($started_at); ?></td>
                                <td><?php echo $status_html; ?></td>
                                <td><?php echo esc_html($token->ip_address); ?></td>
                                <td>
                                    <div style="display: flex; gap: 5px; flex-wrap: wrap;">
                                        
                                        <?php if ($token->is_blocked == 0): ?>
                                            <a href="<?php echo esc_url(str_replace('ACTION_PLACEHOLDER', 'block', $action_url)); ?>" class="button button-small" style="color: #dc3232;"><?php esc_html_e('Block', TBA_TEXTDOMAIN); ?></a>
                                        <?php else: ?>
                                            <a href="<?php echo esc_url(str_replace('ACTION_PLACEHOLDER', 'unblock', $action_url)); ?>" class="button button-small"><?php esc_html_e('Unblock', TBA_TEXTDOMAIN); ?></a>
                                        <?php endif; ?>
                                        
                                        <a href="<?php echo esc_url(str_replace('ACTION_PLACEHOLDER', 'delete', $action_url)); ?>" class="button button-small button-link-delete" onclick="return confirm('<?php echo esc_js(__('Are you sure you want to delete this token?', TBA_TEXTDOMAIN)); ?>');"><?php esc_html_e('Delete', TBA_TEXTDOMAIN); ?></a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <?php if ($total_pages > 1): ?>
                    <div class="tablenav">
                        <div class="tablenav-pages">
                            <?php
                            $pagination_args = array(
                                'base' => add_query_arg('paged', '%#%'),
                                'format' => '',
                                'prev_text' => __('&laquo;'),
                                'next_text' => __('&raquo;'),
                                'total' => $total_pages,
                                'current' => $current_page,
                            );
                            
                            // Preserve filter parameters in pagination
                            if ($filter_email) {
                                $pagination_args['add_args'] = array('filter_email' => $filter_email);
                            }
                            if ($filter_blocked !== null) {
                                $pagination_args['add_args']['filter_blocked'] = $filter_blocked;
                            }
                            if ($filter_expired !== null) {
                                $pagination_args['add_args']['filter_expired'] = $filter_expired;
                            }
                            
                            echo paginate_links($pagination_args);
                            ?>
                        </div>
                    </div>
                <?php endif; ?>
                
                <p style="margin-top: 20px;">
                    <strong><?php echo esc_html(sprintf(__('Total tokens: %d', TBA_TEXTDOMAIN), $total_tokens)); ?></strong>
                </p>
            <?php endif; ?>
        </div>
    </div>
    <?php
}
