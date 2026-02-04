<?php
/**
 * Plugin Name: HU NOW Auto Linker
 * Description: Automatically add internal links based on keywords. Includes settings page for customization.
 * Version: 1.0.0
 * Author: HU NOW
 */

if (!defined('ABSPATH')) exit;

class HU_Now_Auto_Linker {
    private $option_name = 'hunow_auto_linker_options';

    function __construct() {
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_init', [$this, 'register_settings']);
        add_filter('the_content', [$this, 'process_content'], 20);
        add_filter('the_excerpt', [$this, 'process_content'], 20);
        
        // Catch final output for Elementor pages
        add_action('wp_footer', [$this, 'start_output_buffer'], 1);
        add_action('wp_footer', [$this, 'end_output_buffer'], 999);
        
        // Alternative: Process all page output
        add_action('template_redirect', [$this, 'start_page_buffer']);
        
        // Fallback: Process all pages regardless of type
        add_action('wp_head', [$this, 'start_universal_buffer'], 1);
        
        // Direct content search approach
        add_action('wp_footer', [$this, 'search_and_replace_content'], 999);
        
        // Elementor support - safe hooks only
        add_action('elementor/widget/before_render_content', [$this, 'process_elementor_content'], 10, 1);
        add_filter('elementor/frontend/builder_content_data', [$this, 'process_elementor_data'], 10, 2);
        
        add_action('wp_ajax_hunow_test_keywords', [$this, 'ajax_test_keywords']);
    }

    function add_admin_menu() {
        add_options_page(
            'HU NOW Auto Linker',
            'HU NOW Auto Linker',
            'manage_options',
            'hunow-auto-linker',
            [$this, 'settings_page']
        );
    }

    function register_settings() {
        register_setting($this->option_name, $this->option_name, [
            'sanitize_callback' => [$this, 'sanitize_options']
        ]);
    }

    /**
     * Sanitize and validate plugin options
     */
    function sanitize_options($input) {
        $sanitized = [];
        
        // Sanitize max_links
        $sanitized['max_links'] = max(1, min(20, intval($input['max_links'] ?? 5)));
        
        // Sanitize blacklist
        $blacklist = sanitize_text_field($input['blacklist'] ?? '');
        $sanitized['blacklist'] = preg_replace('/[^0-9,\s]/', '', $blacklist);
        
        // Sanitize keywords - handle both string and array inputs
        $keywords_raw = '';
        if (isset($input['keywords'])) {
            if (is_array($input['keywords'])) {
                $keywords_raw = implode("\n", $input['keywords']);
            } else {
                $keywords_raw = $input['keywords'];
            }
        }
        $sanitized['keywords'] = [];
        
        if (!empty($keywords_raw)) {
            $lines = explode("\n", $keywords_raw);
            foreach ($lines as $line) {
                $line = trim($line);
                if (empty($line)) continue;
                
                $parts = explode('|', $line);
                if (count($parts) < 2) continue;
                
                $keyword = trim($parts[0]);
                $url = trim($parts[1]);
                
                if (empty($keyword) || empty($url)) continue;
                
                // Validate URL
                if (!filter_var($url, FILTER_VALIDATE_URL) && !preg_match('/^\/[^\/]/', $url)) {
                    continue; // Skip invalid URLs
                }
                
                // Sanitize keyword (remove HTML tags)
                $keyword = sanitize_text_field($keyword);
                if (empty($keyword)) continue;
                
                // Sanitize URL
                $url = esc_url_raw($url);
                if (empty($url)) continue;
                
                // Sanitize anchor text if provided
                $anchor_text = '';
                if (isset($parts[2])) {
                    $anchors = explode(',', $parts[2]);
                    $sanitized_anchors = [];
                    foreach ($anchors as $anchor) {
                        $anchor = trim($anchor);
                        if (!empty($anchor)) {
                            $sanitized_anchors[] = sanitize_text_field($anchor);
                        }
                    }
                    $anchor_text = implode(',', $sanitized_anchors);
                }
                
                // Build sanitized line
                $sanitized_line = $keyword . '|' . $url;
                if (!empty($anchor_text)) {
                    $sanitized_line .= '|' . $anchor_text;
                }
                
                $sanitized['keywords'][] = $sanitized_line;
            }
        }
        
        return $sanitized;
    }

