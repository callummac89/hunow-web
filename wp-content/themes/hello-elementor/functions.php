<?php
/**
 * Theme functions and definitions
 *
 * @package HelloElementor
 */

use Elementor\WPNotificationsPackage\V110\Notifications as ThemeNotifications;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

define( 'HELLO_ELEMENTOR_VERSION', '3.3.0' );

if ( ! isset( $content_width ) ) {
	$content_width = 800; // Pixels.
}

if ( ! function_exists( 'hello_elementor_setup' ) ) {
	/**
	 * Set up theme support.
	 *
	 * @return void
	 */
	function hello_elementor_setup() {
		if ( is_admin() ) {
			hello_maybe_update_theme_version_in_db();
		}

		if ( apply_filters( 'hello_elementor_register_menus', true ) ) {
			register_nav_menus( [ 'menu-1' => esc_html__( 'Header', 'hello-elementor' ) ] );
			register_nav_menus( [ 'menu-2' => esc_html__( 'Footer', 'hello-elementor' ) ] );
		}

		if ( apply_filters( 'hello_elementor_post_type_support', true ) ) {
			add_post_type_support( 'page', 'excerpt' );
		}

		if ( apply_filters( 'hello_elementor_add_theme_support', true ) ) {
			add_theme_support( 'post-thumbnails' );
			add_theme_support( 'automatic-feed-links' );
			add_theme_support( 'title-tag' );
			add_theme_support(
				'html5',
				[
					'search-form',
					'comment-form',
					'comment-list',
					'gallery',
					'caption',
					'script',
					'style',
				]
			);
			add_theme_support(
				'custom-logo',
				[
					'height'      => 100,
					'width'       => 350,
					'flex-height' => true,
					'flex-width'  => true,
				]
			);
			add_theme_support( 'align-wide' );
			add_theme_support( 'responsive-embeds' );

			/*
			 * Editor Styles
			 */
			add_theme_support( 'editor-styles' );
			add_editor_style( 'editor-styles.css' );

			/*
			 * WooCommerce.
			 */
			if ( apply_filters( 'hello_elementor_add_woocommerce_support', true ) ) {
				// WooCommerce in general.
				add_theme_support( 'woocommerce' );
				// Enabling WooCommerce product gallery features (are off by default since WC 3.0.0).
				// zoom.
				add_theme_support( 'wc-product-gallery-zoom' );
				// lightbox.
				add_theme_support( 'wc-product-gallery-lightbox' );
				// swipe.
				add_theme_support( 'wc-product-gallery-slider' );
			}
		}
	}
}
add_action( 'after_setup_theme', 'hello_elementor_setup' );

function hello_maybe_update_theme_version_in_db() {
	$theme_version_option_name = 'hello_theme_version';
	// The theme version saved in the database.
	$hello_theme_db_version = get_option( $theme_version_option_name );

	// If the 'hello_theme_version' option does not exist in the DB, or the version needs to be updated, do the update.
	if ( ! $hello_theme_db_version || version_compare( $hello_theme_db_version, HELLO_ELEMENTOR_VERSION, '<' ) ) {
		update_option( $theme_version_option_name, HELLO_ELEMENTOR_VERSION );
	}
}

if ( ! function_exists( 'hello_elementor_display_header_footer' ) ) {
	/**
	 * Check whether to display header footer.
	 *
	 * @return bool
	 */
	function hello_elementor_display_header_footer() {
		$hello_elementor_header_footer = true;

		return apply_filters( 'hello_elementor_header_footer', $hello_elementor_header_footer );
	}
}

if ( ! function_exists( 'hello_elementor_scripts_styles' ) ) {
	/**
	 * Theme Scripts & Styles.
	 *
	 * @return void
	 */
	function hello_elementor_scripts_styles() {
		$min_suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';

		if ( apply_filters( 'hello_elementor_enqueue_style', true ) ) {
			wp_enqueue_style(
				'hello-elementor',
				get_template_directory_uri() . '/style' . $min_suffix . '.css',
				[],
				HELLO_ELEMENTOR_VERSION
			);
		}

		if ( apply_filters( 'hello_elementor_enqueue_theme_style', true ) ) {
			wp_enqueue_style(
				'hello-elementor-theme-style',
				get_template_directory_uri() . '/theme' . $min_suffix . '.css',
				[],
				HELLO_ELEMENTOR_VERSION
			);
		}

		if ( hello_elementor_display_header_footer() ) {
			wp_enqueue_style(
				'hello-elementor-header-footer',
				get_template_directory_uri() . '/header-footer' . $min_suffix . '.css',
				[],
				HELLO_ELEMENTOR_VERSION
			);
		}
	}
}
add_action( 'wp_enqueue_scripts', 'hello_elementor_scripts_styles' );

