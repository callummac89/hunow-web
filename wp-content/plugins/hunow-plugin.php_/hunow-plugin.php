<?php
/**
 * Plugin Name: HU NOW Statistics Dashboard
 * Plugin URI: https://hunow.co.uk
 * Description: Track membership card scans and display statistics with venue portal
 * Version: 2.0.0
 * Author: HU NOW
 * License: GPL v2 or later
 * Text Domain: hunow-stats
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Main plugin class
 */
class HUNOW_Statistics {
    
    public function __construct() {
        // Ensure venue_manager role exists (create if missing)
        add_action('init', array($this, 'ensure_venue_manager_role'));
        
        // Add admin menu
        add_action('admin_menu', array($this, 'add_admin_menu'));
        
        // Add venue portal menu for venue managers
        add_action('admin_menu', array($this, 'add_venue_portal_menu'));
        
        // Remove WordPress menu items for venue managers (keep only Venue Portal)
        add_action('admin_menu', array($this, 'remove_wordpress_menu_items'), 999);
        
        // Add REST API endpoint for stats
        add_action('rest_api_init', array($this, 'register_api_routes'));
        
        // Add user meta fields for venue assignment (admin only)
        add_action('show_user_profile', array($this, 'add_venue_assignment_fields'));
        add_action('edit_user_profile', array($this, 'add_venue_assignment_fields'));
        add_action('personal_options_update', array($this, 'save_venue_assignment_fields'));
        add_action('edit_user_profile_update', array($this, 'save_venue_assignment_fields'));
        
        // Restrict venue managers to only their venues
        add_action('pre_get_posts', array($this, 'restrict_venue_manager_posts'));
        
        // Handle QR code verification page
        add_action('template_redirect', array($this, 'handle_verify_page'));
        
        // Remove admin bar items for venue managers
        add_action('admin_bar_menu', array($this, 'remove_admin_bar_items'), 999);
        
        // Allow venue managers to edit their assigned venues
        add_filter('map_meta_cap', array($this, 'allow_venue_manager_edit'), 10, 4);
        
        // Allow venue managers to publish their assigned venues directly
        add_filter('map_meta_cap', array($this, 'allow_venue_manager_publish'), 10, 4);
        
        // Force publish status when venue managers save posts
        // Use high priority to run AFTER WordPress's capability checks
        add_filter('wp_insert_post_data', array($this, 'force_publish_for_venue_managers'), 99, 2);
        
        // Also hook into save_post to re-publish if status was reverted
        add_action('save_post', array($this, 'ensure_publish_for_venue_managers'), 99, 2);
        
        // Change "Submit for Review" button to "Publish" for venue managers (works for both WordPress editor and Elementor)
        add_action('admin_head-post.php', array($this, 'change_publish_button_for_venue_managers'));
        add_action('admin_head-post-new.php', array($this, 'change_publish_button_for_venue_managers'));
        add_action('elementor/editor/before_enqueue_scripts', array($this, 'change_publish_button_for_venue_managers'));
        
        // Register shortcode for displaying offers in Elementor/templates
        add_shortcode('hunow_offers', array($this, 'display_venue_offers_shortcode'));
        
        // Push notifications
        add_action('admin_menu', array($this, 'add_push_notification_menu'));
        add_action('publish_event', array($this, 'send_new_event_notification'), 10, 2);
        add_action('hunow_rating_submitted', array($this, 'send_rating_notification'), 10, 3);
    }
    
    /**
     * Get venue IDs assigned to current user
     */
    private function get_user_venues() {
        $user_id = get_current_user_id();
        if (!$user_id) {
            return array();
        }
        
        $venues = get_user_meta($user_id, 'assigned_venues', true);
        if (!is_array($venues)) {
            $venues = array();
        }
        
        return array_map('intval', $venues);
    }
    
    /**
     * Check if current user is venue manager
     */
    private function is_venue_manager() {
        $user = wp_get_current_user();
        return in_array('venue_manager', $user->roles);
    }
    
    /**
     * Ensure venue_manager role exists (create if missing)
     */
    public function ensure_venue_manager_role() {
        if (!get_role('venue_manager')) {
            add_role(
                'venue_manager',
                'Venue Manager',
                array(
                    'read' => true,
                    'upload_files' => true,
                    'edit_posts' => true,
                    'delete_posts' => false,
                    'publish_posts' => true,
                    'edit_published_posts' => true,
                )
            );
        }
    }
    
    /**
     * Add venue portal menu for venue managers
     */
    public function add_venue_portal_menu() {
        if (!$this->is_venue_manager()) {
            return;
        }
        
        add_menu_page(
            'Venue Portal',
            'Venue Portal',
            'read',
            'venue-portal',
            array($this, 'display_venue_dashboard'),
            'dashicons-store',
            25
        );
        
        add_submenu_page(
            'venue-portal',
            'Dashboard',
            'Dashboard',
            'read',
            'venue-portal',
            array($this, 'display_venue_dashboard')
        );
        
        add_submenu_page(
            'venue-portal',
            'Analytics',
            'Analytics',
            'read',
            'venue-analytics',
            array($this, 'display_venue_analytics')
        );
        
        add_submenu_page(
            'venue-portal',
            'Scan History',
            'Scan History',
            'read',
            'venue-scan-history',
            array($this, 'display_venue_scan_history')
        );
        
        add_submenu_page(
            'venue-portal',
            'My Offers',
            'My Offers',
            'read',
            'venue-offers',
            array($this, 'display_venue_offers')
        );
        
        add_submenu_page(
            'venue-portal',
            'My Listing',
            'My Listing',
            'read',
            'venue-listing',
            array($this, 'display_venue_listing')
        );
        
        add_submenu_page(
            'venue-portal',
            'Subscription',
            'Subscription',
            'read',
            'venue-subscription',
            array($this, 'display_venue_subscription')
        );
    }
    
    /**
     * Remove WordPress admin menu items for venue managers (keep only Venue Portal)
     */
    public function remove_wordpress_menu_items() {
        // Only remove items for venue managers
        if (!$this->is_venue_manager()) {
            return;
        }
        
        // Remove main WordPress menu items
        remove_menu_page('index.php'); // Dashboard
        remove_menu_page('edit.php'); // Posts
        remove_menu_page('upload.php'); // Media
        remove_menu_page('edit-comments.php'); // Comments
        remove_menu_page('tools.php'); // Tools
        remove_menu_page('themes.php'); // Appearance
        remove_menu_page('plugins.php'); // Plugins
        remove_menu_page('users.php'); // Users
        remove_menu_page('options-general.php'); // Settings
        
        // Remove any other custom post type menus that might appear
        // (These will be handled by restrict_venue_manager_posts, but hide the menus)
        global $menu;
        
        // Remove all menu items except Venue Portal
        foreach ($menu as $key => $item) {
            // Keep only Venue Portal menu (slug: 'venue-portal')
            if (isset($item[2]) && $item[2] !== 'venue-portal') {
                // Also keep separator items for clean appearance
                if (!empty($item[0]) && $item[0] !== '') {
                    unset($menu[$key]);
                }
            }
        }
    }
    
    /**
     * Remove items from WordPress admin bar for venue managers
     */
    public function remove_admin_bar_items($wp_admin_bar) {
        // Only remove items for venue managers
        if (!$this->is_venue_manager()) {
            return;
        }
        
        // Remove Comments
        $wp_admin_bar->remove_node('comments');
        
        // Remove "New" menu (contains New Post, New Page, New Media, etc.)
        $wp_admin_bar->remove_node('new-content');
    }
    
    /**
     * Restrict venue managers to only see/edit their assigned venues
     */
    public function restrict_venue_manager_posts($query) {
        if (!is_admin() || !$this->is_venue_manager()) {
            return;
        }
        
        // Only apply to main query in admin for post types
        if (!$query->is_main_query()) {
            return;
        }
        
        // Check if querying venue post types
        $post_type = $query->get('post_type');
        $venue_post_types = array('eat', 'activity', 'event');
        
        // If no post type specified or not a venue post type, don't restrict
        if (!$post_type) {
            return;
        }
        
        // Check if it's a venue post type
        if (is_array($post_type)) {
            // If array, check if any match venue types
            if (empty(array_intersect($post_type, $venue_post_types))) {
                return;
            }
        } else {
            // If single post type, check if it matches
            if (!in_array($post_type, $venue_post_types)) {
                return;
            }
        }
        
        $user_venues = $this->get_user_venues();
        if (empty($user_venues)) {
            // If no venues assigned, show nothing
            $query->set('post__in', array(0));
            return;
        }
        
        // Only show posts that match user's assigned venues
        $query->set('post__in', $user_venues);
    }
    
    /**
     * Allow venue managers to edit their assigned venues
     */
    public function allow_venue_manager_edit($caps, $cap, $user_id, $args) {
        // Only apply to venue_manager role
        $user = get_user_by('ID', $user_id);
        if (!$user || !in_array('venue_manager', $user->roles)) {
            return $caps;
        }
        
        // Only apply to edit_post or edit_published_posts capabilities
        if ($cap !== 'edit_post' && $cap !== 'edit_published_posts') {
            return $caps;
        }
        
        // Get post ID from args
        if (empty($args)) {
            return $caps;
        }
        
        $post_id = isset($args[0]) ? intval($args[0]) : 0;
        if (!$post_id) {
            return $caps;
        }
        
        // Get post to check its type
        $post = get_post($post_id);
        if (!$post) {
            return $caps;
        }
        
        // Only allow for venue post types (eat, activity, event)
        if (!in_array($post->post_type, array('eat', 'activity', 'event'))) {
            return $caps;
        }
        
        // Check if this venue is assigned to the user
        $assigned_venues = get_user_meta($user_id, 'assigned_venues', true);
        if (!is_array($assigned_venues)) {
            $assigned_venues = array();
        }
        
        if (in_array($post_id, array_map('intval', $assigned_venues))) {
            // User can edit this venue - remove the 'do_not_allow' capability
            $caps = array('edit_posts');
        }
        
        return $caps;
    }
    
    /**
     * Allow venue managers to publish their assigned venues
     */
    public function allow_venue_manager_publish($caps, $cap, $user_id, $args) {
        // Only apply to venue_manager role
        $user = get_user_by('ID', $user_id);
        if (!$user || !in_array('venue_manager', $user->roles)) {
            return $caps;
        }
        
        // Only apply to publish_posts capability
        if ($cap !== 'publish_posts') {
            return $caps;
        }
        
        // If checking publish_posts without a post ID (general capability check)
        if (empty($args)) {
            // Venue managers can publish posts (for their assigned venues)
            return array('publish_posts');
        }
        
        // Get post ID from args if checking for specific post
        $post_id = isset($args[0]) ? intval($args[0]) : 0;
        if (!$post_id) {
            return $caps;
        }
        
        // Get post to check its type
        $post = get_post($post_id);
        if (!$post) {
            return $caps;
        }
        
        // Only allow for venue post types (eat, activity, event)
        if (!in_array($post->post_type, array('eat', 'activity', 'event'))) {
            return $caps;
        }
        
        // Check if this venue is assigned to the user
        $assigned_venues = get_user_meta($user_id, 'assigned_venues', true);
        if (!is_array($assigned_venues)) {
            $assigned_venues = array();
        }
        
        if (in_array($post_id, array_map('intval', $assigned_venues))) {
            // User can publish this venue
            return array('publish_posts');
        }
        
        return $caps;
    }
    
    /**
     * Force publish status when venue managers save posts
     * This ensures posts are published directly instead of going to "pending" or "draft"
     * Uses high priority (99) to run AFTER WordPress's capability checks
     */
    public function force_publish_for_venue_managers($data, $postarr) {
        // Only for venue managers
        if (!$this->is_venue_manager()) {
            return $data;
        }
        
        // Only for venue post types
        if (!in_array($data['post_type'], array('eat', 'activity', 'event'))) {
            return $data;
        }
        
        $post_id = isset($postarr['ID']) ? intval($postarr['ID']) : 0;
        $user_venues = $this->get_user_venues();
        
        // Only apply if editing an assigned venue (or new post for assigned venue types)
        if ($post_id > 0 && !in_array($post_id, $user_venues)) {
            return $data;
        }
        
        // If user clicked publish button OR post status is set to publish
        if (isset($_POST['publish']) || (isset($_POST['post_status']) && $_POST['post_status'] === 'publish')) {
            // Force publish - this runs AFTER WordPress's checks, so we override any revert
            $data['post_status'] = 'publish';
        }
        // If status was changed to pending or draft by WordPress (reverted), check if we should publish
        elseif (in_array($data['post_status'], array('pending', 'draft'))) {
            // If this is an existing post that was published before, keep it published
            if ($post_id > 0) {
                $previous_post = get_post($post_id);
                if ($previous_post && $previous_post->post_status === 'publish' && in_array($post_id, $user_venues)) {
                    $data['post_status'] = 'publish';
                }
            }
        }
        
        return $data;
    }
    
    /**
     * Ensure post stays published after save
     * This catches any status reverts that happen after wp_insert_post_data
     */
    public function ensure_publish_for_venue_managers($post_id, $post) {
        // Prevent infinite loops
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        
        // Only for venue managers
        if (!$this->is_venue_manager()) {
            return;
        }
        
        // Only for venue post types
        if (!in_array($post->post_type, array('eat', 'activity', 'event'))) {
            return;
        }
        
        // Check if this venue is assigned to the user
        $user_venues = $this->get_user_venues();
        if (!in_array($post_id, $user_venues)) {
            return;
        }
        
        // If user clicked publish button, ensure status is publish
        if (isset($_POST['publish']) || (isset($_POST['post_status']) && $_POST['post_status'] === 'publish')) {
            // If post status is not publish (was reverted to draft/pending), force it back
            if ($post->post_status !== 'publish') {
                // Remove this action temporarily to prevent loop
                remove_action('save_post', array($this, 'ensure_publish_for_venue_managers'), 99);
                
                // Force publish
                wp_update_post(array(
                    'ID' => $post_id,
                    'post_status' => 'publish'
                ));
                
                // Re-add the action
                add_action('save_post', array($this, 'ensure_publish_for_venue_managers'), 99, 2);
            }
        }
    }
    