    function settings_page() {
        $opts = get_option($this->option_name, [
            'max_links' => 5,
            'keywords' => [],
            'blacklist' => ''
        ]);
        ?>
        <div class="wrap">
            <h1>HU NOW Auto Linker</h1>
            <div class="notice notice-info">
                <p><strong>How it works:</strong> This plugin automatically converts keywords in your content into links. Internal links will open in the same tab, external links will open in a new tab.</p>
            </div>
            
            <form method="post" action="options.php">
                <?php settings_fields($this->option_name); ?>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row">Max links per page</th>
                        <td>
                            <input type="number" name="<?php echo $this->option_name; ?>[max_links]" value="<?php echo esc_attr($opts['max_links']); ?>" min="1" max="20">
                            <p class="description">Maximum number of auto-links to add per page (1-20)</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Blacklist (post IDs)</th>
                        <td>
                            <input type="text" name="<?php echo $this->option_name; ?>[blacklist]" value="<?php echo esc_attr($opts['blacklist']); ?>" size="50" placeholder="123,456,789">
                            <p class="description">Comma-separated list of post/page IDs where auto-linking should be disabled</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Keywords Configuration</th>
                        <td>
                            <p><strong>Format:</strong> <code>keyword|url|anchor_text</code></p>
                            <p class="description">
                                • <strong>keyword:</strong> The word/phrase to auto-link (case-insensitive)<br>
                                • <strong>url:</strong> Destination URL (use <code>/page-slug/</code> for internal pages)<br>
                                • <strong>anchor_text:</strong> Optional custom link text (if omitted, uses keyword)<br>
                                • Use commas to separate multiple anchor text options: <code>anchor1,anchor2</code>
                            </p>
                            
                            <h4>Examples:</h4>
                            <div style="background: #f9f9f9; padding: 10px; margin: 10px 0; border-left: 4px solid #0073aa;">
                                <code>WordPress|/wordpress-guide/|WP Guide,WordPress Tutorial</code><br>
                                <code>SEO|/seo-tips/</code><br>
                                <code>contact|/contact-us/|Contact Us,Get in Touch</code><br>
                                <code>https://example.com|https://example.com|External Link</code>
                            </div>
                            
                            <textarea name="<?php echo $this->option_name; ?>[keywords]" rows="12" cols="80" placeholder="Enter your keywords here, one per line..."><?php
                                if (!empty($opts['keywords']) && is_array($opts['keywords'])) {
                                    foreach ($opts['keywords'] as $line) {
                                        echo esc_html($line) . "\n";
                                    }
                                }
                            ?></textarea>
                            <p class="description">
                                <strong>Tips:</strong><br>
                                • One keyword per line<br>
                                • Internal links (same domain) will open in the same tab<br>
                                • External links will open in a new tab with nofollow<br>
                                • Only the first occurrence of each keyword per page will be linked<br>
                                • Keywords are case-insensitive
                            </p>
                            
                            <h4>Test Your Keywords:</h4>
                            <p>Test how your keywords will work with sample content:</p>
                            <textarea id="test-content" rows="4" cols="80" placeholder="Enter sample content to test your keywords...">This is a sample WordPress article about SEO and marketing strategies. Contact us for more information.</textarea><br><br>
                            <button type="button" id="test-keywords" class="button">Test Keywords</button>
                            <div id="test-results" style="margin-top: 10px; padding: 10px; background: #f9f9f9; border: 1px solid #ddd; display: none;"></div>
                        </td>
                    </tr>
                </table>
                <?php submit_button('Save Settings'); ?>
            </form>
            
            <div class="notice notice-warning">
                <p><strong>Note:</strong> Changes will take effect immediately on your published content. Test with a few keywords first to ensure they work as expected.</p>
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            $('#test-keywords').on('click', function() {
                var testContent = $('#test-content').val();
                var keywords = $('textarea[name="<?php echo $this->option_name; ?>[keywords]"]').val();
                
                if (!testContent.trim()) {
                    alert('Please enter some test content first.');
                    return;
                }
                
                if (!keywords.trim()) {
                    alert('Please enter some keywords first.');
                    return;
                }
                
                $('#test-keywords').prop('disabled', true).text('Testing...');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'hunow_test_keywords',
                        content: testContent,
                        keywords: keywords,
                        nonce: '<?php echo wp_create_nonce('hunow_test'); ?>'
                    },
                    success: function(response) {
                        $('#test-results').html(response.data).show();
                    },
                    error: function() {
                        $('#test-results').html('<p style="color: red;">Error testing keywords. Please try again.</p>').show();
                    },
                    complete: function() {
                        $('#test-keywords').prop('disabled', false).text('Test Keywords');
                    }
                });
            });
        });
        </script>
        <?php
    }

    /**
     * AJAX handler for testing keywords
     */
    function ajax_test_keywords() {
        check_ajax_referer('hunow_test', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        $test_content = sanitize_textarea_field($_POST['content'] ?? '');
        $keywords_raw = sanitize_textarea_field($_POST['keywords'] ?? '');
        
        if (empty($test_content) || empty($keywords_raw)) {
            wp_send_json_error('Missing test content or keywords');
        }
        
        // Parse keywords
        $keywords = [];
        $lines = explode("\n", $keywords_raw);
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) continue;
            
            $parts = explode('|', $line);
            if (count($parts) < 2) continue;
            
            $keyword = trim($parts[0]);
            $url = trim($parts[1]);
            
            if (empty($keyword) || empty($url)) continue;
            
            $anchors = isset($parts[2]) ? explode(',', $parts[2]) : [$keyword];
            $anchor = trim($anchors[array_rand($anchors)]);
            
            $keywords[] = [
                'keyword' => $keyword,
                'url' => $url,
                'anchor' => $anchor
            ];
        }
        
        // Process test content
        $processed_content = $test_content;
        $linked_count = 0;
        $max_links = 5;
        
        foreach ($keywords as $keyword_data) {
            if ($linked_count >= $max_links) break;
            
            $keyword = $keyword_data['keyword'];
            $url = $keyword_data['url'];
            $anchor = $keyword_data['anchor'];
            
            $is_internal = $this->is_internal_url($url);
            
            $link_attrs = 'href="' . esc_url($url) . '"';
            if (!$is_internal) {
                $link_attrs .= ' target="_blank" rel="nofollow noopener"';
            } else {
                $link_attrs .= ' rel="follow"';
            }
            
            $escaped_keyword = preg_quote($keyword, '/');
            
            // For phrases with spaces, don't use word boundaries
            if (strpos($keyword, ' ') !== false) {
                // Multi-word phrase: match exact phrase
                $regex = '/(' . $escaped_keyword . ')/i';
            } else {
                // Single word: use word boundaries
                $regex = '/\b(' . $escaped_keyword . ')\b/i';
            }
            $replace = '<a ' . $link_attrs . '>' . esc_html($anchor) . '</a>';
            
            $new_content = preg_replace($regex, $replace, $processed_content, 1);
            if ($new_content !== null && $new_content !== $processed_content) {
                $processed_content = $new_content;
                $linked_count++;
            } elseif ($new_content === null) {
                // Log regex error for debugging
                error_log('HU NOW Auto Linker: Regex error for keyword: ' . $keyword);
            }
        }
        
        $output = '<h4>Test Results:</h4>';
        $output .= '<p><strong>Original:</strong></p>';
        $output .= '<div style="background: white; padding: 10px; border: 1px solid #ccc; margin: 5px 0;">' . esc_html($test_content) . '</div>';
        $output .= '<p><strong>After Auto-Linking (' . $linked_count . ' links added):</strong></p>';
        $output .= '<div style="background: white; padding: 10px; border: 1px solid #ccc; margin: 5px 0;">' . $processed_content . '</div>';
        
        if ($linked_count === 0) {
            $output .= '<p style="color: orange;"><strong>No keywords were matched. Check your keyword format and test content.</strong></p>';
        }
        
        wp_send_json_success($output);
    }

    function process_content($content) {
        if (is_admin()) return $content;
        global $post;
        $opts = get_option($this->option_name, []);
        if (!$opts) return $content;

        // Debug logging (reduced)
        if (strlen($content) > 100) { // Only log substantial content
            error_log('HU NOW Auto Linker: Processing content for post ID: ' . ($post ? $post->ID : 'unknown') . ', length: ' . strlen($content));
        }

        // Blacklist check
        $blacklist = array_map('trim', explode(',', $opts['blacklist'] ?? ''));
        if ($post && in_array($post->ID, $blacklist)) {
            error_log('HU NOW Auto Linker: Post ID ' . $post->ID . ' is blacklisted');
            return $content;
        }

        $max_links = intval($opts['max_links'] ?? 5);
        $linked = 0;

        if (!empty($opts['keywords']) && is_array($opts['keywords'])) {
            error_log('HU NOW Auto Linker: Found ' . count($opts['keywords']) . ' keywords to process');
            foreach ($opts['keywords'] as $line) {
                if ($linked >= $max_links) break;
                
                $line = trim($line);
                if (empty($line)) continue;
                
                $parts = explode('|', $line);
                if (count($parts) < 2) continue;
                
                $keyword = trim($parts[0]);
                $url = trim($parts[1]);
                
                if (empty($keyword) || empty($url)) continue;
                
                error_log('HU NOW Auto Linker: Processing keyword: ' . $keyword);
                
                // Check if content contains the keyword (case insensitive)
                if (stripos($content, $keyword) !== false) {
                    error_log('HU NOW Auto Linker: Found keyword in content, but regex failed');
                } else {
                    error_log('HU NOW Auto Linker: Keyword not found in content at all');
                }
                
                // Validate and sanitize URL
                $url = esc_url($url);
                if (empty($url)) continue;
                
                $anchors = isset($parts[2]) ? explode(',', $parts[2]) : [$keyword];
                $anchor = trim($anchors[array_rand($anchors)]);
                
                // Determine if it's an internal or external link
                $is_internal = $this->is_internal_url($url);
                
                // Build link attributes
                $link_attrs = 'href="' . $url . '"';
                if (!$is_internal) {
                    $link_attrs .= ' target="_blank" rel="nofollow noopener"';
                } else {
                    $link_attrs .= ' rel="follow"';
                }

                // Simple regex that works for both single words and phrases
                $escaped_keyword = preg_quote($keyword, '/');
                
                // For phrases with spaces, don't use word boundaries
                if (strpos($keyword, ' ') !== false) {
                    // Multi-word phrase: match exact phrase
                    $regex = '/(' . $escaped_keyword . ')/i';
                } else {
                    // Single word: use word boundaries
                    $regex = '/\b(' . $escaped_keyword . ')\b/i';
                }
                $replace = '<a ' . $link_attrs . '>' . esc_html($anchor) . '</a>';

                $new_content = preg_replace($regex, $replace, $content, 1);
                if ($new_content !== null && $new_content !== $content) {
                    $content = $new_content;
                    $linked++;
                    error_log('HU NOW Auto Linker: Successfully linked keyword: ' . $keyword);
                } elseif ($new_content === null) {
                    // Log regex error for debugging
                    error_log('HU NOW Auto Linker: Regex error for keyword: ' . $keyword);
                } else {
                    error_log('HU NOW Auto Linker: No match found for keyword: ' . $keyword . ' in content');
                }
            }
        }
        return $content;
    }

    /**
     * Check if URL is internal to the current WordPress site
     */
    private function is_internal_url($url) {
        $site_url = home_url();
        $parsed_url = parse_url($url);
        $parsed_site = parse_url($site_url);
        
        // If URL is relative (starts with /), it's internal
        if (strpos($url, '/') === 0) {
            return true;
        }
        
        // If URL has same domain as site, it's internal
        if (isset($parsed_url['host']) && isset($parsed_site['host'])) {
            return $parsed_url['host'] === $parsed_site['host'];
        }
        
        return false;
    }

    /**
     * Process Elementor widget content
     */
    function process_elementor_content($widget) {
        if (!$widget) return;
        
        $settings = $widget->get_settings();
        
        // Process text widgets
        if (isset($settings['text'])) {
            $settings['text'] = $this->process_content($settings['text']);
            $widget->set_settings($settings);
        }
        
        // Process heading widgets
        if (isset($settings['title'])) {
            $settings['title'] = $this->process_content($settings['title']);
            $widget->set_settings($settings);
        }
        
        // Process other text fields
        $text_fields = ['description', 'content', 'editor', 'html'];
        foreach ($text_fields as $field) {
            if (isset($settings[$field])) {
                $settings[$field] = $this->process_content($settings[$field]);
                $widget->set_settings($settings);
            }
        }
    }

    /**
     * Process Elementor data before rendering
     */
    function process_elementor_data($data, $post_id) {
        if (!is_array($data)) return $data;
        
        foreach ($data as $element_id => $element_data) {
            if (isset($element_data['settings'])) {
                $settings = $element_data['settings'];
                
                // Process text fields
                $text_fields = ['text', 'title', 'description', 'content', 'editor', 'html'];
                foreach ($text_fields as $field) {
                    if (isset($settings[$field])) {
                        $settings[$field] = $this->process_content($settings[$field]);
                    }
                }
                
                $data[$element_id]['settings'] = $settings;
            }
            
            // Process nested elements
            if (isset($element_data['elements'])) {
                $data[$element_id]['elements'] = $this->process_elementor_data($element_data['elements'], $post_id);
            }
        }
        
        return $data;
    }

    private $output_buffer_started = false;

    /**
     * Start output buffering for Elementor pages
     */
    function start_output_buffer() {
        if (is_admin()) return;
        
        global $post;
        if (!$post) return;
        
        // Only process Elementor pages
        if (!class_exists('\Elementor\Plugin')) {
            return;
        }
        
        try {
            $document = \Elementor\Plugin::$instance->documents->get($post->ID);
            if (!$document || !$document->is_built_with_elementor()) {
                return;
            }
        } catch (Exception $e) {
            error_log('HU NOW Auto Linker: Elementor detection error: ' . $e->getMessage());
            return;
        }
        
        if (!$this->output_buffer_started) {
            ob_start([$this, 'modify_final_output']);
            $this->output_buffer_started = true;
            error_log('HU NOW Auto Linker: Started output buffering for Elementor page');
        }
    }

    /**
     * End output buffering
     */
    function end_output_buffer() {
        if ($this->output_buffer_started) {
            ob_end_flush();
            $this->output_buffer_started = false;
            error_log('HU NOW Auto Linker: Ended output buffering');
        }
    }

    /**
     * Modify final output
     */
    function modify_final_output($content) {
        error_log('HU NOW Auto Linker: Processing final output: ' . substr(strip_tags($content), 0, 200));
        return $this->process_content($content);
    }

    /**
     * Start page buffer for all pages
     */
    function start_page_buffer() {
        if (is_admin()) return;
        
        global $post;
        if (!$post) return;
        
        // Check if this is likely an Elementor page by looking for Elementor data
        $elementor_data = get_post_meta($post->ID, '_elementor_data', true);
        error_log('HU NOW Auto Linker: Checking post ' . $post->ID . ' for Elementor data. Found: ' . (!empty($elementor_data) ? 'YES' : 'NO'));
        
        if (!empty($elementor_data)) {
            error_log('HU NOW Auto Linker: Detected Elementor page, starting page buffer');
            ob_start([$this, 'modify_page_output']);
        } else {
            // Try alternative detection methods
            $elementor_version = get_post_meta($post->ID, '_elementor_version', true);
            $elementor_template_type = get_post_meta($post->ID, '_elementor_template_type', true);
            
            if (!empty($elementor_version) || !empty($elementor_template_type)) {
                error_log('HU NOW Auto Linker: Detected Elementor page via alternative method, starting page buffer');
                ob_start([$this, 'modify_page_output']);
            }
        }
    }

    /**
     * Modify page output
     */
    function modify_page_output($content) {
        error_log('HU NOW Auto Linker: Processing page output: ' . substr(strip_tags($content), 0, 200));
        return $this->process_content($content);
    }

    /**
     * Start universal buffer for all pages
     */
    function start_universal_buffer() {
        if (is_admin()) return;
        
        global $post;
        if (!$post) return;
        
        error_log('HU NOW Auto Linker: Starting universal buffer for post ' . $post->ID);
        ob_start([$this, 'modify_universal_output']);
    }

    /**
     * Modify universal output
     */
    function modify_universal_output($content) {
        error_log('HU NOW Auto Linker: Processing universal output: ' . substr(strip_tags($content), 0, 200));
        return $this->process_content($content);
    }

    /**
     * Direct content search and replace using JavaScript
     */
    function search_and_replace_content() {
        if (is_admin()) return;
        
        global $post;
        if (!$post) return;
        
        $opts = get_option($this->option_name, []);
        if (empty($opts['keywords']) || !is_array($opts['keywords'])) return;
        
        error_log('HU NOW Auto Linker: Running direct content search');
        
        echo '<script>
        document.addEventListener("DOMContentLoaded", function() {
            console.log("HU NOW Auto Linker: Starting direct content search");
            ';
        
        foreach ($opts['keywords'] as $line) {
            $line = trim($line);
            if (empty($line)) continue;
            
            $parts = explode('|', $line);
            if (count($parts) < 2) continue;
            
            $keyword = trim($parts[0]);
            $url = trim($parts[1]);
            
            if (empty($keyword) || empty($url)) continue;
            
            $anchors = isset($parts[2]) ? explode(',', $parts[2]) : [$keyword];
            $anchor = trim($anchors[array_rand($anchors)]);
            
            echo '
            // Search for "' . esc_js($keyword) . '"
            var keyword = "' . esc_js($keyword) . '";
            var url = "' . esc_js($url) . '";
            var anchor = "' . esc_js($anchor) . '";
            
            var walker = document.createTreeWalker(
                document.body,
                NodeFilter.SHOW_TEXT,
                null,
                false
            );
            
            var textNodes = [];
            var node;
            while (node = walker.nextNode()) {
                if (node.textContent.indexOf(keyword) !== -1) {
                    textNodes.push(node);
                }
            }
            
            textNodes.forEach(function(textNode) {
                var parent = textNode.parentNode;
                if (parent.tagName === "A") return; // Skip if already in a link
                
                var text = textNode.textContent;
                
                // Create regex based on whether keyword has spaces
                var regexPattern;
                if (keyword.indexOf(" ") !== -1) {
                    // Multi-word phrase: match exact phrase
                    regexPattern = keyword.replace(/[.*+?^${}()|[\]\\]/g, "\\\\$&");
                } else {
                    // Single word: use word boundaries
                    regexPattern = "\\\\b" + keyword.replace(/[.*+?^${}()|[\]\\]/g, "\\\\$&") + "\\\\b";
                }
                
                var regex = new RegExp(regexPattern, "gi");
                console.log("HU NOW Auto Linker: JS using regex: " + regexPattern);
                
                if (regex.test(text)) {
                    var newHTML = text.replace(regex, "<a href=\\"" + url + "\\" rel=\\"follow\\">" + anchor + "</a>");
                    if (newHTML !== text) {
                        console.log("HU NOW Auto Linker: Found and linked: " + keyword);
                        parent.innerHTML = parent.innerHTML.replace(text, newHTML);
                    }
                }
            });
            ';
        }
        
        echo '
        });
        </script>';
    }

}

new HU_Now_Auto_Linker();