if ( ! function_exists( 'hello_elementor_register_elementor_locations' ) ) {
	/**
	 * Register Elementor Locations.
	 *
	 * @param ElementorPro\Modules\ThemeBuilder\Classes\Locations_Manager $elementor_theme_manager theme manager.
	 *
	 * @return void
	 */
	function hello_elementor_register_elementor_locations( $elementor_theme_manager ) {
		if ( apply_filters( 'hello_elementor_register_elementor_locations', true ) ) {
			$elementor_theme_manager->register_all_core_location();
		}
	}
}
add_action( 'elementor/theme/register_locations', 'hello_elementor_register_elementor_locations' );

if ( ! function_exists( 'hello_elementor_content_width' ) ) {
	/**
	 * Set default content width.
	 *
	 * @return void
	 */
	function hello_elementor_content_width() {
		$GLOBALS['content_width'] = apply_filters( 'hello_elementor_content_width', 800 );
	}
}
add_action( 'after_setup_theme', 'hello_elementor_content_width', 0 );

if ( ! function_exists( 'hello_elementor_add_description_meta_tag' ) ) {
	/**
	 * Add description meta tag with excerpt text.
	 *
	 * @return void
	 */
	function hello_elementor_add_description_meta_tag() {
		if ( ! apply_filters( 'hello_elementor_description_meta_tag', true ) ) {
			return;
		}

		if ( ! is_singular() ) {
			return;
		}

		$post = get_queried_object();
		if ( empty( $post->post_excerpt ) ) {
			return;
		}

		echo '<meta name="description" content="' . esc_attr( wp_strip_all_tags( $post->post_excerpt ) ) . '">' . "\n";
	}
}
add_action( 'wp_head', 'hello_elementor_add_description_meta_tag' );

// Admin notice
if ( is_admin() ) {
	require get_template_directory() . '/includes/admin-functions.php';
}

// Settings page
require get_template_directory() . '/includes/settings-functions.php';

// Header & footer styling option, inside Elementor
require get_template_directory() . '/includes/elementor-functions.php';

if ( ! function_exists( 'hello_elementor_customizer' ) ) {
	// Customizer controls
	function hello_elementor_customizer() {
		if ( ! is_customize_preview() ) {
			return;
		}

		if ( ! hello_elementor_display_header_footer() ) {
			return;
		}

		require get_template_directory() . '/includes/customizer-functions.php';
	}
}
add_action( 'init', 'hello_elementor_customizer' );

if ( ! function_exists( 'hello_elementor_check_hide_title' ) ) {
	/**
	 * Check whether to display the page title.
	 *
	 * @param bool $val default value.
	 *
	 * @return bool
	 */
	function hello_elementor_check_hide_title( $val ) {
		if ( defined( 'ELEMENTOR_VERSION' ) ) {
			$current_doc = Elementor\Plugin::instance()->documents->get( get_the_ID() );
			if ( $current_doc && 'yes' === $current_doc->get_settings( 'hide_title' ) ) {
				$val = false;
			}
		}
		return $val;
	}
}
add_filter( 'hello_elementor_page_title', 'hello_elementor_check_hide_title' );

/**
 * BC:
 * In v2.7.0 the theme removed the `hello_elementor_body_open()` from `header.php` replacing it with `wp_body_open()`.
 * The following code prevents fatal errors in child themes that still use this function.
 */
if ( ! function_exists( 'hello_elementor_body_open' ) ) {
	function hello_elementor_body_open() {
		wp_body_open();
	}
}

function hello_elementor_get_theme_notifications(): ThemeNotifications {
	static $notifications = null;

	if ( null === $notifications ) {
		require get_template_directory() . '/vendor/autoload.php';

		$notifications = new ThemeNotifications(
			'hello-elementor',
			HELLO_ELEMENTOR_VERSION,
			'theme'
		);
	}

	return $notifications;
}

hello_elementor_get_theme_notifications();