    /**
     * Change "Submit for Review" button text to "Publish" for venue managers
     * Works for both WordPress editor and Elementor
     */
    public function change_publish_button_for_venue_managers() {
        // Only for venue managers
        if (!$this->is_venue_manager()) {
            return;
        }
        
        // Get current post ID
        global $post;
        $post_id = isset($_GET['post']) ? intval($_GET['post']) : (isset($post->ID) ? $post->ID : 0);
        
        // Only apply to venue post types
        if ($post_id > 0) {
            $post_type = get_post_type($post_id);
            if (!in_array($post_type, array('eat', 'activity', 'event'))) {
                return;
            }
            
            // Check if this venue is assigned to the user
            $user_venues = $this->get_user_venues();
            if (!in_array($post_id, $user_venues)) {
                return;
            }
        }
        
        ?>
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            // Change button text from "Submit for Review" to "Publish"
            function changePublishButton() {
                // WordPress Gutenberg editor button (most common)
                $('button.editor-post-publish-button, button.components-button.is-primary, .edit-post-header__settings button').each(function() {
                    var $btn = $(this);
                    var text = $btn.text().trim() || $btn.attr('aria-label') || '';
                    if (text === 'Submit for Review' || text.indexOf('Submit for Review') !== -1) {
                        $btn.text('Publish').attr('aria-label', 'Publish');
                        // Also check for inner spans
                        $btn.find('span').text('Publish');
                    }
                });
                
                // WordPress classic editor button
                $('#publish, #original_publish').each(function() {
                    if ($(this).val() === 'Submit for Review' || $(this).attr('value') === 'Submit for Review') {
                        $(this).val('Publish').attr('value', 'Publish');
                    }
                });
                
                // Any button with "Submit for Review" text (catch-all)
                $('button, input[type="submit"]').each(function() {
                    var $btn = $(this);
                    var text = $btn.text().trim() || $btn.val() || $btn.attr('aria-label') || '';
                    if (text === 'Submit for Review') {
                        if ($btn.is('input[type="submit"]')) {
                            $btn.val('Publish');
                        } else {
                            $btn.text('Publish');
                            $btn.find('span').text('Publish');
                        }
                        $btn.attr('aria-label', 'Publish');
                    }
                });
                
                // Elementor buttons (if using Elementor)
                $('.elementor-button.elementor-button-publish, .e-publish-button').each(function() {
                    if ($(this).text().indexOf('Submit for Review') !== -1 || $(this).attr('aria-label') === 'Submit for Review') {
                        $(this).text('Publish').attr('aria-label', 'Publish');
                    }
                });
            }
            
            // Run immediately
            changePublishButton();
            
            // For Gutenberg: Watch for editor ready
            if (typeof wp !== 'undefined' && wp.domReady) {
                wp.domReady(changePublishButton);
            }
            
            // For Gutenberg: Watch editor store changes
            if (typeof wp !== 'undefined' && wp.data && wp.data.subscribe) {
                wp.data.subscribe(function() {
                    setTimeout(changePublishButton, 100);
                });
            }
            
            // Run after Elementor loads (for Elementor editor)
            if (typeof elementor !== 'undefined') {
                elementor.on('preview:loaded', changePublishButton);
            }
            
            // Also run on DOM mutations (catches dynamically added buttons)
            var observer = new MutationObserver(changePublishButton);
            observer.observe(document.body, {
                childList: true,
                subtree: true,
                attributes: true,
                attributeFilter: ['value', 'aria-label']
            });
            
            // Also check periodically (fallback for Gutenberg)
            setTimeout(changePublishButton, 500);
            setTimeout(changePublishButton, 1000);
            setTimeout(changePublishButton, 2000);
            setTimeout(changePublishButton, 3000);
        });
        </script>
        <style type="text/css">
        /* Hide any "Submit for Review" specific styling if needed */
        </style>
        <?php
    }
    
    /**
     * Add admin menu page
     */
    public function add_admin_menu() {
        add_menu_page(
            'HU NOW Stats',
            'HU NOW Stats',
            'manage_options',
            'hunow-stats',
            array($this, 'display_stats_page'),
            'dashicons-chart-line',
            30
        );
    }
    
    /**
     * Display statistics dashboard
     */
    public function display_stats_page() {
        // Get all users
        $users = get_users();
        $total_scans = 0;
        $users_with_scans = [];
        
        foreach ($users as $user) {
            $scans = get_user_meta($user->ID, 'membership_card_scans', true);
            $scans = $scans ? intval($scans) : 0;
            
            if ($scans > 0) {
                $users_with_scans[] = [
                    'user' => $user,
                    'scans' => $scans
                ];
                $total_scans += $scans;
            }
        }
        
        // Sort by scans (highest first)
        usort($users_with_scans, function($a, $b) {
            return $b['scans'] - $a['scans'];
        });
        
        // Calculate additional statistics
        $total_active_members = count($users_with_scans);
        $avg_scans_per_member = $total_active_members > 0 ? round($total_scans / $total_active_members, 1) : 0;
        
        // Calculate time-based scans
        $today = strtotime('today');
        $week_ago = strtotime('-7 days');
        $month_ago = strtotime('-30 days');
        
        $today_scans = 0;
        $weekly_scans = 0;
        $monthly_scans = 0;
        
        // Calculate venue statistics and day activity
        $venues_data = [];
        $venue_scans_by_time = ['today' => 0, 'week' => 0, 'month' => 0];
        $day_counts = array('Monday' => 0, 'Tuesday' => 0, 'Wednesday' => 0, 'Thursday' => 0, 'Friday' => 0, 'Saturday' => 0, 'Sunday' => 0);
        $members_active_last_30_days = [];
        
        foreach ($users as $user) {
            $scan_history = get_user_meta($user->ID, 'membership_card_history', true);
            $user_scanned_last_30_days = false;
            
            if (is_array($scan_history) && !empty($scan_history)) {
                foreach ($scan_history as $scan) {
                    $scan_timestamp = isset($scan['timestamp']) ? strtotime($scan['timestamp']) : 0;
                    
                    // Count time-based scans
                    if ($scan_timestamp >= $today) {
                        $today_scans++;
                    }
                    if ($scan_timestamp >= $week_ago) {
                        $weekly_scans++;
                    }
                    if ($scan_timestamp >= $month_ago) {
                        $monthly_scans++;
                        $user_scanned_last_30_days = true;
                    }
                    
                    // Count scans by day of week
                    if ($scan_timestamp > 0) {
                        $day_name = date('l', $scan_timestamp);
                        if (isset($day_counts[$day_name])) {
                            $day_counts[$day_name]++;
                        }
                    }
                    
                    // Count venue redemptions
                    $venue_name = 'Unknown Venue';
                    if (isset($scan['venue_name']) && !empty($scan['venue_name'])) {
                        $venue_name = $scan['venue_name'];
                    } elseif (isset($scan['venue_id']) && !empty($scan['venue_id'])) {
                        $venue_post = get_post($scan['venue_id']);
                        if ($venue_post) {
                            $venue_name = $venue_post->post_title;
                        }
                    }
                    
                    if (!isset($venues_data[$venue_name])) {
                        $venues_data[$venue_name] = 0;
                    }
                    $venues_data[$venue_name]++;
                }
            }
            
            // Track if user scanned in last 30 days for retention rate
            if ($user_scanned_last_30_days) {
                $members_active_last_30_days[] = $user->ID;
            }
        }
        
        // Get most active day
        $most_active_day = 'N/A';
        if (!empty($day_counts)) {
            arsort($day_counts);
            $most_active_day = key($day_counts);
        }
        
        // Calculate retention rate (members who scanned in last 30 days / total active members)
        $retention_rate = $total_active_members > 0 ? round((count($members_active_last_30_days) / $total_active_members) * 100, 1) : 0;
        
        // Get top venue and average redeems per venue
        $top_venue_name = 'N/A';
        $top_venue_count = 0;
        $avg_redeems_per_venue = 0;
        
        if (!empty($venues_data)) {
            arsort($venues_data);
            $top_venue_name = key($venues_data);
            $top_venue_count = reset($venues_data);
            $avg_redeems_per_venue = round(array_sum($venues_data) / count($venues_data), 1);
        }
        
        ?>
        <div class="wrap">
            <h1>HU NOW Statistics Dashboard</h1>
            
            <div class="hunow-stats-header" style="background: #0f0032; color: #fff; padding: 30px; border-radius: 8px; margin: 20px 0;">
                <h2 style="color: #fbc903; margin: 0 0 15px 0; font-size: 16px; font-weight: 600;">📊 Total Offer Redemptions</h2>
                <div style="font-size: 56px; font-weight: bold; color: #fbc903; margin: 10px 0 15px 0; line-height: 1.2;"><?php echo number_format($total_scans); ?></div>
                <p style="margin: 0; opacity: 0.9; font-size: 14px; line-height: 1.5;">QR codes scanned across all members</p>
            </div>
            
            <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin: 20px 0;">
                <div style="background: #fff; padding: 20px; border: 1px solid #ddd; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                    <div style="color: #666; font-size: 13px; margin-bottom: 8px; font-weight: 500;">Avg Scans Per Member</div>
                    <div style="font-size: 32px; font-weight: bold; color: #0f0032;"><?php echo number_format($avg_scans_per_member, 1); ?></div>
                </div>
                
                <div style="background: #fff; padding: 20px; border: 1px solid #ddd; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                    <div style="color: #666; font-size: 13px; margin-bottom: 8px; font-weight: 500;">Top Performing Venue</div>
                    <div style="font-size: 32px; font-weight: bold; color: #0f0032;"><?php echo esc_html($top_venue_name); ?></div>
                    <div style="color: #fbc903; font-size: 14px; margin-top: 5px;"><?php echo number_format($top_venue_count); ?> redeems</div>
                </div>
                
                <div style="background: #fff; padding: 20px; border: 1px solid #ddd; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                    <div style="color: #666; font-size: 13px; margin-bottom: 8px; font-weight: 500;">Avg Redeems Per Venue</div>
                    <div style="font-size: 32px; font-weight: bold; color: #0f0032;"><?php echo number_format($avg_redeems_per_venue, 1); ?></div>
                </div>
                
                <div style="background: #fff; padding: 20px; border: 1px solid #ddd; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                    <div style="color: #666; font-size: 13px; margin-bottom: 8px; font-weight: 500;">Total Active Members</div>
                    <div style="font-size: 32px; font-weight: bold; color: #0f0032;"><?php echo number_format($total_active_members); ?></div>
                </div>
                
                <div style="background: #fff; padding: 20px; border: 1px solid #ddd; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                    <div style="color: #666; font-size: 13px; margin-bottom: 8px; font-weight: 500;">Today's Scans</div>
                    <div style="font-size: 32px; font-weight: bold; color: #0f0032;"><?php echo number_format($today_scans); ?></div>
                </div>
                
                <div style="background: #fff; padding: 20px; border: 1px solid #ddd; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                    <div style="color: #666; font-size: 13px; margin-bottom: 8px; font-weight: 500;">Weekly Scans</div>
                    <div style="font-size: 32px; font-weight: bold; color: #0f0032;"><?php echo number_format($weekly_scans); ?></div>
                </div>
                
                <div style="background: #fff; padding: 20px; border: 1px solid #ddd; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                    <div style="color: #666; font-size: 13px; margin-bottom: 8px; font-weight: 500;">Monthly Scans</div>
                    <div style="font-size: 32px; font-weight: bold; color: #0f0032;"><?php echo number_format($monthly_scans); ?></div>
                </div>
                
                <div style="background: #fff; padding: 20px; border: 1px solid #ddd; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                    <div style="color: #666; font-size: 13px; margin-bottom: 8px; font-weight: 500;">Most Active Day</div>
                    <div style="font-size: 32px; font-weight: bold; color: #0f0032;"><?php echo esc_html($most_active_day); ?></div>
                </div>
                
                <div style="background: #fff; padding: 20px; border: 1px solid #ddd; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                    <div style="color: #666; font-size: 13px; margin-bottom: 8px; font-weight: 500;">Retention Rate</div>
                    <div style="font-size: 32px; font-weight: bold; color: #0f0032;"><?php echo number_format($retention_rate, 1); ?>%</div>
                </div>
            </div>
            
            <div style="background: #fff; padding: 20px; border: 1px solid #ddd; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                <h2>Top Members by Scans</h2>
                
                <?php if (empty($users_with_scans)): ?>
                    <p>No scans recorded yet.</p>
                <?php else: ?>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th style="width: 5%;">#</th>
                                <th style="width: 18%;">Member Name</th>
                                <th style="width: 22%;">Email</th>
                                <th style="width: 10%;">Total Scans</th>
                                <th style="width: 20%;">Last Venue</th>
                                <th style="width: 15%;">Last Offer</th>
                                <th style="width: 10%;">User ID</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $rank = 1;
                            foreach ($users_with_scans as $user_data): 
                                $user = $user_data['user'];
                                $scans = $user_data['scans'];
                                
                                // Get scan history to find last venue and offer
                                $scan_history = get_user_meta($user->ID, 'membership_card_history', true);
                                $last_venue = 'N/A';
                                $last_offer = 'N/A';
                                if (is_array($scan_history) && !empty($scan_history)) {
                                    $last_scan = end($scan_history);
                                    
                                    // Get venue name if available
                                    if (isset($last_scan['venue_name']) && !empty($last_scan['venue_name'])) {
                                        $last_venue = esc_html($last_scan['venue_name']);
                                    } elseif (isset($last_scan['venue_id']) && !empty($last_scan['venue_id'])) {
                                        $venue_post = get_post($last_scan['venue_id']);
                                        if ($venue_post) {
                                            $last_venue = esc_html($venue_post->post_title);
                                        }
                                    }
                                    
                                    // Get offer title if available
                                    if (isset($last_scan['offer_title']) && !empty($last_scan['offer_title'])) {
                                        $last_offer = esc_html($last_scan['offer_title']);
                                    }
                                }
                            ?>
                                <tr>
                                    <td><strong><?php echo $rank; ?></strong></td>
                                    <td><?php echo esc_html($user->display_name); ?></td>
                                    <td><?php echo esc_html($user->user_email); ?></td>
                                    <td>
                                        <span style="background: #fbc903; color: #000; padding: 5px 10px; border-radius: 4px; font-weight: bold;">
                                            <?php echo number_format($scans); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <strong style="color: #0f0032;"><?php echo $last_venue; ?></strong>
                                    </td>
                                    <td><?php echo $last_offer; ?></td>
                                    <td><?php echo $user->ID; ?> 
                                        <a href="?page=hunow-stats&user_id=<?php echo $user->ID; ?>" style="margin-left: 10px; color: #0073aa; text-decoration: none;">View Details</a>
                                    </td>
                                </tr>
                            <?php 
                                $rank++;
                            endforeach; 
                            ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
            
            <?php
            // Get top venues by redeems
            $venues_data = [];
            foreach ($users as $user) {
                $scan_history = get_user_meta($user->ID, 'membership_card_history', true);
                if (is_array($scan_history) && !empty($scan_history)) {
                    foreach ($scan_history as $scan) {
                        $venue_name = 'Unknown Venue';
                        $venue_id = null;
                        
                        if (isset($scan['venue_name']) && !empty($scan['venue_name'])) {
                            $venue_name = $scan['venue_name'];
                        } elseif (isset($scan['venue_id']) && !empty($scan['venue_id'])) {
                            $venue_id = $scan['venue_id'];
                            $venue_post = get_post($venue_id);
                            if ($venue_post) {
                                $venue_name = $venue_post->post_title;
                            }
                        }
                        
                        if (!isset($venues_data[$venue_name])) {
                            $venues_data[$venue_name] = [
                                'name' => $venue_name,
                                'count' => 0,
                                'venue_id' => $venue_id
                            ];
                        }
                        $venues_data[$venue_name]['count']++;
                    }
                }
            }
            
            // Sort venues by count (highest first) and get top 10
            usort($venues_data, function($a, $b) {
                return $b['count'] - $a['count'];
            });
            $top_venues = array_slice($venues_data, 0, 10);
            ?>
            
            <div style="background: #fff; padding: 20px; border: 1px solid #ddd; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin-top: 30px;">
                <h2>Top 10 Venues by Redeems</h2>
                
                <?php if (empty($top_venues)): ?>
                    <p>No venue redemptions recorded yet.</p>
                <?php else: ?>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th style="width: 8%;">#</th>
                                <th style="width: 67%;">Venue Name</th>
                                <th style="width: 25%;">Total Redeems</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $rank = 1;
                            foreach ($top_venues as $venue): 
                            ?>
                                <tr>
                                    <td><strong><?php echo $rank; ?></strong></td>
                                    <td><strong style="color: #0f0032;"><?php echo esc_html($venue['name']); ?></strong></td>
                                    <td>
                                        <span style="background: #fbc903; color: #000; padding: 5px 10px; border-radius: 4px; font-weight: bold;">
                                            <?php echo number_format($venue['count']); ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php 
                                $rank++;
                            endforeach; 
                            ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
            
            <div style="margin-top: 30px; padding: 15px; background: #f0f0f0; border-left: 4px solid #fbc903; border-radius: 4px;">
                <strong>How it works:</strong>
                <ul style="margin: 10px 0 0 0;">
                    <li>Each time a membership QR code is scanned at a venue, the count increases</li>
                    <li>This shows how many offers have been redeemed by each member</li>
                    <li>Venue names are displayed when the venue password is verified during scanning</li>
                    <li>Shows the last known venue where each member used their card</li>
                </ul>
            </div>
            
            <?php
            // Add detailed scan history section
            if (isset($_GET['user_id']) && is_numeric($_GET['user_id'])) {
                $selected_user = get_user_by('ID', intval($_GET['user_id']));
                if ($selected_user) {
                    $scan_history = get_user_meta($selected_user->ID, 'membership_card_history', true);
                    ?>
                    <div style="margin-top: 30px; background: #fff; padding: 20px; border: 1px solid #ddd; border-radius: 8px;">
                        <h2>Detailed Scan History: <?php echo esc_html($selected_user->display_name); ?></h2>
                        
                        <?php if (is_array($scan_history) && !empty($scan_history)): ?>
                            <table class="wp-list-table widefat fixed striped">
                                <thead>
                                    <tr>
                                        <th>Timestamp</th>
                                        <th>Venue</th>
                                        <th>IP Address</th>
                                        <th>Location</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach (array_reverse($scan_history) as $scan): 
                                        // Get venue name
                                        $venue_name = 'N/A';
                                        if (isset($scan['venue_name']) && !empty($scan['venue_name'])) {
                                            $venue_name = esc_html($scan['venue_name']);
                                        } elseif (isset($scan['venue_id']) && !empty($scan['venue_id'])) {
                                            $venue_post = get_post($scan['venue_id']);
                                            if ($venue_post) {
                                                $venue_name = esc_html($venue_post->post_title);
                                            }
                                        }
                                    ?>
                                        <tr>
                                            <td><?php echo isset($scan['timestamp']) ? date('Y-m-d H:i:s', strtotime($scan['timestamp'])) : 'Unknown'; ?></td>
                                            <td><strong style="color: #0f0032;"><?php echo $venue_name; ?></strong></td>
                                            <td><?php echo isset($scan['ip']) ? esc_html($scan['ip']) : 'Unknown'; ?></td>
                                            <td>
                                                <?php 
                                                if (isset($scan['latitude']) && isset($scan['longitude']) && $scan['latitude'] != 0) {
                                                    $lat = $scan['latitude'];
                                                    $lng = $scan['longitude'];
                                                    echo "<a href='https://www.google.com/maps?q={$lat},{$lng}' target='_blank' style='color: #fbc903;'>📍 View on Map</a>";
                                                } else {
                                                    echo 'N/A';
                                                }
                                                ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php else: ?>
                            <p>No scan history available for this member.</p>
                        <?php endif; ?>
                    </div>
                    <?php
                }
            }
            ?>
        </div>
        
        <style>
            .hunow-stats-header {
                background: linear-gradient(135deg, #0f0032 0%, #1a0047 100%);
            }
        </style>
        <?php
    }
    
    /**
     * Register REST API routes
     */
    public function register_api_routes() {
        register_rest_route('hunow/v1', '/membership/(?P<id>[a-zA-Z0-9-]+)/stats', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_membership_stats'),
            'permission_callback' => '__return_true'
        ));
        
        register_rest_route('hunow/v1', '/stats/total', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_total_stats'),
            'permission_callback' => function() {
                return current_user_can('manage_options');
            }
        ));
        
        // Featured listings for archive pages
        register_rest_route('hunow/v1', '/featured-archive/(?P<post_type>[a-zA-Z0-9-]+)', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_featured_archive_listings'),
            'permission_callback' => '__return_true'
        ));
        
        // Create listing from app
        register_rest_route('hunow/v1', '/listings/create', array(
            'methods' => 'POST',
            'callback' => array($this, 'create_listing'),
            'permission_callback' => array($this, 'verify_jwt_token')
        ));
        
        // Get user's submitted listings
        register_rest_route('hunow/v1', '/listings/my-submissions', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_user_submissions'),
            'permission_callback' => array($this, 'verify_jwt_token')
        ));
        
        // Rating endpoints
        register_rest_route('hunow/v1', '/ratings/(?P<post_id>\d+)', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_post_ratings'),
            'permission_callback' => '__return_true'
        ));
        
        register_rest_route('hunow/v1', '/ratings', array(
            'methods' => 'POST',
            'callback' => array($this, 'submit_rating'),
            'permission_callback' => array($this, 'verify_jwt_token')
        ));
        
        register_rest_route('hunow/v1', '/ratings/history', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_user_rating_history'),
            'permission_callback' => array($this, 'verify_jwt_token')
        ));
        
        // Comments endpoints
        register_rest_route('hunow/v1', '/comments/(?P<post_id>\d+)', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_post_comments'),
            'permission_callback' => '__return_true'
        ));
        
        register_rest_route('hunow/v1', '/comments', array(
            'methods' => 'POST',
            'callback' => array($this, 'submit_comment'),
            'permission_callback' => array($this, 'verify_jwt_token')
        ));
        
        register_rest_route('hunow/v1', '/comments/(?P<comment_id>\d+)', array(
            'methods' => 'PUT',
            'callback' => array($this, 'update_comment'),
            'permission_callback' => array($this, 'verify_jwt_token')
        ));
        
        register_rest_route('hunow/v1', '/comments/(?P<comment_id>\d+)', array(
            'methods' => 'DELETE',
            'callback' => array($this, 'delete_comment'),
            'permission_callback' => array($this, 'verify_jwt_token')
        ));
        
        // Debug endpoint (can be removed in production)
        register_rest_route('hunow/v1', '/debug/post/(?P<id>\d+)', array(
            'methods' => 'GET',
            'callback' => array($this, 'debug_post_data'),
            'permission_callback' => '__return_true'
        ));
        
        // Push notification endpoints
        register_rest_route('hunow/v1', '/push/register', array(
            'methods' => 'POST',
            'callback' => array($this, 'register_push_token'),
            'permission_callback' => function($request) {
                // Check JWT token or regular auth
                $headers = $request->get_headers();
                $auth_header = isset($headers['authorization']) ? $headers['authorization'] : array();
                $auth_header = is_array($auth_header) ? $auth_header[0] : $auth_header;
                
                // If JWT token exists, verify it
                if ($auth_header && strpos($auth_header, 'Bearer ') === 0) {
                    $token = str_replace('Bearer ', '', $auth_header);
                    // Allow if token is present (JWT Auth plugin should validate it)
                    return !empty($token);
                }
                
                // Fallback to regular WordPress auth
                return is_user_logged_in();
            }
        ));
        
        register_rest_route('hunow/v1', '/push/send', array(
            'methods' => 'POST',
            'callback' => array($this, 'send_push_notification_api'),
            'permission_callback' => function() {
                return current_user_can('manage_options') || is_user_logged_in();
            }
        ));
        
        // Register custom REST fields for featured images
        $this->register_featured_image_fields();
    }
    
    /**
     * Get membership stats for a specific user
     */
    public function get_membership_stats($request) {
        $membership_id = $request['id'];
        $parts = explode('-', $membership_id);
        
        if (count($parts) !== 3 || $parts[0] !== 'HUNOW') {
            return new WP_Error('invalid_membership', 'Invalid membership ID', array('status' => 400));
        }
        
        $user_id = $parts[1];
        $scans = get_user_meta($user_id, 'membership_card_scans', true);
        $history = get_user_meta($user_id, 'membership_card_history', true);
        
        return new WP_REST_Response(array(
            'success' => true,
            'scans' => intval($scans),
            'history' => $history ?: array()
        ));
    }
    
    /**
     * Get total statistics (admin only)
     */
    public function get_total_stats() {
        $users = get_users();
        $total_scans = 0;
        $active_members = 0;
        
        foreach ($users as $user) {
            $scans = get_user_meta($user->ID, 'membership_card_scans', true);
            if ($scans > 0) {
                $total_scans += intval($scans);
                $active_members++;
            }
        }
        
        return new WP_REST_Response(array(
            'total_scans' => $total_scans,
            'active_members' => $active_members,
            'total_users' => count($users)
        ));
    }
    
    /**
     * Get featured listings for archive pages
     * Endpoint: /wp-json/hunow/v1/featured-archive/{post_type}
     */
    public function get_featured_archive_listings($request) {
        $post_type = sanitize_text_field($request['post_type']);
        
        // Validate post type
        $allowed_types = array('eat', 'activity', 'event');
        if (!in_array($post_type, $allowed_types)) {
            return new WP_Error('invalid_post_type', 'Invalid post type. Allowed: eat, activity, event', array('status' => 400));
        }
        
        // Query for featured posts
        $args = array(
            'post_type' => $post_type,
            'posts_per_page' => 10, // Limit to 10 featured items
            'post_status' => 'publish',
            'meta_query' => array(
                array(
                    'key' => 'featured_on_archive',
                    'value' => '1',
                    'compare' => '='
                )
            ),
            'orderby' => 'date',
            'order' => 'DESC'
        );
        
        $query = new WP_Query($args);
        $items = array();
        
        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $post_id = get_the_ID();
                
                // Get ACF fields
                $acf_fields = get_fields($post_id);
                
                // Build item data
                $item = array(
                    'id' => $post_id,
                    'type' => $post_type,
                    'title' => array('rendered' => get_the_title()),
                    'excerpt' => array('rendered' => get_the_excerpt()),
                    'date' => get_the_date('c'),
                    'link' => get_permalink(),
                    'acf' => $acf_fields
                );
                
                $items[] = $item;
            }
            wp_reset_postdata();
        }
        
        return new WP_REST_Response(array(
            'success' => true,
            'items' => $items,
            'count' => count($items)
        ), 200);
    }
    
    /**
     * Verify JWT token for authenticated endpoints
     */
    public function verify_jwt_token($request) {
        $headers = $request->get_headers();
        $auth_header = isset($headers['authorization']) ? $headers['authorization'] : array();
        
        if (empty($auth_header)) {
            return new WP_Error('no_token', 'Authentication required', array('status' => 401));
        }
        
        // JWT plugin handles actual verification
        // If we get here, user is authenticated
        return true;
    }
    
    /**
     * Create listing from app
     * Endpoint: /wp-json/hunow/v1/listings/create
     */
    public function create_listing($request) {
        $current_user_id = get_current_user_id();
        
        if (!$current_user_id) {
            return new WP_Error('unauthorized', 'User must be logged in', array('status' => 401));
        }
        
        // Get and validate post type
        $post_type = sanitize_text_field($request->get_param('post_type'));
        $allowed_types = array('eat', 'activity', 'event');
        if (!in_array($post_type, $allowed_types)) {
            return new WP_Error('invalid_type', 'Invalid post type. Allowed: eat, activity, event', array('status' => 400));
        }
        
        // Get required fields
        $title = sanitize_text_field($request->get_param('title'));
        $content = wp_kses_post($request->get_param('content'));
        
        if (empty($title)) {
            return new WP_Error('missing_title', 'Title is required', array('status' => 400));
        }
        
        // Create post with pending status (requires moderation)
        $post_id = wp_insert_post(array(
            'post_type' => $post_type,
            'post_title' => $title,
            'post_content' => $content,
            'post_status' => 'pending', // Requires admin approval
            'post_author' => $current_user_id,
        ), true);
        
        if (is_wp_error($post_id)) {
            return $post_id;
        }
        
        // Handle ACF fields if provided
        $acf_fields = $request->get_param('acf_fields');
        if ($acf_fields && is_array($acf_fields)) {
            foreach ($acf_fields as $field_key => $field_value) {
                // Handle gallery field specially (array of image IDs)
                if ($field_key === 'gallery' && is_array($field_value)) {
                    // Convert to array of image IDs for ACF
                    $gallery_ids = array();
                    foreach ($field_value as $img) {
                        if (is_numeric($img)) {
                            // Direct ID
                            $gallery_ids[] = intval($img);
                        } elseif (isset($img['id']) && is_numeric($img['id'])) {
                            // Object with id property
                            $gallery_ids[] = intval($img['id']);
                        }
                    }
                    update_field($field_key, $gallery_ids, $post_id);
                } elseif ($field_key === 'listing_offer' && is_array($field_value)) {
                    // Handle listing_offer as nested array
                    update_field($field_key, $field_value, $post_id);
                } else {
                    // Sanitize other field values
                    if (is_array($field_value)) {
                        $sanitized_value = array_map('sanitize_text_field', $field_value);
                    } else {
                        $sanitized_value = sanitize_text_field($field_value);
                    }
                    update_field($field_key, $sanitized_value, $post_id);
                }
            }
        }
        
        // Handle featured image if provided (media ID)
        $featured_image_id = intval($request->get_param('featured_image_id'));
        if ($featured_image_id > 0) {
            set_post_thumbnail($post_id, $featured_image_id);
        }
        
        return new WP_REST_Response(array(
            'success' => true,
            'post_id' => $post_id,
            'message' => 'Listing submitted for review. It will appear in the ' . ucfirst($post_type) . ' archive once approved.',
            'status' => 'pending'
        ), 200);
    }
    
    /**
     * Get user's submitted listings
     * Endpoint: /wp-json/hunow/v1/listings/my-submissions
     */
    public function get_user_submissions($request) {
        $current_user_id = get_current_user_id();
        
        if (!$current_user_id) {
            return new WP_Error('unauthorized', 'User must be logged in', array('status' => 401));
        }
        
        $args = array(
            'post_type' => array('eat', 'activity', 'event'),
            'author' => $current_user_id,
            'posts_per_page' => 50,
            'orderby' => 'date',
            'order' => 'DESC'
        );
        
        $query = new WP_Query($args);
        $submissions = array();
        
        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $post_id = get_the_ID();
                
                $submissions[] = array(
                    'id' => $post_id,
                    'title' => get_the_title(),
                    'type' => get_post_type(),
                    'status' => get_post_status(),
                    'date' => get_the_date('c'),
                    'link' => get_permalink()
                );
            }
            wp_reset_postdata();
        }
        
        return new WP_REST_Response(array(
            'success' => true,
            'submissions' => $submissions,
            'count' => count($submissions)
        ), 200);
    }
    
    /**
     * Get ratings for a specific post
     * Endpoint: /wp-json/hunow/v1/ratings/{post_id}
     */
    public function get_post_ratings($request) {
        $post_id = intval($request['post_id']);
        
        if (!get_post($post_id)) {
            return new WP_Error('post_not_found', 'Post not found', array('status' => 404));
        }
        
        // Get ratings from post meta
        $ratings = get_post_meta($post_id, 'hunow_ratings', true);
        if (!is_array($ratings)) {
            $ratings = array();
        }
        
        // Calculate average and count
        $count = count($ratings);
        $average = 0;
        
        if ($count > 0) {
            $sum = array_sum($ratings);
            $average = round($sum / $count, 1);
        }
        
        // Get current user's rating if logged in
        $user_rating = null;
        $current_user_id = get_current_user_id();
        if ($current_user_id && isset($ratings[$current_user_id])) {
            $user_rating = $ratings[$current_user_id];
        }
        
        return new WP_REST_Response(array(
            'success' => true,
            'data' => array(
                'average' => $average,
                'count' => $count,
                'user_rating' => $user_rating
            )
        ), 200);
    }
    
    /**
     * Submit or update a rating
     * Endpoint: POST /wp-json/hunow/v1/ratings
     */
    public function submit_rating($request) {
        $current_user_id = get_current_user_id();
        
        if (!$current_user_id) {
            return new WP_Error('unauthorized', 'User must be logged in', array('status' => 401));
        }
        
        $post_id = intval($request->get_param('post_id'));
        $rating = intval($request->get_param('rating'));
        
        // Validate
        if (!get_post($post_id)) {
            return new WP_Error('post_not_found', 'Post not found', array('status' => 404));
        }
        
        if ($rating < 1 || $rating > 5) {
            return new WP_Error('invalid_rating', 'Rating must be between 1 and 5', array('status' => 400));
        }
        
        // Get existing ratings
        $ratings = get_post_meta($post_id, 'hunow_ratings', true);
        if (!is_array($ratings)) {
            $ratings = array();
        }
        
        // Update user's rating
        $ratings[$current_user_id] = $rating;
        update_post_meta($post_id, 'hunow_ratings', $ratings);
        
        // Recalculate average
        $count = count($ratings);
        $average = round(array_sum($ratings) / $count, 1);
        
        // Also save to user meta for rating history
        $user_ratings = get_user_meta($current_user_id, 'hunow_user_ratings', true);
        if (!is_array($user_ratings)) {
            $user_ratings = array();
        }
        
        $user_ratings[] = array(
            'id' => uniqid(),
            'post_id' => $post_id,
            'content_type' => get_post_type($post_id),
            'rating' => $rating,
            'date' => current_time('mysql')
        );
        update_user_meta($current_user_id, 'hunow_user_ratings', $user_ratings);
        
        // Trigger action for push notifications and other integrations
        do_action('hunow_rating_submitted', $post_id, $rating, $current_user_id);
        
        return new WP_REST_Response(array(
            'success' => true,
            'data' => array(
                'average' => $average,
                'count' => $count,
                'user_rating' => $rating
            )
        ), 200);
    }
    
    /**
     * Get user's rating history
     * Endpoint: GET /wp-json/hunow/v1/ratings/history
     */
    public function get_user_rating_history($request) {
        $current_user_id = get_current_user_id();
        
        if (!$current_user_id) {
            return new WP_Error('unauthorized', 'User must be logged in', array('status' => 401));
        }
        
        $user_ratings = get_user_meta($current_user_id, 'hunow_user_ratings', true);
        if (!is_array($user_ratings)) {
            $user_ratings = array();
        }
        
        return new WP_REST_Response(array(
            'success' => true,
            'data' => array(
                'ratings' => $user_ratings,
                'count' => count($user_ratings)
            )
        ), 200);
    }
    
    /**
     * Get comments for a specific post
     * Endpoint: GET /wp-json/hunow/v1/comments/{post_id}
     */
    public function get_post_comments($request) {
        $post_id = intval($request['post_id']);
        
        if (!get_post($post_id)) {
            return new WP_Error('post_not_found', 'Post not found', array('status' => 404));
        }
        
        $comments = get_comments(array(
            'post_id' => $post_id,
            'status' => 'approve',
            'orderby' => 'comment_date_gmt',
            'order' => 'DESC'
        ));
        
        $formatted_comments = array();
        foreach ($comments as $comment) {
            $formatted_comments[] = array(
                'id' => $comment->comment_ID,
                'author' => $comment->comment_author,
                'author_email' => $comment->comment_author_email,
                'content' => $comment->comment_content,
                'date' => $comment->comment_date_gmt,
                'parent' => $comment->comment_parent
            );
        }
        
        return new WP_REST_Response(array(
            'success' => true,
            'comments' => $formatted_comments,
            'count' => count($formatted_comments)
        ), 200);
    }
    
    /**
     * Submit a comment
     * Endpoint: POST /wp-json/hunow/v1/comments
     */
    public function submit_comment($request) {
        $current_user_id = get_current_user_id();
        
        if (!$current_user_id) {
            return new WP_Error('unauthorized', 'User must be logged in', array('status' => 401));
        }
        
        $post_id = intval($request->get_param('post_id'));
        $content = sanitize_textarea_field($request->get_param('content'));
        $parent = intval($request->get_param('parent'));
        
        if (!get_post($post_id)) {
            return new WP_Error('post_not_found', 'Post not found', array('status' => 404));
        }
        
        if (empty($content)) {
            return new WP_Error('empty_content', 'Comment content is required', array('status' => 400));
        }
        
        $user = get_userdata($current_user_id);
        
        $comment_data = array(
            'comment_post_ID' => $post_id,
            'comment_author' => $user->display_name,
            'comment_author_email' => $user->user_email,
            'comment_content' => $content,
            'comment_parent' => $parent,
            'user_id' => $current_user_id,
            'comment_approved' => 1 // Auto-approve for logged-in users
        );
        
        $comment_id = wp_insert_comment($comment_data);
        
        if (!$comment_id) {
            return new WP_Error('comment_failed', 'Failed to create comment', array('status' => 500));
        }
        
        return new WP_REST_Response(array(
            'success' => true,
            'comment_id' => $comment_id,
            'message' => 'Comment posted successfully'
        ), 200);
    }
    
    /**
     * Update a comment
     * Endpoint: PUT /wp-json/hunow/v1/comments/{comment_id}
     */
    public function update_comment($request) {
        $current_user_id = get_current_user_id();
        
        if (!$current_user_id) {
            return new WP_Error('unauthorized', 'User must be logged in', array('status' => 401));
        }
        
        $comment_id = intval($request['comment_id']);
        $content = sanitize_textarea_field($request->get_param('content'));
        
        $comment = get_comment($comment_id);
        if (!$comment) {
            return new WP_Error('comment_not_found', 'Comment not found', array('status' => 404));
        }
        
        // Check if user owns the comment
        if ($comment->user_id != $current_user_id) {
            return new WP_Error('unauthorized', 'You can only edit your own comments', array('status' => 403));
        }
        
        if (empty($content)) {
            return new WP_Error('empty_content', 'Comment content is required', array('status' => 400));
        }
        
        $result = wp_update_comment(array(
            'comment_ID' => $comment_id,
            'comment_content' => $content
        ));
        
        if (!$result) {
            return new WP_Error('update_failed', 'Failed to update comment', array('status' => 500));
        }
        
        return new WP_REST_Response(array(
            'success' => true,
            'message' => 'Comment updated successfully'
        ), 200);
    }
    
    /**
     * Delete a comment
     * Endpoint: DELETE /wp-json/hunow/v1/comments/{comment_id}
     */
    public function delete_comment($request) {
        $current_user_id = get_current_user_id();
        
        if (!$current_user_id) {
            return new WP_Error('unauthorized', 'User must be logged in', array('status' => 401));
        }
        
        $comment_id = intval($request['comment_id']);
        
        $comment = get_comment($comment_id);
        if (!$comment) {
            return new WP_Error('comment_not_found', 'Comment not found', array('status' => 404));
        }
        
        // Check if user owns the comment or is admin
        if ($comment->user_id != $current_user_id && !current_user_can('manage_options')) {
            return new WP_Error('unauthorized', 'You can only delete your own comments', array('status' => 403));
        }
        
        $result = wp_delete_comment($comment_id, true);
        
        if (!$result) {
            return new WP_Error('delete_failed', 'Failed to delete comment', array('status' => 500));
        }
        
        return new WP_REST_Response(array(
            'success' => true,
            'message' => 'Comment deleted successfully'
        ), 200);
    }
    
    /**
     * Debug endpoint to check post data structure
     * Endpoint: GET /wp-json/hunow/v1/debug/post/{id}
     */
    public function debug_post_data($request) {
        $post_id = intval($request['id']);
        
        $post = get_post($post_id);
        
        if (!$post) {
            return new WP_Error('post_not_found', 'Post not found', array('status' => 404));
        }
        
        $thumbnail_id = get_post_thumbnail_id($post_id);
        
        return new WP_REST_Response(array(
            'post_exists' => true,
            'post_type' => get_post_type($post_id),
            'post_status' => get_post_status($post_id),
            'has_thumbnail' => has_post_thumbnail($post_id),
            'thumbnail_id' => $thumbnail_id,
            'thumbnail_url' => get_the_post_thumbnail_url($post_id, 'full'),
            'thumbnail_sizes' => $thumbnail_id ? array(
                'full' => wp_get_attachment_image_url($thumbnail_id, 'full'),
                'large' => wp_get_attachment_image_url($thumbnail_id, 'large'),
                'medium' => wp_get_attachment_image_url($thumbnail_id, 'medium'),
                'thumbnail' => wp_get_attachment_image_url($thumbnail_id, 'thumbnail')
            ) : null,
            'acf_fields' => function_exists('get_fields') ? get_fields($post_id) : null,
            'excerpt' => get_the_excerpt($post_id),
            'content_length' => strlen(get_post_field('post_content', $post_id))
        ), 200);
    }
    
    /**
     * Register custom REST fields for featured images
     * This adds featured_image_url field to all post types for easier access
     */
    public function register_featured_image_fields() {
        $post_types = array('eat', 'event', 'activity', 'guide', 'post');
        
        foreach ($post_types as $post_type) {
            register_rest_field($post_type, 'featured_image_url', array(
                'get_callback' => function($post) {
                    $image_id = get_post_thumbnail_id($post['id']);
                    
                    if (!$image_id) {
                        return null;
                    }
                    
                    return array(
                        'full' => wp_get_attachment_image_url($image_id, 'full'),
                        'large' => wp_get_attachment_image_url($image_id, 'large'),
                        'medium' => wp_get_attachment_image_url($image_id, 'medium'),
                        'thumbnail' => wp_get_attachment_image_url($image_id, 'thumbnail'),
                        'id' => $image_id
                    );
                },
                'schema' => array(
                    'description' => 'Featured image URLs in various sizes',
                    'type' => 'object'
                )
            ));
            
            // Also add ACF gallery URLs if gallery field exists
            register_rest_field($post_type, 'gallery_urls', array(
                'get_callback' => function($post) {
                    if (!function_exists('get_field')) {
                        return null;
                    }
                    
                    $gallery = get_field('gallery', $post['id']);
                    if (!$gallery || !is_array($gallery)) {
                        return null;
                    }
                    
                    $urls = array();
                    foreach ($gallery as $image) {
                        // ACF gallery returns full objects, not just IDs
                        $image_id = null;
                        
                        if (is_numeric($image)) {
                            // If it's just an ID
                            $image_id = intval($image);
                        } elseif (is_array($image) && isset($image['ID'])) {
                            // If it's an object with ID property
                            $image_id = intval($image['ID']);
                        } elseif (is_object($image) && isset($image->ID)) {
                            // If it's an object with ID property
                            $image_id = intval($image->ID);
                        }
                        
                        if ($image_id) {
                            $urls[] = array(
                                'id' => $image_id,
                                'full' => wp_get_attachment_image_url($image_id, 'full'),
                                'large' => wp_get_attachment_image_url($image_id, 'large'),
                                'medium' => wp_get_attachment_image_url($image_id, 'medium'),
                                'thumbnail' => wp_get_attachment_image_url($image_id, 'thumbnail')
                            );
                        }
                    }
                    
                    return $urls;
                },
                'schema' => array(
                    'description' => 'Gallery image URLs in various sizes',
                    'type' => 'array'
                )
            ));
            
            // Add offers field - reads from post meta (offer_title_1, offer_description_1, etc.)
            register_rest_field($post_type, 'offers', array(
                'get_callback' => function($post) {
                    $offers = array();
                    
                    // Check up to 20 offers (covers all subscription tiers)
                    for ($i = 1; $i <= 20; $i++) {
                        // Get from post meta (where Venue Portal saves them)
                        $title = get_post_meta($post['id'], 'offer_title_' . $i, true);
                        
                        // Also try ACF as fallback
                        if (empty($title) && function_exists('get_field')) {
                            $title = get_field('offer_title_' . $i, $post['id']);
                        }
                        
                        // Skip if no title
                        if (empty($title)) {
                            continue;
                        }
                        
                        // Get description
                        $description = get_post_meta($post['id'], 'offer_description_' . $i, true);
                        if (empty($description) && function_exists('get_field')) {
                            $description = get_field('offer_description_' . $i, $post['id']);
                        }
                        
                        $offers[] = array(
                            'id' => $i,
                            'title' => $title,
                            'description' => $description ? wp_kses_post($description) : ''
                        );
                    }
                    
                    // Also check legacy single offer field for backwards compatibility
                    $legacy_title = get_post_meta($post['id'], 'offer_title', true);
                    if (empty($legacy_title) && function_exists('get_field')) {
                        $legacy_title = get_field('offer_title', $post['id']);
                    }
                    
                    if (!empty($legacy_title) && empty($offers)) {
                        $legacy_description = get_post_meta($post['id'], 'offer_description', true);
                        if (empty($legacy_description) && function_exists('get_field')) {
                            $legacy_description = get_field('offer_description', $post['id']);
                        }
                        
                        $offers[] = array(
                            'id' => 1,
                            'title' => $legacy_title,
                            'description' => $legacy_description ? wp_kses_post($legacy_description) : ''
                        );
                    }
                    
                    // Get CTA if exists
                    $cta_text = get_post_meta($post['id'], 'offer_cta_text', true);
                    $cta_url = get_post_meta($post['id'], 'offer_cta_url', true);
                    
                    if (empty($cta_text) && function_exists('get_field')) {
                        $cta_text = get_field('offer_cta_text', $post['id']);
                        $cta_url = get_field('offer_cta_url', $post['id']);
                    }
                    
                    return array(
                        'items' => $offers,
                        'count' => count($offers),
                        'cta' => ($cta_text && $cta_url) ? array(
                            'text' => $cta_text,
                            'url' => $cta_url
                        ) : null
                    );
                },
                'schema' => array(
                    'description' => 'All active offers for this listing',
                    'type' => 'object'
                )
            ));
        }
    }
    
    /**
     * Display venue dashboard (main portal page)
     */
    public function display_venue_dashboard() {
        $user_venues = $this->get_user_venues();
        
        if (empty($user_venues)) {
            echo '<div class="wrap"><h1>Venue Portal</h1><div class="notice notice-error"><p>No venues assigned to your account. Please contact support.</p></div></div>';
            return;
        }
        
        // Get statistics for all assigned venues
        $stats = $this->get_venue_statistics($user_venues);
        $primary_venue_id = $user_venues[0];
        $primary_venue = get_post($primary_venue_id);
        
        ?>
        <div class="wrap">
            <h1>Venue Portal - <?php echo esc_html($primary_venue ? $primary_venue->post_title : 'Dashboard'); ?></h1>
            
            <?php if (count($user_venues) > 1): ?>
                <div class="notice notice-info">
                    <p>You are managing <?php echo count($user_venues); ?> venues.</p>
                </div>
            <?php endif; ?>
            
            <div style="background: #0f0032; color: #fff; padding: 30px; border-radius: 8px; margin: 20px 0;">
                <h2 style="color: #fbc903; margin: 0 0 15px 0; font-size: 20px; font-weight: 600;">📊 Total Redemptions</h2>
                <div style="font-size: 56px; font-weight: bold; color: #fbc903; margin: 10px 0 15px 0; line-height: 1.2;"><?php echo number_format($stats['total_scans']); ?></div>
                <p style="margin: 0; opacity: 0.9; font-size: 14px;">QR codes scanned at your venue(s)</p>
            </div>
            
            <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; margin: 20px 0;">
                <div style="background: #fff; padding: 20px; border: 1px solid #ddd; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                    <div style="color: #666; font-size: 13px; margin-bottom: 8px; font-weight: 500;">Today's Scans</div>
                    <div style="font-size: 32px; font-weight: bold; color: #0f0032;"><?php echo number_format($stats['today_scans']); ?></div>
                </div>
                
                <div style="background: #fff; padding: 20px; border: 1px solid #ddd; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                    <div style="color: #666; font-size: 13px; margin-bottom: 8px; font-weight: 500;">This Week</div>
                    <div style="font-size: 32px; font-weight: bold; color: #0f0032;"><?php echo number_format($stats['weekly_scans']); ?></div>
                </div>
                
                <div style="background: #fff; padding: 20px; border: 1px solid #ddd; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                    <div style="color: #666; font-size: 13px; margin-bottom: 8px; font-weight: 500;">This Month</div>
                    <div style="font-size: 32px; font-weight: bold; color: #0f0032;"><?php echo number_format($stats['monthly_scans']); ?></div>
                </div>
                
                <div style="background: #fff; padding: 20px; border: 1px solid #ddd; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                    <div style="color: #666; font-size: 13px; margin-bottom: 8px; font-weight: 500;">Most Active Day</div>
                    <div style="font-size: 24px; font-weight: bold; color: #0f0032;"><?php echo esc_html($stats['most_active_day']); ?></div>
                </div>
            </div>
            
            <div style="background: #fff; padding: 20px; border: 1px solid #ddd; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin-top: 20px;">
                <h2>Recent Redemptions</h2>
                <?php if (empty($stats['recent_scans'])): ?>
                    <p>No redemptions yet. When members scan their QR codes at your venue, they'll appear here.</p>
                <?php else: ?>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Offer</th>
                                <th>User who redeemed</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach (array_slice($stats['recent_scans'], 0, 10) as $scan): ?>
                                <tr>
                                    <td><?php echo date('Y-m-d H:i:s', strtotime($scan['timestamp'])); ?></td>
                                    <td><?php echo isset($scan['offer_title']) && !empty($scan['offer_title']) ? esc_html($scan['offer_title']) : 'N/A'; ?></td>
                                    <td><?php 
                                        $user_display = 'Unknown';
                                        if (isset($scan['user_id'])) {
                                            $user_obj = get_user_by('ID', $scan['user_id']);
                                            if ($user_obj) {
                                                $user_display = esc_html($user_obj->display_name) . ' (' . esc_html($user_obj->user_email) . ')';
                                            }
                                        } elseif (isset($scan['user_email'])) {
                                            $user_obj = get_user_by('email', $scan['user_email']);
                                            if ($user_obj) {
                                                $user_display = esc_html($user_obj->display_name) . ' (' . esc_html($scan['user_email']) . ')';
                                            } else {
                                                $user_display = esc_html($scan['user_email']);
                                            }
                                        }
                                        echo $user_display;
                                    ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
        
        <style>
            .hunow-stats-header {
                background: linear-gradient(135deg, #0f0032 0%, #1a0047 100%);
            }
        </style>
        <?php
    }
    
    /**
     * Get statistics for specific venue IDs
     */
    private function get_venue_statistics($venue_ids) {
        $users = get_users();
        $stats = array(
            'total_scans' => 0,
            'today_scans' => 0,
            'weekly_scans' => 0,
            'monthly_scans' => 0,
            'day_counts' => array('Monday' => 0, 'Tuesday' => 0, 'Wednesday' => 0, 'Thursday' => 0, 'Friday' => 0, 'Saturday' => 0, 'Sunday' => 0),
            'recent_scans' => array()
        );
        
        // Get venue names for matching (in case scans use venue_name instead of venue_id)
        $venue_names = array();
        foreach ($venue_ids as $venue_id) {
            $venue_post = get_post($venue_id);
            if ($venue_post) {
                $venue_names[] = strtolower(trim($venue_post->post_title));
            }
        }
        
        $today = strtotime('today');
        $week_ago = strtotime('-7 days');
        $month_ago = strtotime('-30 days');
        
        foreach ($users as $user) {
            $scan_history = get_user_meta($user->ID, 'membership_card_history', true);
            
            if (is_array($scan_history) && !empty($scan_history)) {
                foreach ($scan_history as $scan) {
                    $scan_venue_id = isset($scan['venue_id']) ? intval($scan['venue_id']) : 0;
                    $scan_venue_name = isset($scan['venue_name']) ? strtolower(trim($scan['venue_name'])) : '';
                    
                    // Match by venue_id OR venue_name (for password-based scans)
                    $matches = false;
                    if ($scan_venue_id > 0 && in_array($scan_venue_id, $venue_ids)) {
                        $matches = true;
                    } elseif (!empty($scan_venue_name) && in_array($scan_venue_name, $venue_names)) {
                        $matches = true;
                    }
                    
                    if (!$matches) {
                        continue;
                    }
                    
                    $scan_timestamp = isset($scan['timestamp']) ? strtotime($scan['timestamp']) : 0;
                    
                    // Add user info to scan for dashboard display
                    $scan_with_user = $scan;
                    $scan_with_user['user_id'] = $user->ID;
                    $scan_with_user['user_email'] = $user->user_email;
                    
                    $stats['total_scans']++;
                    $stats['recent_scans'][] = $scan_with_user;
                    
                    if ($scan_timestamp >= $today) {
                        $stats['today_scans']++;
                    }
                    if ($scan_timestamp >= $week_ago) {
                        $stats['weekly_scans']++;
                    }
                    if ($scan_timestamp >= $month_ago) {
                        $stats['monthly_scans']++;
                    }
                    
                    if ($scan_timestamp > 0) {
                        $day_name = date('l', $scan_timestamp);
                        if (isset($stats['day_counts'][$day_name])) {
                            $stats['day_counts'][$day_name]++;
                        }
                    }
                }
            }
        }
        
        // Sort recent scans by timestamp (newest first)
        usort($stats['recent_scans'], function($a, $b) {
            $time_a = isset($a['timestamp']) ? strtotime($a['timestamp']) : 0;
            $time_b = isset($b['timestamp']) ? strtotime($b['timestamp']) : 0;
            return $time_b - $time_a;
        });
        
        // Get most active day
        arsort($stats['day_counts']);
        $stats['most_active_day'] = !empty($stats['day_counts']) ? key($stats['day_counts']) : 'N/A';
        
        return $stats;
    }
    
    /**
     * Display venue analytics page
     */
    public function display_venue_analytics() {
        $user_venues = $this->get_user_venues();
        
        if (empty($user_venues)) {
            echo '<div class="wrap"><h1>Analytics</h1><div class="notice notice-error"><p>No venues assigned to your account.</p></div></div>';
            return;
        }
        
        $stats = $this->get_venue_statistics($user_venues);
        
        ?>
        <div class="wrap">
            <h1>Analytics</h1>
            
            <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px; margin: 20px 0;">
                <div style="background: #fff; padding: 20px; border: 1px solid #ddd; border-radius: 8px;">
                    <h3>Activity by Day of Week</h3>
                    <table class="wp-list-table widefat fixed">
                        <thead>
                            <tr>
                                <th>Day</th>
                                <th>Redemptions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($stats['day_counts'] as $day => $count): ?>
                                <tr>
                                    <td><?php echo esc_html($day); ?></td>
                                    <td><strong><?php echo number_format($count); ?></strong></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <div style="background: #fff; padding: 20px; border: 1px solid #ddd; border-radius: 8px;">
                    <h3>Export Data</h3>
                    <p>Download your analytics data for further analysis.</p>
                    <a href="<?php echo admin_url('admin.php?page=venue-analytics&action=export&_wpnonce=' . wp_create_nonce('export_analytics')); ?>" class="button button-primary">Export CSV</a>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Display venue scan history page with detailed redemption information
     */
    public function display_venue_scan_history() {
        $user_venues = $this->get_user_venues();
        
        if (empty($user_venues)) {
            echo '<div class="wrap"><h1>Scan History</h1><div class="notice notice-error"><p>No venues assigned to your account.</p></div></div>';
            return;
        }
        
        // Get all scans for assigned venues
        $all_scans = array();
        $users = get_users();
        
        foreach ($users as $user) {
            $scan_history = get_user_meta($user->ID, 'membership_card_history', true);
            
            if (is_array($scan_history) && !empty($scan_history)) {
                foreach ($scan_history as $scan) {
                    $scan_venue_id = isset($scan['venue_id']) ? intval($scan['venue_id']) : 0;
                    $scan_venue_name = isset($scan['venue_name']) ? strtolower(trim($scan['venue_name'])) : '';
                    
                    // Get venue names for assigned venues
                    $assigned_venue_names = array();
                    foreach ($user_venues as $venue_id) {
                        $venue_post = get_post($venue_id);
                        if ($venue_post) {
                            $assigned_venue_names[] = strtolower(trim($venue_post->post_title));
                        }
                    }
                    
                    // Match by venue_id OR venue_name (for password-based scans)
                    $matches = false;
                    if ($scan_venue_id > 0 && in_array($scan_venue_id, $user_venues)) {
                        $matches = true;
                    } elseif (!empty($scan_venue_name) && in_array($scan_venue_name, $assigned_venue_names)) {
                        $matches = true;
                    }
                    
                    if ($matches) {
                        $scan['member_id'] = $user->ID;
                        $scan['member_name'] = $user->display_name;
                        $scan['member_email'] = $user->user_email;
                        $all_scans[] = $scan;
                    }
                }
            }
        }
        
        // Sort by timestamp (newest first)
        usort($all_scans, function($a, $b) {
            $time_a = isset($a['timestamp']) ? strtotime($a['timestamp']) : 0;
            $time_b = isset($b['timestamp']) ? strtotime($b['timestamp']) : 0;
            return $time_b - $time_a;
        });
        
        // Pagination
        $per_page = 50;
        $current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        $total_scans = count($all_scans);
        $total_pages = ceil($total_scans / $per_page);
        $offset = ($current_page - 1) * $per_page;
        $paginated_scans = array_slice($all_scans, $offset, $per_page);
        
        ?>
        <div class="wrap">
            <h1>Scan History</h1>
            <p>Detailed view of all QR code redemptions at your venue(s).</p>
            
            <?php if (empty($all_scans)): ?>
                <div class="notice notice-info">
                    <p>No scans recorded yet. When members scan their QR codes at your venue, they'll appear here.</p>
                </div>
            <?php else: ?>
                <div style="background: #fff; padding: 20px; border: 1px solid #ddd; border-radius: 8px; margin: 20px 0;">
                    <div style="margin-bottom: 15px;">
                        <strong>Total Scans:</strong> <?php echo number_format($total_scans); ?>
                    </div>
                    
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th style="width: 12%;">Date & Time</th>
                                <th style="width: 18%;">Member</th>
                                <th style="width: 20%;">Email</th>
                                <th style="width: 12%;">Location</th>
                                <th style="width: 10%;">IP Address</th>
                                <th style="width: 13%;">Venue</th>
                                <th style="width: 15%;">Offer</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($paginated_scans as $scan): 
                                $scan_time = isset($scan['timestamp']) ? strtotime($scan['timestamp']) : 0;
                                $date_formatted = $scan_time ? date('Y-m-d H:i:s', $scan_time) : 'Unknown';
                                $date_nice = $scan_time ? date('M j, Y g:i A', $scan_time) : 'Unknown';
                                
                                // Get venue name
                                $venue_name = 'Unknown';
                                if (isset($scan['venue_name']) && !empty($scan['venue_name'])) {
                                    $venue_name = esc_html($scan['venue_name']);
                                } elseif (isset($scan['venue_id']) && !empty($scan['venue_id'])) {
                                    $venue_post = get_post($scan['venue_id']);
                                    if ($venue_post) {
                                        $venue_name = esc_html($venue_post->post_title);
                                    }
                                }
                                
                                // Location
                                $location_html = 'N/A';
                                if (isset($scan['latitude']) && isset($scan['longitude']) && $scan['latitude'] != 0) {
                                    $lat = floatval($scan['latitude']);
                                    $lng = floatval($scan['longitude']);
                                    $map_link = "https://www.google.com/maps?q={$lat},{$lng}";
                                    $location_html = "<a href='{$map_link}' target='_blank' style='color: #fbc903; text-decoration: none;'>📍 View Map</a><br><small style='color: #666;'>{$lat}, {$lng}</small>";
                                }
                                
                                // IP address
                                $ip_address = isset($scan['ip']) ? esc_html($scan['ip']) : 'N/A';
                                
                                // Member info
                                $member_name = isset($scan['member_name']) ? esc_html($scan['member_name']) : 'Unknown';
                                $member_email = isset($scan['member_email']) ? esc_html($scan['member_email']) : 'N/A';
                                
                                // Offer info
                                $offer_title = isset($scan['offer_title']) && !empty($scan['offer_title']) ? esc_html($scan['offer_title']) : 'N/A';
                            ?>
                                <tr>
                                    <td>
                                        <strong><?php echo esc_html($date_nice); ?></strong><br>
                                        <small style="color: #666;"><?php echo esc_html($date_formatted); ?></small>
                                    </td>
                                    <td><?php echo esc_html($member_name); ?></td>
                                    <td><?php echo esc_html($member_email); ?></td>
                                    <td><?php echo $location_html; ?></td>
                                    <td><small><?php echo $ip_address; ?></small></td>
                                    <td><strong style="color: #0f0032;"><?php echo $venue_name; ?></strong></td>
                                    <td><?php echo $offer_title; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    
                    <?php if ($total_pages > 1): ?>
                        <div class="tablenav">
                            <div class="tablenav-pages">
                                <?php
                                $page_links = paginate_links(array(
                                    'base' => add_query_arg('paged', '%#%'),
                                    'format' => '',
                                    'prev_text' => '&laquo;',
                                    'next_text' => '&raquo;',
                                    'total' => $total_pages,
                                    'current' => $current_page
                                ));
                                echo $page_links;
                                ?>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <div style="margin-top: 20px; padding: 15px; background: #f0f0f0; border-left: 4px solid #fbc903; border-radius: 4px;">
                        <strong>Export Data:</strong> 
                        <a href="<?php echo admin_url('admin.php?page=venue-scan-history&action=export&_wpnonce=' . wp_create_nonce('export_scans')); ?>" class="button button-secondary" style="margin-left: 10px;">Export All Scans (CSV)</a>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }
    
    /**
     * Display venue offers management page
     */
    public function display_venue_offers() {
        $user_venues = $this->get_user_venues();
        
        if (empty($user_venues)) {
            echo '<div class="wrap"><h1>My Offers</h1><div class="notice notice-error"><p>No venues assigned to your account.</p></div></div>';
            return;
        }
        
        $primary_venue_id = $user_venues[0];
        $primary_venue = get_post($primary_venue_id);
        
        // Get user's subscription tier to determine max offers
        $user_id = get_current_user_id();
        $subscription_tier = get_user_meta($user_id, 'subscription_tier', true);
        if (empty($subscription_tier)) $subscription_tier = 'basic';
        $max_offers = $this->get_max_offers_by_tier($subscription_tier);
        
        // Handle offer updates
        if (isset($_POST['update_offers']) && wp_verify_nonce($_POST['_wpnonce'], 'update_offers_' . $primary_venue_id)) {
            // Save multiple offers (offer_title_1, offer_title_2, etc. and offer_description_1, offer_description_2, etc.)
            // Use update_post_meta directly to ensure data is saved correctly and can be retrieved by get_post_meta()
            for ($i = 1; $i <= $max_offers; $i++) {
                // Save offer title
                $offer_key = 'offer_title_' . $i;
                if (isset($_POST[$offer_key])) {
                    $offer_value = sanitize_text_field($_POST[$offer_key]);
                    // Save directly to post_meta AND via ACF (if available) for compatibility
                    update_post_meta($primary_venue_id, $offer_key, $offer_value);
                    if (function_exists('update_field')) {
                        update_field($offer_key, $offer_value, $primary_venue_id);
                    }
                } else {
                    // Clear empty fields
                    delete_post_meta($primary_venue_id, $offer_key);
                }
                
                // Save offer description
                $desc_key = 'offer_description_' . $i;
                if (isset($_POST[$desc_key])) {
                    $desc_value = wp_kses_post($_POST[$desc_key]);
                    // Save directly to post_meta AND via ACF (if available) for compatibility
                    update_post_meta($primary_venue_id, $desc_key, $desc_value);
                    if (function_exists('update_field')) {
                        update_field($desc_key, $desc_value, $primary_venue_id);
                    }
                } else {
                    // Clear empty fields
                    delete_post_meta($primary_venue_id, $desc_key);
                }
            }
            
            echo '<div class="notice notice-success"><p>Offers updated successfully!</p></div>';
        }
        
        // Get current offers
        $current_offers = array();
        if (function_exists('get_field')) {
            for ($i = 1; $i <= $max_offers; $i++) {
                $offer_value = get_field('offer_title_' . $i, $primary_venue_id);
                $current_offers[$i] = array(
                    'title' => $offer_value ? $offer_value : '',
                    'description' => ''
                );
                
                // Get offer description
                $desc_value = get_field('offer_description_' . $i, $primary_venue_id);
                $current_offers[$i]['description'] = $desc_value ? $desc_value : '';
            }
        }
        
        ?>
        <div class="wrap">
            <h1>Manage Offers - <?php echo esc_html($primary_venue ? $primary_venue->post_title : 'My Venue'); ?></h1>
            
            <div style="background: #f0f0f0; padding: 15px; border-left: 4px solid #fbc903; margin-bottom: 20px; border-radius: 4px;">
                <strong>Your Plan:</strong> 
                <?php 
                $tier_names = array('basic' => 'Basic', 'tier2' => 'Tier 2', 'tier3' => 'Tier 3', 'premium' => 'Premium');
                echo esc_html($tier_names[$subscription_tier] ?? 'Basic'); 
                ?> - 
                You can create up to <strong><?php echo $max_offers === 999 ? 'unlimited' : $max_offers; ?></strong> offers.
            </div>
            
            <form method="post" action="">
                <?php wp_nonce_field('update_offers_' . $primary_venue_id); ?>
                
                <div style="display: grid; gap: 20px; margin-bottom: 20px;">
                    <?php for ($i = 1; $i <= $max_offers; $i++): ?>
                        <div style="background: #fff; padding: 20px; border: 1px solid #ddd; border-radius: 8px;">
                            <h3 style="margin-top: 0; color: #0f0032;">Offer <?php echo $i; ?></h3>
                            <table class="form-table">
                                <tr>
                                    <th style="width: 150px;"><label for="offer_title_<?php echo $i; ?>">Offer Title</label></th>
                                    <td>
                                        <input type="text" id="offer_title_<?php echo $i; ?>" name="offer_title_<?php echo $i; ?>" 
                                               value="<?php echo esc_attr($current_offers[$i]['title'] ?? ''); ?>" 
                                               class="regular-text" 
                                               placeholder="e.g., 20% off all mains" />
                                        <p class="description">This is the offer name that will appear when staff redeem QR codes.</p>
                                    </td>
                                </tr>
                                <tr>
                                    <th style="width: 150px;"><label for="offer_description_<?php echo $i; ?>">Offer Description</label></th>
                                    <td>
                                        <?php
                                        wp_editor(
                                            $current_offers[$i]['description'] ?? '',
                                            'offer_description_' . $i,
                                            array(
                                                'textarea_name' => 'offer_description_' . $i,
                                                'textarea_rows' => 5,
                                                'media_buttons' => false,
                                                'teeny' => true,
                                                'quicktags' => true
                                            )
                                        );
                                        ?>
                                        <p class="description">A detailed description of this offer (optional).</p>
                                    </td>
                                </tr>
                            </table>
                        </div>
                    <?php endfor; ?>
                </div>
                
                <p class="submit">
                    <input type="submit" name="update_offers" class="button button-primary" value="Update All Offers" />
                </p>
            </form>
            
            <div style="background: #f0f0f0; padding: 15px; border-left: 4px solid #fbc903; margin-top: 20px; border-radius: 4px;">
                <strong>How it works:</strong> When staff scan a member's QR code and enter your venue password, they'll see all your active offers listed. They can then select which offer is being redeemed.
            </div>
        </div>
        <?php
    }
    
    /**
     * Display venue listing management page
     */
    public function display_venue_listing() {
        $user_venues = $this->get_user_venues();
        
        if (empty($user_venues)) {
            echo '<div class="wrap"><h1>My Listing</h1><div class="notice notice-error"><p>No venues assigned to your account.</p></div></div>';
            return;
        }
        
        $primary_venue_id = $user_venues[0];
        $primary_venue = get_post($primary_venue_id);
        
        if (!$primary_venue) {
            echo '<div class="wrap"><h1>My Listing</h1><div class="notice notice-error"><p>Venue not found.</p></div></div>';
            return;
        }
        
        // Handle listing updates
        if (isset($_POST['update_listing']) && wp_verify_nonce($_POST['_wpnonce'], 'update_listing_' . $primary_venue_id)) {
            if (current_user_can('edit_post', $primary_venue_id)) {
                $post_data = array(
                    'ID' => $primary_venue_id,
                    'post_title' => sanitize_text_field($_POST['listing_title']),
                    'post_content' => wp_kses_post($_POST['listing_content']),
                    'post_status' => 'publish' // Publish directly, no review needed
                );
                wp_update_post($post_data);
                
                echo '<div class="notice notice-success"><p>Listing updated successfully!</p></div>';
                // Refresh post object
                $primary_venue = get_post($primary_venue_id);
            }
        }
        
        // Handle password update
        if (isset($_POST['update_password']) && wp_verify_nonce($_POST['_wpnonce'], 'update_password_' . $primary_venue_id)) {
            $new_password = sanitize_text_field($_POST['venue_password']);
            
            if (function_exists('update_field')) {
                update_field('venue_password', $new_password, $primary_venue_id);
                echo '<div class="notice notice-success"><p>Password updated successfully!</p></div>';
            }
        }
        
        // Get current password
        $current_password = function_exists('get_field') ? get_field('venue_password', $primary_venue_id) : '';
        $password_display = !empty($current_password) ? str_repeat('•', min(strlen($current_password), 10)) : '(Not set)';
        
        $view_url = get_permalink($primary_venue_id);
        
        ?>
        <div class="wrap">
            <h1>My Listing - <?php echo esc_html($primary_venue->post_title); ?></h1>
            
            <div style="background: #fff; padding: 20px; border: 1px solid #ddd; border-radius: 8px; margin: 20px 0;">
                <h2>Edit Listing</h2>
                <p style="color: #666; margin-bottom: 15px;">Edit your venue's title and description below, or use the full editor to edit all fields, images, and ACF data.</p>
                
                <p style="margin-bottom: 15px;">
                    <a href="<?php echo esc_url(admin_url('post.php?post=' . $primary_venue_id . '&action=edit')); ?>" class="button button-primary" target="_blank">Open Full Editor (All Fields & Media)</a>
                </p>
                
                <form method="post" action="">
                    <?php wp_nonce_field('update_listing_' . $primary_venue_id); ?>
                    
                    <table class="form-table">
                        <tr>
                            <th><label for="listing_title">Venue Title</label></th>
                            <td>
                                <input type="text" id="listing_title" name="listing_title" value="<?php echo esc_attr($primary_venue->post_title); ?>" class="regular-text" required />
                            </td>
                        </tr>
                        <tr>
                            <th><label for="listing_content">Description</label></th>
                            <td>
                                <?php
                                wp_editor($primary_venue->post_content, 'listing_content', array(
                                    'textarea_name' => 'listing_content',
                                    'textarea_rows' => 10,
                                    'media_buttons' => true,
                                    'teeny' => false
                                ));
                                ?>
                            </td>
                        </tr>
                    </table>
                    
                    <p class="submit">
                        <input type="submit" name="update_listing" class="button button-primary" value="Update Listing" />
                    </p>
                </form>
            </div>
            
            <div style="background: #fff; padding: 20px; border: 1px solid #ddd; border-radius: 8px; margin: 20px 0;">
                <h2>QR Code Scan Password</h2>
                <p style="color: #666; margin-bottom: 15px;">Set a password for your venue. Staff will use this password when scanning member QR codes.</p>
                
                <form method="post" action="">
                    <?php wp_nonce_field('update_password_' . $primary_venue_id); ?>
                    
                    <table class="form-table">
                        <tr>
                            <th><label for="venue_password">Venue Password</label></th>
                            <td>
                                <input type="text" id="venue_password" name="venue_password" value="<?php echo esc_attr($current_password); ?>" class="regular-text" placeholder="Enter password for QR scanning" required />
                                <p class="description">Current password: <strong><?php echo esc_html($password_display); ?></strong></p>
                            </td>
                        </tr>
                    </table>
                    
                    <p class="submit">
                        <input type="submit" name="update_password" class="button button-primary" value="Update Password" />
                    </p>
                </form>
            </div>
            
            <div style="margin-top: 20px;">
                <a href="<?php echo esc_url($view_url); ?>" class="button" target="_blank">View Public Listing</a>
            </div>
            
            <div style="background: #f0f0f0; padding: 15px; border-left: 4px solid #fbc903; margin-top: 20px; border-radius: 4px;">
                <strong>Note:</strong> You can edit your venue's title and description above. Other details like images and ACF fields may need to be edited via the full WordPress editor if needed.
            </div>
        </div>
        <?php
    }
    
    /**
     * Display venue subscription page
     */
    public function display_venue_subscription() {
        $user_id = get_current_user_id();
        $subscription_tier = get_user_meta($user_id, 'subscription_tier', true);
        $subscription_expiry = get_user_meta($user_id, 'subscription_expiry', true);
        
        $tiers = array(
            'basic' => array('name' => 'Basic', 'price' => 'FREE', 'features' => array('Portal access', '1 active offer', 'Basic analytics', 'Standard listing')),
            'tier2' => array('name' => 'Tier 2', 'price' => '£29.99/month', 'features' => array('Portal access', '3 active offers', 'Basic analytics', 'Standard listing')),
            'tier3' => array('name' => 'Tier 3', 'price' => '£79.99/month', 'features' => array('Everything in Tier 2', 'Unlimited offers', 'Advanced analytics', 'Featured placement', 'Newsletter ad (1x/month)', 'Priority support')),
            'premium' => array('name' => 'Premium', 'price' => '£149/month', 'features' => array('Everything in Tier 3', 'Homepage spotlight', 'Newsletter ad (2x/month)', 'Dedicated account manager', 'Custom branding', 'Early access to features'))
        );
        
        $current_tier = isset($tiers[$subscription_tier]) ? $tiers[$subscription_tier] : $tiers['basic'];
        
        ?>
        <div class="wrap">
            <h1>Subscription</h1>
            
            <div style="background: #fff; padding: 30px; border: 1px solid #ddd; border-radius: 8px; margin: 20px 0;">
                <h2 style="color: #0f0032; margin-top: 0;">Current Plan: <?php echo esc_html($current_tier['name']); ?></h2>
                <p style="font-size: 24px; color: #fbc903; font-weight: bold;"><?php echo esc_html($current_tier['price']); ?></p>
                
                <?php if ($subscription_expiry): ?>
                    <p><strong>Renewal Date:</strong> <?php echo date('F j, Y', strtotime($subscription_expiry)); ?></p>
                <?php endif; ?>
                
                <h3>Your Features:</h3>
                <ul style="list-style: disc; margin-left: 20px;">
                    <?php foreach ($current_tier['features'] as $feature): ?>
                        <li><?php echo esc_html($feature); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            
            <div style="background: #f0f0f0; padding: 15px; border-left: 4px solid #fbc903; margin-top: 20px; border-radius: 4px;">
                <strong>Want to upgrade?</strong> Contact support at <a href="mailto:support@hunow.co.uk">support@hunow.co.uk</a> or call us to discuss upgrading your plan.
            </div>
        </div>
        <?php
    }
    
    /**
     * Add venue assignment fields to user profile (admin only)
     */
    public function add_venue_assignment_fields($user) {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        $assigned_venues = get_user_meta($user->ID, 'assigned_venues', true);
        if (!is_array($assigned_venues)) {
            $assigned_venues = array();
        }
        
        $subscription_tier = get_user_meta($user->ID, 'subscription_tier', true);
        $subscription_expiry = get_user_meta($user->ID, 'subscription_expiry', true);
        
        // Get all venues (eat, activity, event posts)
        $venue_posts = get_posts(array(
            'post_type' => array('eat', 'activity', 'event'),
            'posts_per_page' => -1,
            'post_status' => 'any'
        ));
        
        ?>
        <h3>HU NOW Venue Assignment</h3>
        <table class="form-table">
            <tr>
                <th><label for="assigned_venues">Assigned Venues</label></th>
                <td>
                    <select name="assigned_venues[]" id="assigned_venues" multiple style="width: 100%; height: 150px;">
                        <?php foreach ($venue_posts as $venue): ?>
                            <option value="<?php echo $venue->ID; ?>" <?php selected(in_array($venue->ID, $assigned_venues)); ?>>
                                [<?php echo esc_html($venue->post_type); ?>] <?php echo esc_html($venue->post_title); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="description">Hold Ctrl/Cmd to select multiple venues. Assign venues to this venue manager account.</p>
                </td>
            </tr>
            <tr>
                <th><label for="subscription_tier">Subscription Tier</label></th>
                <td>
                    <select name="subscription_tier" id="subscription_tier">
                        <option value="basic" <?php selected($subscription_tier, 'basic'); ?>>Basic (FREE)</option>
                        <option value="tier2" <?php selected($subscription_tier, 'tier2'); ?>>Tier 2 (£29.99/month)</option>
                        <option value="tier3" <?php selected($subscription_tier, 'tier3'); ?>>Tier 3 (£79.99/month)</option>
                        <option value="premium" <?php selected($subscription_tier, 'premium'); ?>>Premium (£149/month)</option>
                    </select>
                </td>
            </tr>
            <tr>
                <th><label for="subscription_expiry">Subscription Expiry Date</label></th>
                <td>
                    <input type="date" name="subscription_expiry" id="subscription_expiry" value="<?php echo esc_attr($subscription_expiry); ?>" class="regular-text" />
                    <p class="description">When does this subscription expire? Leave empty for no expiry.</p>
                </td>
            </tr>
        </table>
        <?php
    }
    
    /**
     * Save venue assignment fields
     */
    public function save_venue_assignment_fields($user_id) {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        if (isset($_POST['assigned_venues']) && is_array($_POST['assigned_venues'])) {
            $venues = array_map('intval', $_POST['assigned_venues']);
            update_user_meta($user_id, 'assigned_venues', $venues);
        } else {
            update_user_meta($user_id, 'assigned_venues', array());
        }
        
        if (isset($_POST['subscription_tier'])) {
            update_user_meta($user_id, 'subscription_tier', sanitize_text_field($_POST['subscription_tier']));
        }
        
        if (isset($_POST['subscription_expiry'])) {
            update_user_meta($user_id, 'subscription_expiry', sanitize_text_field($_POST['subscription_expiry']));
        }
    }
    
    /**
     * Handle QR code verification page (?verify=HUNOW-{email}-{hash})
     * Shows password form, verifies password, records redemption
     */
    public function handle_verify_page() {
        if (!isset($_GET['verify'])) {
            return;
        }
        
        $verify_param = sanitize_text_field($_GET['verify']);
        
        // Check if it's a HUNOW membership verification (format: HUNOW-{email}-{hash})
        if (strpos($verify_param, 'HUNOW-') !== 0) {
            return;
        }
        
        // Parse membership ID (format: HUNOW-{email}-{hash})
        // Handle email which may contain dots/at symbols - use preg_split with limit
        $parts = preg_split('/-/', $verify_param, 3);
        if (count($parts) !== 3 || $parts[0] !== 'HUNOW') {
            $this->show_verify_error('Invalid membership ID format');
            exit;
        }
        
        $email = $parts[1];
        $hash = $parts[2];
        
        // Find user by email
        $user = get_user_by('email', $email);
        if (!$user) {
            $this->show_verify_error('Member not found');
            exit;
        }
        
        $user_id = $user->ID;
        $message = '';
        $error = '';
        
        // Handle form submission
        if (isset($_POST['venue_password']) && isset($_POST['verify_nonce']) && wp_verify_nonce($_POST['verify_nonce'], 'hunow_verify_' . $user_id)) {
            $venue_password = sanitize_text_field($_POST['venue_password']);
            $latitude = isset($_POST['lat']) ? floatval($_POST['lat']) : (isset($_GET['lat']) ? floatval($_GET['lat']) : 0);
            $longitude = isset($_POST['lng']) ? floatval($_POST['lng']) : (isset($_GET['lng']) ? floatval($_GET['lng']) : 0);
            
            // Find venue by password
            $venue_id = null;
            $venue_name = null;
            
            if (function_exists('get_field')) {
                $all_venues = get_posts(array(
                    'post_type' => array('eat', 'activity', 'event'),
                    'posts_per_page' => -1,
                    'post_status' => 'publish'
                ));
                
                foreach ($all_venues as $venue) {
                    $venue_pwd = get_field('venue_password', $venue->ID);
                    if ($venue_pwd === $venue_password) {
                        $venue_id = $venue->ID;
                        $venue_name = $venue->post_title;
                        break;
                    }
                }
            }
            
            // If still no match, try meta query
            if (!$venue_id) {
                $venues = get_posts(array(
                    'post_type' => array('eat', 'activity', 'event'),
                    'posts_per_page' => 1,
                    'post_status' => 'publish',
                    'meta_query' => array(
                        array(
                            'key' => 'venue_password',
                            'value' => $venue_password,
                            'compare' => '='
                        )
                    )
                ));
                
                if (!empty($venues)) {
                    $venue = $venues[0];
                    $venue_id = $venue->ID;
                    $venue_name = $venue->post_title;
                }
            }
            
            if ($venue_id && $venue_name) {
                // Check if offer was selected (second step)
                if (isset($_POST['selected_offer']) && !empty($_POST['selected_offer'])) {
                    $selected_offer = sanitize_text_field($_POST['selected_offer']);
                    
                    // Record the scan with selected offer
                    $scan_data = array(
                        'timestamp' => current_time('mysql'),
                        'venue_id' => $venue_id,
                        'venue_name' => $venue_name,
                        'offer_title' => $selected_offer,
                        'latitude' => $latitude,
                        'longitude' => $longitude,
                        'ip' => isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : ''
                    );
                    
                    // Add to user's scan history
                    $scan_history = get_user_meta($user_id, 'membership_card_history', true);
                    if (!is_array($scan_history)) {
                        $scan_history = array();
                    }
                    $scan_history[] = $scan_data;
                    update_user_meta($user_id, 'membership_card_history', $scan_history);
                    
                    // Update total scan count
                    $total_scans = get_user_meta($user_id, 'membership_card_scans', true);
                    $total_scans = $total_scans ? intval($total_scans) + 1 : 1;
                    update_user_meta($user_id, 'membership_card_scans', $total_scans);
                    
                    // Send push notification to venue owner
                    $this->send_redemption_notification($venue_id, $selected_offer, $user_id);
                    
                    $message = "✓ Offer '{$selected_offer}' redeemed successfully at {$venue_name}!";
                } else {
                    // Password verified, but no offer selected yet - check offers and show selection
                    $offers = $this->get_venue_offers($venue_id);
                    
                    if (empty($offers)) {
                        // No offers configured - record scan without offer
                        $scan_data = array(
                            'timestamp' => current_time('mysql'),
                            'venue_id' => $venue_id,
                            'venue_name' => $venue_name,
                            'offer_title' => '',
                            'latitude' => $latitude,
                            'longitude' => $longitude,
                            'ip' => isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : ''
                        );
                        
                        $scan_history = get_user_meta($user_id, 'membership_card_history', true);
                        if (!is_array($scan_history)) {
                            $scan_history = array();
                        }
                        $scan_history[] = $scan_data;
                        update_user_meta($user_id, 'membership_card_history', $scan_history);
                        
                        $total_scans = get_user_meta($user_id, 'membership_card_scans', true);
                        $total_scans = $total_scans ? intval($total_scans) + 1 : 1;
                        update_user_meta($user_id, 'membership_card_scans', $total_scans);
                        
                        $message = "✓ Offer redeemed successfully at {$venue_name}!";
                    } else {
                        // Store venue info in transient for next step (offer selection)
                        set_transient('hunow_verify_venue_' . $user_id, array('venue_id' => $venue_id, 'venue_name' => $venue_name, 'password' => $venue_password), 300);
                    }
                }
            } else {
                $error = "Invalid venue password. Please try again.";
            }
        }
        
        // Get venue data if password was just verified
        $verified_venue_data = null;
        if (isset($_POST['venue_password']) && !$error && !$message) {
            // Check if transient exists (password just verified)
            $temp_venue = get_transient('hunow_verify_venue_' . $user_id);
            if ($temp_venue) {
                $verified_venue_data = $temp_venue;
            }
        }
        
        // Show the verification form or offer selection
        $this->show_verify_form($user, $verify_param, $message, $error, $verified_venue_data);
        exit;
    }
    
    /**
     * Shortcode to display venue offers in Elementor/templates
     * Usage: [hunow_offers post_id="123"]
     */
    public function display_venue_offers_shortcode($atts) {
        $atts = shortcode_atts(array(
            'post_id' => get_the_ID(),
            'show_descriptions' => 'true',
            'template' => 'default' // 'default', 'simple', 'grid'
        ), $atts);
        
        $post_id = intval($atts['post_id']);
        if (!$post_id || !function_exists('get_field')) {
            return '';
        }
        
        // Find venue owner to get subscription tier
        $users = get_users(array('meta_key' => 'assigned_venues'));
        $subscription_tier = 'basic';
        $max_offers = 1;
        
        foreach ($users as $user) {
            $assigned_venues = get_user_meta($user->ID, 'assigned_venues', true);
            if (is_array($assigned_venues) && in_array($post_id, $assigned_venues)) {
                $subscription_tier = get_user_meta($user->ID, 'subscription_tier', true);
                if (empty($subscription_tier)) $subscription_tier = 'basic';
                $max_offers = $this->get_max_offers_by_tier($subscription_tier);
                break;
            }
        }
        
        // Get all offers for this venue
        $offers = array();
        for ($i = 1; $i <= $max_offers; $i++) {
            $title = get_field('offer_title_' . $i, $post_id);
            if (!empty($title)) {
                $description = get_field('offer_description_' . $i, $post_id);
                $offers[] = array(
                    'title' => $title,
                    'description' => $description ? $description : ''
                );
            }
        }
        
        if (empty($offers)) {
            return '';
        }
        
        // Render offers based on template
        ob_start();
        
        if ($atts['template'] === 'simple') {
            // Simple list format
            echo '<ul class="hunow-offers-list">';
            foreach ($offers as $offer) {
                echo '<li class="hunow-offer-item">';
                echo '<strong>' . esc_html($offer['title']) . '</strong>';
                if ($atts['show_descriptions'] === 'true' && !empty($offer['description'])) {
                    echo '<div class="hunow-offer-desc">' . wp_kses_post($offer['description']) . '</div>';
                }
                echo '</li>';
            }
            echo '</ul>';
        } else {
            // Default card format
            echo '<div class="hunow-offers-container" style="display: grid; gap: 20px; margin: 20px 0;">';
            foreach ($offers as $offer) {
                echo '<div class="hunow-offer-card" style="background: #fffef0; padding: 20px; border: 2px solid #fbc903; border-radius: 12px;">';
                echo '<div style="display: flex; align-items: center; gap: 10px; margin-bottom: 10px;">';
                echo '<span style="background: #fbc903; color: #000; padding: 8px 12px; border-radius: 8px; font-weight: bold; font-size: 18px;">%</span>';
                echo '<h3 style="margin: 0; color: #0f0032; font-size: 20px; font-weight: 600;">' . esc_html($offer['title']) . '</h3>';
                echo '</div>';
                if ($atts['show_descriptions'] === 'true' && !empty($offer['description'])) {
                    echo '<div style="color: #666; margin-top: 10px;">' . wp_kses_post($offer['description']) . '</div>';
                }
                echo '</div>';
            }
            echo '</div>';
        }
        
        return ob_get_clean();
    }
    
    /**
     * Get max number of offers allowed based on subscription tier
     */
    private function get_max_offers_by_tier($tier) {
        switch ($tier) {
            case 'basic':
                return 1;
            case 'tier2':
                return 3;
            case 'tier3':
            case 'premium':
                return 999; // Unlimited (high number for practical purposes)
            default:
                return 1;
        }
    }
    
    /**
     * Get all offers for a venue (based on subscription tier)
     */
    private function get_venue_offers($venue_id) {
        $offers = array();
        
        // Find venue owner/manager to get subscription tier
        $users = get_users(array('meta_key' => 'assigned_venues'));
        $subscription_tier = 'basic';
        
        foreach ($users as $user) {
            $assigned_venues = get_user_meta($user->ID, 'assigned_venues', true);
            if (is_array($assigned_venues) && in_array($venue_id, $assigned_venues)) {
                $subscription_tier = get_user_meta($user->ID, 'subscription_tier', true);
                if (empty($subscription_tier)) $subscription_tier = 'basic';
                break;
            }
        }
        
        $max_offers = $this->get_max_offers_by_tier($subscription_tier);
        
        // Get offers (numbered fields: offer_title_1, offer_title_2, etc.)
        // Also check legacy offer_title for backward compatibility
        if (function_exists('get_field')) {
            // First check legacy single offer
            $legacy_offer = get_field('offer_title', $venue_id);
            if (!empty($legacy_offer)) {
                $offers[] = array('title' => $legacy_offer, 'index' => 0);
            }
            
            // Get numbered offers (offer_title_1, offer_title_2, etc.)
            for ($i = 1; $i <= $max_offers; $i++) {
                $offer_title = get_field('offer_title_' . $i, $venue_id);
                if (!empty($offer_title)) {
                    $offers[] = array('title' => $offer_title, 'index' => $i);
                }
            }
        }
        
        // Remove duplicates and reindex
        $unique_offers = array();
        $seen = array();
        foreach ($offers as $offer) {
            if (!in_array($offer['title'], $seen)) {
                $unique_offers[] = $offer;
                $seen[] = $offer['title'];
            }
        }
        
        return $unique_offers;
    }
    
    /**
     * Display verification form
     */
    private function show_verify_form($user, $verify_param, $message = '', $error = '', $venue_data = null) {
        $latitude = isset($_GET['lat']) ? floatval($_GET['lat']) : (isset($_POST['lat']) ? floatval($_POST['lat']) : 0);
        $longitude = isset($_GET['lng']) ? floatval($_GET['lng']) : (isset($_POST['lng']) ? floatval($_POST['lng']) : 0);
        $nonce = wp_create_nonce('hunow_verify_' . $user->ID);
        
        // Use provided venue_data or check transient
        if (!$venue_data) {
            $venue_data = get_transient('hunow_verify_venue_' . $user->ID);
        }
        
        ?>
        <!DOCTYPE html>
        <html <?php language_attributes(); ?>>
        <head>
            <meta charset="<?php bloginfo('charset'); ?>">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <title>HU NOW - Verify Membership</title>
            <style>
                * { margin: 0; padding: 0; box-sizing: border-box; }
                body {
                    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, sans-serif;
                    background: linear-gradient(135deg, #0f0032 0%, #1a0047 100%);
                    min-height: 100vh;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    padding: 20px;
                }
                .verify-container {
                    background: white;
                    border-radius: 16px;
                    padding: 40px;
                    max-width: 500px;
                    width: 100%;
                    box-shadow: 0 20px 60px rgba(0,0,0,0.3);
                }
                .verify-header {
                    text-align: center;
                    margin-bottom: 30px;
                }
                .verify-header h1 {
                    color: #0f0032;
                    font-size: 28px;
                    margin-bottom: 10px;
                }
                .verify-header .member-info {
                    color: #666;
                    font-size: 14px;
                }
                .form-group {
                    margin-bottom: 20px;
                }
                .form-group label {
                    display: block;
                    color: #333;
                    font-weight: 600;
                    margin-bottom: 8px;
                    font-size: 14px;
                }
                .form-group input[type="password"] {
                    width: 100%;
                    padding: 12px 16px;
                    border: 2px solid #e5e7eb;
                    border-radius: 8px;
                    font-size: 16px;
                    transition: border-color 0.3s;
                }
                .form-group input[type="password"]:focus {
                    outline: none;
                    border-color: #fbc903;
                }
                .submit-btn {
                    width: 100%;
                    background: #0f0032;
                    color: white;
                    border: none;
                    padding: 14px;
                    border-radius: 8px;
                    font-size: 16px;
                    font-weight: 600;
                    cursor: pointer;
                    transition: background 0.3s;
                }
                .submit-btn:hover {
                    background: #1a0047;
                }
                .message {
                    padding: 12px 16px;
                    border-radius: 8px;
                    margin-bottom: 20px;
                    text-align: center;
                    font-weight: 500;
                }
                .message.success {
                    background: #d1fae5;
                    color: #065f46;
                    border: 1px solid #10b981;
                }
                .message.error {
                    background: #fee2e2;
                    color: #991b1b;
                    border: 1px solid #ef4444;
                }
            </style>
        </head>
        <body>
            <div class="verify-container">
                <div class="verify-header">
                    <h1>HU NOW</h1>
                    <div class="member-info">
                        Member: <?php echo esc_html($user->display_name); ?><br>
                        Email: <?php echo esc_html($user->user_email); ?>
                    </div>
                </div>
                
                <?php if ($message): ?>
                    <div class="message success"><?php echo esc_html($message); ?></div>
                <?php endif; ?>
                
                <?php if ($error): ?>
                    <div class="message error"><?php echo esc_html($error); ?></div>
                <?php endif; ?>
                
                <?php
                // Check if venue was just verified (show offer selection)
                // Use passed venue_data parameter or fall back to transient
                if (!$venue_data) {
                    $venue_data = get_transient('hunow_verify_venue_' . $user->ID);
                }
                
                $offers = array();
                $venue_id = null;
                
                if ($venue_data && isset($venue_data['venue_id'])) {
                    $venue_id = $venue_data['venue_id'];
                    $offers = $this->get_venue_offers($venue_id);
                }
                
                if (!$message && $venue_id && !empty($offers)): 
                    // Show offer selection
                ?>
                    <div style="margin-bottom: 20px;">
                        <p style="color: #0f0032; font-weight: 600; margin-bottom: 15px;">✓ Password verified! Select the offer being redeemed:</p>
                    </div>
                    
                    <form method="post" action="">
                        <?php wp_nonce_field('hunow_verify_' . $user->ID, 'verify_nonce'); ?>
                        <input type="hidden" name="venue_password" value="<?php echo esc_attr($venue_data['password']); ?>">
                        <input type="hidden" name="lat" value="<?php echo esc_attr($latitude); ?>">
                        <input type="hidden" name="lng" value="<?php echo esc_attr($longitude); ?>">
                        
                        <div class="form-group">
                            <label for="selected_offer" style="margin-bottom: 12px; font-size: 15px;">Available Offers</label>
                            <div style="display: grid; gap: 10px;">
                                <?php foreach ($offers as $offer): ?>
                                    <label style="display: flex; align-items: center; padding: 12px; border: 2px solid #e5e7eb; border-radius: 8px; cursor: pointer; transition: all 0.3s;" 
                                           onmouseover="this.style.borderColor='#fbc903'; this.style.backgroundColor='#fffef0';" 
                                           onmouseout="this.style.borderColor='#e5e7eb'; this.style.backgroundColor='transparent';">
                                        <input type="radio" name="selected_offer" value="<?php echo esc_attr($offer['title']); ?>" required style="margin-right: 10px;">
                                        <span style="font-weight: 500; color: #0f0032;"><?php echo esc_html($offer['title']); ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        
                        <button type="submit" class="submit-btn" style="margin-top: 10px;">Confirm Redemption</button>
                    </form>
                    
                <?php elseif (!$message): 
                    // Show password form
                ?>
                    <form method="post" action="">
                        <?php wp_nonce_field('hunow_verify_' . $user->ID, 'verify_nonce'); ?>
                        <input type="hidden" name="lat" value="<?php echo esc_attr($latitude); ?>">
                        <input type="hidden" name="lng" value="<?php echo esc_attr($longitude); ?>">
                        
                        <div class="form-group">
                            <label for="venue_password">Enter Venue Password</label>
                            <input 
                                type="password" 
                                id="venue_password" 
                                name="venue_password" 
                                required 
                                autofocus
                                placeholder="Enter your venue password"
                            >
                        </div>
                        
                        <button type="submit" class="submit-btn">Redeem Offer</button>
                    </form>
                <?php else: ?>
                    <div style="text-align: center; padding: 20px 0;">
                        <p style="color: #666; margin-bottom: 15px;">Redemption complete. You can close this page.</p>
                    </div>
                <?php endif; ?>
            </div>
        </body>
        </html>
        <?php
    }
    
    /**
     * Show error page
     */
    private function show_verify_error($message) {
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="utf-8">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <title>HU NOW - Error</title>
            <style>
                body {
                    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
                    background: linear-gradient(135deg, #0f0032 0%, #1a0047 100%);
                    min-height: 100vh;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    padding: 20px;
                    color: white;
                    text-align: center;
                }
                .error-container {
                    background: rgba(255,255,255,0.1);
                    padding: 40px;
                    border-radius: 16px;
                }
                h1 { margin-bottom: 15px; }
            </style>
        </head>
        <body>
            <div class="error-container">
                <h1>HU NOW</h1>
                <p><?php echo esc_html($message); ?></p>
            </div>
        </body>
        </html>
        <?php
    }
    
    // ============================================================================
    // PUSH NOTIFICATIONS
    // ============================================================================
    
    /**
     * Register push token for user
     * Endpoint: POST /wp-json/hunow/v1/push/register
     */
    public function register_push_token($request) {
        $user_id = $request->get_param('user_id');
        $token = $request->get_param('token');
        
        // Get JWT token from headers
        $headers = $request->get_headers();
        $auth_header = isset($headers['authorization']) ? $headers['authorization'] : array();
        $auth_header = is_array($auth_header) ? $auth_header[0] : $auth_header;
        $jwt_token = $auth_header ? str_replace('Bearer ', '', $auth_header) : '';
        
        // Try to get user ID from JWT token if not provided
        if (!$user_id && $jwt_token) {
            // Decode JWT payload to get user_id
            $parts = explode('.', $jwt_token);
            if (count($parts) === 3) {
                $payload = json_decode(base64_decode(str_replace(array('_', '-'), array('/', '+'), $parts[1])), true);
                if (isset($payload['data']['user']['id'])) {
                    $user_id = intval($payload['data']['user']['id']);
                }
            }
        }
        
        // Fallback to current user if available
        if (!$user_id && is_user_logged_in()) {
            $user_id = get_current_user_id();
        }
        
        if (!$user_id || !$token) {
            return new WP_Error('missing_params', 'User ID and token are required', array('status' => 400));
        }
        
        // Verify user exists
        $user = get_user_by('id', $user_id);
        if (!$user) {
            return new WP_Error('user_not_found', 'User not found', array('status' => 404));
        }
        
        // If we have JWT token, verify the user_id matches (basic security check)
        // For admin users, skip this check
        if ($jwt_token && !current_user_can('manage_options')) {
            $jwt_user_id = null;
            $parts = explode('.', $jwt_token);
            if (count($parts) === 3) {
                $payload = json_decode(base64_decode(str_replace(array('_', '-'), array('/', '+'), $parts[1])), true);
                if (isset($payload['data']['user']['id'])) {
                    $jwt_user_id = intval($payload['data']['user']['id']);
                }
            }
            
            // Verify user_id from request matches JWT user_id
            if ($jwt_user_id && $jwt_user_id != $user_id) {
                return new WP_Error('unauthorized', 'You can only register your own push token', array('status' => 403));
            }
        }
        
        // Store in user meta
        update_user_meta($user_id, 'expo_push_token', $token);
        
        return new WP_REST_Response(array(
            'success' => true,
            'message' => 'Push token registered',
            'user_id' => $user_id
        ), 200);
    }
    
    /**
     * Send push notification via API
     * Endpoint: POST /wp-json/hunow/v1/push/send
     */
    public function send_push_notification_api($request) {
        $user_id = $request->get_param('user_id');
        $title = $request->get_param('title');
        $body = $request->get_param('body');
        $data = $request->get_param('data');
        
        if (!$user_id || !$title || !$body) {
            return new WP_Error('missing_params', 'user_id, title, and body are required', array('status' => 400));
        }
        
        // Get user's push token
        $token = get_user_meta($user_id, 'expo_push_token', true);
        
        if (!$token) {
            return new WP_Error('no_token', 'User has no push token registered', array('status' => 404));
        }
        
        // Send notification
        $result = $this->send_expo_push(
            $token,
            $title,
            $body,
            $data ?: array()
        );
        
        if (is_array($result) && isset($result['success']) && $result['success']) {
            return new WP_REST_Response(array(
                'success' => true,
                'message' => 'Notification sent'
            ), 200);
        } else {
            return new WP_Error('send_failed', 'Failed to send notification', array('status' => 500));
        }
    }
    
    /**
     * Send push notification via Expo Push API
     */
    private function send_expo_push($to, $title, $body, $data = array()) {
        // Expo API expects data to be an object, not an array
        $data_obj = is_array($data) ? (object)$data : $data;
        
        // Expo API expects an array of messages, even for a single notification
        $messages = array(array(
            'to' => $to,
            'sound' => 'default',
            'title' => $title,
            'body' => $body,
            'data' => $data_obj,
            'badge' => 1,
            'channelId' => 'default'
        ));
        
        error_log('HU NOW Push: Sending notification to token: ' . substr($to, 0, 30) . '...');
        error_log('HU NOW Push: Title: ' . $title . ', Body: ' . $body);
        error_log('HU NOW Push: Payload: ' . json_encode($messages));
        
        $response = wp_remote_post('https://exp.host/--/api/v2/push/send', array(
            'headers' => array(
                'Content-Type' => 'application/json',
                'Accept' => 'application/json'
            ),
            'body' => json_encode($messages),
            'timeout' => 10
        ));
        
        if (is_wp_error($response)) {
            $error_msg = 'Push notification failed: ' . $response->get_error_message();
            error_log('HU NOW Push ERROR: ' . $error_msg);
            return array('success' => false, 'error' => $error_msg);
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        $body_data = json_decode($response_body, true);
        
        error_log('HU NOW Push: Response code: ' . $response_code);
        error_log('HU NOW Push: Response body: ' . $response_body);
        
        // Check for validation errors in response
        if (isset($body_data['errors']) && is_array($body_data['errors'])) {
            $error_msg = 'Expo API validation error: ' . ($body_data['errors'][0]['message'] ?? 'Unknown error');
            error_log('HU NOW Push ERROR: ' . $error_msg);
            return array('success' => false, 'error' => $error_msg, 'details' => $body_data['errors'][0]);
        }
        
        // Check if Expo returned success/error status
        if (isset($body_data['data']) && is_array($body_data['data']) && isset($body_data['data'][0]['status'])) {
            if ($body_data['data'][0]['status'] === 'error') {
                $error_msg = 'Expo API error: ' . ($body_data['data'][0]['message'] ?? 'Unknown error');
                error_log('HU NOW Push ERROR: ' . $error_msg);
                return array('success' => false, 'error' => $error_msg, 'details' => $body_data['data'][0]);
            } elseif ($body_data['data'][0]['status'] === 'ok') {
                error_log('HU NOW Push: ✅ Notification sent successfully!');
                return array('success' => true, 'id' => $body_data['data'][0]['id'] ?? null);
            }
        }
        
        // If we get here, response format is unexpected
        error_log('HU NOW Push WARNING: Unexpected response format: ' . print_r($body_data, true));
        return array('success' => false, 'error' => 'Unexpected response from Expo API', 'response' => $body_data);
    }
    
    /**
     * Send push notification to multiple users
     */
    private function send_expo_push_bulk($tokens, $title, $body, $data = array()) {
        if (empty($tokens)) {
            error_log('HU NOW Push ERROR: No tokens provided for bulk send');
            return array('success' => false, 'error' => 'No tokens provided');
        }
        
        // Expo API expects data to be an object, not an array
        $data_obj = is_array($data) ? (object)$data : $data;
        
        $messages = array();
        foreach ($tokens as $token) {
            $messages[] = array(
                'to' => $token,
                'sound' => 'default',
                'title' => $title,
                'body' => $body,
                'data' => $data_obj,
                'badge' => 1,
                'channelId' => 'default'
            );
        }
        
        error_log('HU NOW Push: Sending bulk notification to ' . count($tokens) . ' tokens');
        error_log('HU NOW Push: Title: ' . $title . ', Body: ' . $body);
        
        $response = wp_remote_post('https://exp.host/--/api/v2/push/send', array(
            'headers' => array(
                'Content-Type' => 'application/json',
                'Accept' => 'application/json'
            ),
            'body' => json_encode($messages),
            'timeout' => 15
        ));
        
        if (is_wp_error($response)) {
            $error_msg = 'Bulk push notification failed: ' . $response->get_error_message();
            error_log('HU NOW Push ERROR: ' . $error_msg);
            return array('success' => false, 'error' => $error_msg);
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        $body_data = json_decode($response_body, true);
        
        error_log('HU NOW Push: Bulk response code: ' . $response_code);
        error_log('HU NOW Push: Bulk response body: ' . $response_body);
        
        // Check for validation errors in response
        if (isset($body_data['errors']) && is_array($body_data['errors'])) {
            $error_msg = 'Expo API validation error: ' . ($body_data['errors'][0]['message'] ?? 'Unknown error');
            error_log('HU NOW Push ERROR (bulk): ' . $error_msg);
            return array('success' => false, 'error' => $error_msg, 'details' => $body_data['errors'][0]);
        }
        
        // Check Expo response
        if (isset($body_data['data']) && is_array($body_data['data'])) {
            $success_count = 0;
            $error_count = 0;
            
            foreach ($body_data['data'] as $result) {
                if (isset($result['status'])) {
                    if ($result['status'] === 'ok') {
                        $success_count++;
                    } elseif ($result['status'] === 'error') {
                        $error_count++;
                        error_log('HU NOW Push ERROR (bulk): ' . ($result['message'] ?? 'Unknown error') . ' for token: ' . substr($result['to'] ?? 'unknown', 0, 30));
                    }
                }
            }
            
            error_log('HU NOW Push: Bulk send complete - Success: ' . $success_count . ', Errors: ' . $error_count);
            
            if ($error_count === 0) {
                return array('success' => true, 'sent' => $success_count, 'errors' => 0);
            } elseif ($success_count > 0) {
                return array('success' => true, 'sent' => $success_count, 'errors' => $error_count, 'partial' => true);
            } else {
                return array('success' => false, 'error' => 'All notifications failed', 'errors' => $error_count);
            }
        }
        
        error_log('HU NOW Push WARNING: Unexpected bulk response format: ' . print_r($body_data, true));
        return array('success' => false, 'error' => 'Unexpected response from Expo API', 'response' => $body_data);
    }
    
    /**
     * Add push notification admin menu
     */
    public function add_push_notification_menu() {
        add_submenu_page(
            'tools.php',
            'Send Push Notification',
            '📱 Send Push',
            'manage_options',
            'send-push',
            array($this, 'display_push_notification_page')
        );
    }
    
    /**
     * Display push notification admin page
     */
    public function display_push_notification_page() {
        // Handle form submission
        if (isset($_POST['send_push']) && check_admin_referer('send_push_nonce')) {
            $title = sanitize_text_field($_POST['title']);
            $body = sanitize_textarea_field($_POST['body']);
            
            // Check if sending to all users or selected users
            $send_to_all = isset($_POST['send_to_all']) && $_POST['send_to_all'] === '1';
            
            if ($send_to_all) {
                // Send to all users with push tokens
                $users = get_users(array(
                    'meta_key' => 'expo_push_token',
                    'meta_compare' => 'EXISTS'
                ));
                
                $tokens = array();
                foreach ($users as $user) {
                    $token = get_user_meta($user->ID, 'expo_push_token', true);
                    if ($token) {
                        $tokens[] = $token;
                    }
                }
                
                if (!empty($tokens)) {
                    $result = $this->send_expo_push_bulk($tokens, $title, $body);
                    if (is_array($result) && isset($result['success'])) {
                        if ($result['success']) {
                            $message = '✅ Notification sent successfully!';
                            if (isset($result['sent'])) {
                                $message .= ' Sent to <strong>' . $result['sent'] . '</strong> users';
                                if (isset($result['errors']) && $result['errors'] > 0) {
                                    $message .= ' (<strong>' . $result['errors'] . '</strong> failed)';
                                }
                            }
                            $message .= '.';
                            echo '<div class="notice notice-success"><p>' . $message . '</p></div>';
                        } else {
                            $error_msg = $result['error'] ?? 'Unknown error';
                            echo '<div class="notice notice-error"><p>❌ Failed to send notification: <strong>' . esc_html($error_msg) . '</strong></p>';
                            echo '<p><small>Check WordPress error logs for more details.</small></p></div>';
                        }
                    } elseif ($result) {
                        echo '<div class="notice notice-success"><p>✅ Notification sent to <strong>' . count($tokens) . '</strong> users!</p></div>';
                    } else {
                        echo '<div class="notice notice-error"><p>❌ Failed to send notification. Check error logs.</p></div>';
                    }
                } else {
                    echo '<div class="notice notice-error"><p>❌ No users with push tokens found.</p></div>';
                }
            } else {
                // Send to selected users
                $user_ids = isset($_POST['user_ids']) ? array_map('intval', $_POST['user_ids']) : array();
                
                if (empty($user_ids)) {
                    echo '<div class="notice notice-error"><p>❌ Please select at least one user.</p></div>';
                } else {
                    $tokens = array();
                    
                    foreach ($user_ids as $user_id) {
                        $token = get_user_meta($user_id, 'expo_push_token', true);
                        if ($token) {
                            $tokens[] = $token;
                        }
                    }
                    
                    if (!empty($tokens)) {
                        if (count($tokens) === 1) {
                            // Single user - use regular function
                            $result = $this->send_expo_push($tokens[0], $title, $body);
                        } else {
                            // Multiple users - use bulk function
                            $result = $this->send_expo_push_bulk($tokens, $title, $body);
                        }
                        
                        if (is_array($result) && isset($result['success'])) {
                            if ($result['success']) {
                                $message = '✅ Notification sent successfully!';
                                if (isset($result['sent'])) {
                                    $message .= ' Sent to <strong>' . $result['sent'] . '</strong> user(s)';
                                    if (isset($result['errors']) && $result['errors'] > 0) {
                                        $message .= ' (<strong>' . $result['errors'] . '</strong> failed)';
                                    }
                                }
                                $message .= '.';
                                echo '<div class="notice notice-success"><p>' . $message . '</p></div>';
                            } else {
                                $error_msg = $result['error'] ?? 'Unknown error';
                                echo '<div class="notice notice-error"><p>❌ Failed to send notification: <strong>' . esc_html($error_msg) . '</strong></p>';
                                if (isset($result['details'])) {
                                    echo '<p><small>Details: ' . esc_html(print_r($result['details'], true)) . '</small></p>';
                                }
                                echo '<p><small>Check WordPress error logs for more details.</small></p></div>';
                            }
                        } elseif ($result) {
                            // Backwards compatibility
                            echo '<div class="notice notice-success"><p>✅ Notification sent to <strong>' . count($tokens) . '</strong> user(s)!</p></div>';
                        } else {
                            echo '<div class="notice notice-error"><p>❌ Failed to send notification. Check error logs.</p></div>';
                        }
                    } else {
                        echo '<div class="notice notice-error"><p>❌ Selected users have no push tokens.</p></div>';
                    }
                }
            }
        }
        
        // Get all users with push tokens
        $users = get_users(array(
            'meta_key' => 'expo_push_token',
            'meta_compare' => 'EXISTS'
        ));
        
        ?>
        <div class="wrap">
            <h1>📱 Send Push Notification</h1>
            <p>Send push notifications to users who have the mobile app installed and have enabled notifications.</p>
            
            <form method="post" style="max-width: 800px;">
                <?php wp_nonce_field('send_push_nonce'); ?>
                <table class="form-table">
                    <tr>
                        <th><label>Send To</label></th>
                        <td>
                            <label style="display: block; margin-bottom: 10px;">
                                <input type="radio" name="send_to_all" value="1" onclick="document.getElementById('user-select').style.display='none';">
                                <strong>All Users</strong> (<?php echo count($users); ?> users)
                            </label>
                            <label style="display: block;">
                                <input type="radio" name="send_to_all" value="0" checked onclick="document.getElementById('user-select').style.display='table-row';">
                                <strong>Selected Users</strong>
                            </label>
                        </td>
                    </tr>
                    <tr id="user-select">
                        <th><label>Select Users</label></th>
                        <td>
                            <?php if (empty($users)): ?>
                                <p class="description">No users with push tokens found. Users need to login to the app first.</p>
                            <?php else: ?>
                                <div style="max-height: 200px; overflow-y: auto; border: 1px solid #ddd; padding: 10px; background: #fff; border-radius: 4px;">
                                    <label style="display: block; margin-bottom: 5px; font-weight: bold;">
                                        <input type="checkbox" onclick="hunowToggleAll(this);"> Select All
                                    </label>
                                    <hr style="margin: 5px 0;">
                                    <?php foreach ($users as $user) {
                                        echo '<label style="display: block; padding: 5px 0; border-bottom: 1px solid #eee;">';
                                        echo '<input type="checkbox" name="user_ids[]" value="' . $user->ID . '" class="hunow-user-checkbox"> ';
                                        echo esc_html($user->display_name) . ' (' . $user->user_email . ')';
                                        echo '</label>';
                                    } ?>
                                </div>
                                <p class="description">Select one or more users to send notifications to.</p>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th><label>Title</label></th>
                        <td>
                            <input type="text" name="title" class="regular-text" required 
                                   placeholder="e.g., New Event!" maxlength="100" />
                        </td>
                    </tr>
                    <tr>
                        <th><label>Message</label></th>
                        <td>
                            <textarea name="body" rows="5" class="large-text" required 
                                      placeholder="e.g., Check out this new event"></textarea>
                            <p class="description">Keep it short and engaging!</p>
                        </td>
                    </tr>
                </table>
                <?php submit_button('Send Notification', 'primary', 'send_push'); ?>
            </form>
            
            <hr style="margin: 30px 0;">
            
            <h2>Statistics</h2>
            <p><strong><?php echo count($users); ?></strong> users have push tokens registered.</p>
        </div>
        
        <script>
        function hunowToggleAll(source) {
            var checkboxes = document.getElementsByClassName('hunow-user-checkbox');
            for (var i = 0; i < checkboxes.length; i++) {
                checkboxes[i].checked = source.checked;
            }
        }
        </script>
        <?php
    }
    
    /**
     * Send notification when a new event is published
     */
    public function send_new_event_notification($post_id, $post) {
        // Only for newly published posts (not updates)
        if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) {
            return;
        }
        
        // Get all users with push tokens
        $users = get_users(array(
            'meta_key' => 'expo_push_token',
            'meta_compare' => 'EXISTS'
        ));
        
        $tokens = array();
        foreach ($users as $user) {
            $token = get_user_meta($user->ID, 'expo_push_token', true);
            if ($token) {
                $tokens[] = $token;
            }
        }
        
        if (!empty($tokens)) {
            $this->send_expo_push_bulk(
                $tokens,
                '🎉 New Event!',
                $post->post_title . ' is now live',
                array(
                    'screen' => 'Detail',
                    'id' => $post_id,
                    'type' => 'event'
                )
            );
        }
    }
    
    /**
     * Send notification when someone rates a listing
     * Hooked via action: hunow_rating_submitted
     */
    public function send_rating_notification($post_id, $rating, $user_id) {
        // Get the listing owner
        $post = get_post($post_id);
        $owner_id = $post->post_author;
        
        // Don't notify yourself
        if ($owner_id == $user_id) {
            return;
        }
        
        // Get owner's push token
        $token = get_user_meta($owner_id, 'expo_push_token', true);
        
        if ($token) {
            $rater = get_userdata($user_id);
            $rater_name = $rater->display_name ? $rater->display_name : 'Someone';
            
            $this->send_expo_push(
                $token,
                '⭐ New Rating!',
                $rater_name . ' rated your listing ' . $rating . ' stars',
                array(
                    'screen' => 'Detail',
                    'id' => $post_id,
                    'type' => get_post_type($post_id)
                )
            );
        }
    }
    
    /**
     * Send notification when a QR code is redeemed
     * Call this from handle_verify_page after successful redemption
     */
    public function send_redemption_notification($venue_id, $offer_title, $user_id) {
        // Get venue owner (assuming venue managers are authors)
        $venue = get_post($venue_id);
        if (!$venue) {
            return;
        }
        
        $owner_id = $venue->post_author;
        
        // Get owner's push token
        $token = get_user_meta($owner_id, 'expo_push_token', true);
        
        if ($token) {
            $redeemer = get_userdata($user_id);
            $redeemer_name = $redeemer->display_name ? $redeemer->display_name : 'A customer';
            
            $this->send_expo_push(
                $token,
                '🎫 Offer Redeemed!',
                $redeemer_name . ' just redeemed: ' . $offer_title,
                array(
                    'screen' => 'VenuePortal',
                    'id' => $venue_id,
                    'type' => 'redemption'
                )
            );
        }
    }
}

// Register activation hook (must be outside class)
register_activation_hook(__FILE__, 'hunow_create_venue_manager_role');

function hunow_create_venue_manager_role() {
    add_role(
        'venue_manager',
        'Venue Manager',
        array(
            'read' => true,
            'upload_files' => true,
            'edit_posts' => true,
            'delete_posts' => false,
            'publish_posts' => false,
            'edit_published_posts' => true,
        )
    );
}

// Initialize plugin
new HUNOW_Statistics();