// Register Post Type for Places to Eat
function hunow_register_post_type_eat() 
{
    $labels = array(
        'name'                  => 'Places to Eat',
        'singular_name'         => 'Place to Eat',
        'menu_name'             => 'Places to Eat',
        'name_admin_bar'        => 'Place to Eat',
        'add_new'               => 'Add New',
        'add_new_item'          => 'Add New Place',
        'new_item'              => 'New Place',
        'edit_item'             => 'Edit Place',
        'view_item'             => 'View Place',
        'all_items'             => 'All Places',
        'search_items'          => 'Search Places',
        'parent_item_colon'     => 'Parent Places:',
        'not_found'             => 'No places found.',
        'not_found_in_trash'    => 'No places found in Trash.',
    );

    $args = array(
        'labels'                => $labels,
        'public'                => true,
        'publicly_queryable'    => true,
        'show_ui'               => true,
        'show_in_menu'          => true,
        'query_var'             => true,
        'rewrite'               => array('slug' => 'eat'),
        'capability_type'       => 'post',
        'has_archive'           => true,
        'hierarchical'          => false,
        'menu_position'         => 5,
        'menu_icon'             => 'dashicons-carrot',
        'supports'              => array('title', 'editor', 'thumbnail', 'excerpt'),
        'show_in_rest'          => true, // Enables Gutenberg and API support
    );

    register_post_type('eat', $args);
}
add_action('init', 'hunow_register_post_type_eat');

// Register 'Cuisine' Taxonomy
function hunow_register_taxonomy_cuisine() {
    register_taxonomy('cuisine', 'eat', array(
        'labels' => array(
            'name' => 'Cuisines',
            'singular_name' => 'Cuisine',
            'search_items' => 'Search Cuisines',
            'all_items' => 'All Cuisines',
            'edit_item' => 'Edit Cuisine',
            'update_item' => 'Update Cuisine',
            'add_new_item' => 'Add New Cuisine',
            'new_item_name' => 'New Cuisine Name',
            'menu_name' => 'Cuisines',
        ),
        'hierarchical' => true, // Like categories (set false for tag-like)
        'rewrite' => array('slug' => 'cuisine'),
        'show_admin_column' => true,
        'show_in_rest' => true,
    ));
}
add_action('init', 'hunow_register_taxonomy_cuisine');

// Register 'Location' Taxonomy
function hunow_register_taxonomy_location() {
    register_taxonomy('location', ['eat', 'event', 'activity'], array(
        'labels' => array(
            'name' => 'Locations',
            'singular_name' => 'Location',
        ),
        'hierarchical' => true,
        'public' => true,
        'rewrite' => array('slug' => 'location'),
        'show_admin_column' => true,
        'show_in_rest' => true,
    ));
}
add_action('init', 'hunow_register_taxonomy_location');

// Register Post Types
function hunow_register_additional_post_types() {

    // Event CPT
    register_post_type('event', array(
        'labels' => array(
            'name' => 'Events',
            'singular_name' => 'Event',
            'add_new_item' => 'Add New Event',
            'edit_item' => 'Edit Event',
        ),
        'public' => true,
        'has_archive' => true,
        'rewrite' => array('slug' => 'events'),
        'menu_icon' => 'dashicons-calendar-alt',
        'supports' => array('title', 'editor', 'excerpt', 'thumbnail'),
        'show_in_rest' => true,
    ));

    // Activity CPT
    register_post_type('activity', array(
        'labels' => array(
            'name' => 'Things to Do',
            'singular_name' => 'Activity',
            'add_new_item' => 'Add New Activity',
            'edit_item' => 'Edit Activity',
        ),
        'public' => true,
        'has_archive' => true,
        'rewrite' => array('slug' => 'activities'),
        'menu_icon' => 'dashicons-palmtree',
        'supports' => array('title', 'editor', 'excerpt', 'thumbnail'),
        'show_in_rest' => true,
    ));

}
add_action('init', 'hunow_register_additional_post_types');

function hunow_acf_google_map_api( $api ) {
    $api['key'] = 'AIzaSyA2hbunGQ5v09GfpRaIXjt_Rn5MqmLhTTc';
    return $api;
}
add_filter('acf/fields/google_map/api', 'hunow_acf_google_map_api');

// Enable ACF REST API for all fields
add_filter('acf/rest_api/field_settings/show_in_rest', '__return_true');

// Ensure ACF fields are exposed in REST API responses
add_filter('acf/settings/rest_api_format', function() {
    return 'standard'; // Use 'standard' format for better compatibility
});


// Links For Taxonomies
function hunow_set_single_cuisine_link( $value, $post_id, $field ) {
    $terms = get_the_terms( $post_id, 'cuisine' );
    if ( $terms && ! is_wp_error( $terms ) ) {
        return get_term_link( $terms[0] );
    }
    return '';
}
add_filter( 'acf/load_value/name=cuisine_link', 'hunow_set_single_cuisine_link', 10, 3 );

function hunow_set_location_link( $value, $post_id, $field ) {
    $terms = get_the_terms( $post_id, 'location' );
    if ( $terms && ! is_wp_error( $terms ) ) {
        return get_term_link( $terms[0] );
    }
    return '';
}
add_filter( 'acf/load_value/name=location_link', 'hunow_set_location_link', 10, 3 );

// Register 'Activity Type' Taxonomy
function hunow_register_activity_type_taxonomy() {
    register_taxonomy('activity_type', ['activity'], [
        'label' => 'Activity Types',
        'hierarchical' => true,
        'rewrite' => ['slug' => 'activity-type'],
        'show_admin_column' => true,
        'show_in_rest' => true,
    ]);
}
add_action('init', 'hunow_register_activity_type_taxonomy');

// Register 'Guide' Post Type
function hunow_register_guide_post_type() {
    register_post_type('guide', array(
        'labels' => array(
            'name' => 'Guides',
            'singular_name' => 'Guide',
            'add_new_item' => 'Add New Guide',
            'edit_item' => 'Edit Guide',
        ),
        'public' => true,
        'has_archive' => true,
        'rewrite' => array('slug' => 'guides'),
        'menu_icon' => 'dashicons-lightbulb',
        'supports' => array('title', 'editor', 'excerpt', 'thumbnail'),
        'show_in_rest' => true,
    ));
}
add_action('init', 'hunow_register_guide_post_type');

// Events This Month
add_action( 'elementor/query/events_this_month', function( $query ) {
    $start = date('Ymd', strtotime('first day of this month'));
    $end   = date('Ymd', strtotime('last day of this month'));

    $query->set('post_type', 'event');
    $query->set('meta_query', array(
        array(
            'key'     => 'event_date',
            'compare' => 'BETWEEN',
            'value'   => array($start, $end),
            'type'    => 'NUMERIC'
        )
    ));
    $query->set('orderby', 'meta_value');
    $query->set('meta_key', 'event_date');
    $query->set('order', 'ASC');
});

/**
 * Shortcode: [restaurant_faq_schema]
 * Outputs FAQPage JSON-LD from ACF repeater 'faq_schema' (question/answer).
 * Works only on CPT 'eat'.
 */
add_shortcode('restaurant_faq_schema', function () {
    $post_id   = get_the_ID();
    $post_type = $post_id ? get_post_type($post_id) : null;

    // Only run on the "eat" CPT
    if ($post_type !== 'eat') {
        return '';
    }

    if (!function_exists('have_rows') || !have_rows('faq_schema', $post_id)) {
        return '';
    }

    $entities = [];
    while (have_rows('faq_schema', $post_id)) {
        the_row();
        $q = trim((string) get_sub_field('question'));
        $a = trim((string) get_sub_field('answer'));
        if ($q !== '' && $a !== '') {
            $entities[] = [
                '@type' => 'Question',
                'name'  => wp_strip_all_tags($q),
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text'  => wp_strip_all_tags($a)
                ]
            ];
        }
    }

    if (!$entities) return '';

    $schema = [
        '@context'   => 'https://schema.org',
        '@type'      => 'FAQPage',
        'mainEntity' => $entities
    ];

    return '<script type="application/ld+json">' .
           wp_json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) .
           '</script>';
});

add_action('elementor/query/events_loop', function( $query ) {
    // today in Ymd format (safe for ACF Date Picker if return format = Ymd)
    $today = current_time('Ymd');

    $meta_query = [
        'relation' => 'OR',
        // Event still running (end date in the future)
        [
            'key'     => 'event_end',
            'value'   => $today,
            'compare' => '>=',
            'type'    => 'NUMERIC'
        ],
        // No end date, but start date in the future
        [
            'relation' => 'AND',
            [
                'key'     => 'event_end',
                'compare' => 'NOT EXISTS',
            ],
            [
                'key'     => 'event_date',
                'value'   => $today,
                'compare' => '>=',
                'type'    => 'NUMERIC'
            ]
        ]
    ];

    $query->set('meta_query', $meta_query);
    $query->set('meta_key', 'event_date');
    $query->set('orderby', 'meta_value');
    $query->set('order', 'ASC');
});

// [listing_offer] — prints all offer boxes from venue portal; supports multiple offers
add_shortcode('listing_offer', function () {
    $id = get_the_ID();
    if (!$id) return '';

    // Collect all offers - check numbered fields (same way portal retrieves them)
    // Just check up to a reasonable limit (20) and show whatever exists
    $offers = array();
    for ($i = 1; $i <= 20; $i++) {
        // Use get_post_meta() directly since portal saves with update_post_meta()
        // Try post_meta first (what portal saves to), then ACF as fallback
        $title = trim((string) get_post_meta($id, 'offer_title_' . $i, true));
        if (empty($title) && function_exists('get_field')) {
            $title = trim((string) get_field('offer_title_' . $i, $id));
        }
        
        if (!empty($title)) {
            $description = (string) get_post_meta($id, 'offer_description_' . $i, true);
            if (empty($description) && function_exists('get_field')) {
                $description = (string) get_field('offer_description_' . $i, $id);
            }
            $offers[] = array(
                'title' => $title,
                'description' => $description
            );
        }
    }

    // Also check legacy offer_title for backward compatibility
    $legacy_title = trim((string) get_post_meta($id, 'offer_title', true));
    if (empty($legacy_title) && function_exists('get_field')) {
        $legacy_title = trim((string) get_field('offer_title', $id));
    }
    if (!empty($legacy_title)) {
        // Check if this offer is already in the list (avoid duplicates)
        $exists = false;
        foreach ($offers as $offer) {
            if ($offer['title'] === $legacy_title) {
                $exists = true;
                break;
            }
        }
        if (!$exists) {
            $legacy_desc = (string) get_post_meta($id, 'offer_description', true);
            if (empty($legacy_desc) && function_exists('get_field')) {
                $legacy_desc = (string) get_field('offer_description', $id);
            }
            $offers[] = array(
                'title' => $legacy_title,
                'description' => $legacy_desc
            );
        }
    }

    if (empty($offers)) return '';

    // Get optional CTA fields (shared across all offers)
    $cta_txt = trim((string) get_post_meta($id, 'offer_cta_text', true));
    if (empty($cta_txt) && function_exists('get_field')) {
        $cta_txt = trim((string) get_field('offer_cta_text', $id));
    }
    $cta_url = trim((string) get_post_meta($id, 'offer_cta_url', true));
    if (empty($cta_url) && function_exists('get_field')) {
        $cta_url = trim((string) get_field('offer_cta_url', $id));
    }

    ob_start();
    foreach ($offers as $offer) {
        $title = $offer['title'];
        $text = $offer['description'];
        
        if (empty($title) && empty($text)) continue;
        
        ?>
        <section class="listing-offer">
          <div class="listing-offer__wrap">
            <div class="listing-offer__icon" aria-hidden="true">%</div>
            <div class="listing-offer__content">
              <?php if ($title) : ?>
                <h3 class="listing-offer__title"><?php echo esc_html($title); ?></h3>
              <?php endif; ?>

              <?php if ($text) : ?>
                <div class="listing-offer__text"><?php echo wpautop(wp_kses_post($text)); ?></div>
              <?php endif; ?>

              <div class="listing-offer__foot">
                <?php
                if ($cta_txt && $cta_url) {
                  $parsed = wp_parse_url($cta_url);
                  $joiner = ( isset($parsed['query']) && $parsed['query'] ) ? '&' : '?';
                  $cta_url_full = $cta_url . $joiner . 'utm_source=hunow&utm_medium=listing&utm_campaign=offer';

                  echo '<a class="listing-offer__cta" href="' . esc_url($cta_url_full) . '" target="_blank" rel="nofollow noopener">'
                        . esc_html($cta_txt) .
                       '</a>';
                }
                ?>
              </div>
            </div>
          </div>
        </section>
        <?php
    }
    return ob_get_clean();
});

/**
 * Pretty-print ACF date fields globally on the FRONT END.
 * Targets: event_date, event_end
 * Works whether the stored/returned value is Ymd (20251010) or Y-m-d (2025-10-10).
 * If you ever need the raw value in PHP, call: get_field('event_date', get_the_ID(), false)
 */
function hunow_format_acf_event_dates($value, $post_id, $field) {
    if (is_admin() || empty($value)) {
        return $value; // don't touch admin or empty values
    }

    // Try to normalize to a timestamp
    $ts = false;

    // Ymd (e.g. 20251010)
    if (preg_match('/^\d{8}$/', $value)) {
        $dt = DateTime::createFromFormat('Ymd', $value);
        if ($dt) {
            $ts = $dt->getTimestamp();
        }
    } else {
        // Anything strtotime can parse (e.g. 2025-10-10)
        $maybe = strtotime($value);
        if ($maybe) {
            $ts = $maybe;
        }
    }

    if ($ts) {
        // Use your site’s date format (Settings → General), or hardcode e.g. 'D j F Y'
        return date_i18n(get_option('date_format'), $ts);
    }

    return $value; // fallback
}
add_filter('acf/format_value/name=event_date', 'hunow_format_acf_event_dates', 10, 3);
add_filter('acf/format_value/name=event_end',  'hunow_format_acf_event_dates', 10, 3);