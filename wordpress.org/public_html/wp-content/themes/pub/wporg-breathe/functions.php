<?php
namespace WordPressdotorg\Make\Breathe;

/**
 * Sets up theme defaults.
 */
function after_setup_theme() {
	add_theme_support( 'responsive-embeds' );

	remove_theme_support( 'custom-header' );
	remove_theme_support( 'custom-background' );

	remove_action( 'customize_register', 'breathe_customize_register' );
	remove_action( 'customize_preview_init', 'breathe_customize_preview_js' );
	remove_filter( 'wp_head', 'breathe_color_styles' );

	add_action( 'customize_register', __NAMESPACE__ . '\customize_register' );
}
add_action( 'after_setup_theme', __NAMESPACE__ . '\after_setup_theme', 11 );

/**
 * Add postMessage support for site title and description in the customizer.
 *
 * @param WP_Customize_Manager $wp_customize The customizer object.
 */
function customize_register( $wp_customize ) {
	$wp_customize->get_setting( 'blogname' )->transport = 'postMessage';
	$wp_customize->get_setting( 'blogdescription' )->transport = 'postMessage';

	if ( isset( $wp_customize->selective_refresh ) ) {
		$wp_customize->selective_refresh->add_partial( 'blogname', [
			'selector'            => '.site-title a',
			'container_inclusive' => false,
			'render_callback'     => __NAMESPACE__ . '\customize_partial_blogname',
		] );
	}
}

/**
 * Outputs a 'noindex,follow' `meta` tag when appropriate.
 *
 * Currently applies to:
 * - date-based archives
 * - search results
 * - Author archives
 * - 'mentions' taxonomy
 * - tag archives with fewer than 3 posts
 */
function no_robots() {
	if (
		is_search()
	||
		is_author()
	||
		is_date()
	||
		is_tax( 'mentions' )
	||
		is_tag()
	) {
		wp_no_robots();
	}
}
add_action( 'wp_head', __NAMESPACE__ . '\no_robots', 9 );


/**
 * Outputs `<link rel="canonical">` tags where needed.
 */
function rel_canonical() {
	$canonical = false;
	$queried_object = get_queried_object();

	if ( is_tax() || is_tag() || is_category() ) {
		$canonical = get_term_link( $queried_object );
	} elseif ( is_singular() ) {
		$canonical = get_permalink( $queried_object );
	} elseif ( is_front_page() ) {
		$canonical = home_url( '/' );
	}

	if ( $canonical && get_query_var( 'paged' ) > 1 ) {
		$canonical .= 'page/' . (int) get_query_var( 'paged' ) . '/';
	}

	if ( $canonical ) {
		printf( '<link rel="canonical" href="%s">' . "\n", esc_url( $canonical ) );
	}
}
add_action( 'wp_head', __NAMESPACE__ . '\rel_canonical' );

/**
 * Renders the site title for the selective refresh partial.
 */
function customize_partial_blogname() {
	bloginfo( 'name' );
}

function styles() {
	wp_dequeue_style( 'breathe-style' );
	wp_enqueue_style( 'p2-breathe', get_template_directory_uri() . '/style.css' );

	// Cacheing hack
	wp_enqueue_style( 'wporg-breathe', get_stylesheet_uri(), array( 'p2-breathe' ), '20200402' );
}
add_action( 'wp_enqueue_scripts', __NAMESPACE__ . '\styles', 11 );

function scripts() {
	wp_enqueue_script( 'wporg-breathe-chapters', get_stylesheet_directory_uri() . '/js/chapters.js', array( 'jquery' ), '20200127' );
}
add_action( 'wp_enqueue_scripts', __NAMESPACE__ . '\scripts', 11 );

function inline_scripts() {
	$current_site = get_site();
	?>
	<script type="text/javascript">
		var el = document.getElementById( 'make-welcome-toggle' );
		if ( el ) {
			el.addEventListener( 'click', function( e ) {
				if ( jQuery( '.make-welcome .entry-content' ).is( ':hidden' ) ) {
					document.cookie = el.dataset.cookie + '=' +
						'; expires=Thu, 01 Jan 1970 00:00:00 GMT' +
						'; domain=<?php echo esc_js( $current_site->domain ); ?>' +
						'; path=<?php echo esc_js( $current_site->path ); ?>';
					jQuery( '#make-welcome-toggle' ).text( '<?php esc_attr_e( 'Hide welcome box', 'wporg' ); ?>' );
				} else {
					document.cookie = el.dataset.cookie + '=' + el.dataset.hash +
						'; expires=Fri, 31 Dec 9999 23:59:59 GMT' +
						'; domain=<?php echo esc_js( $current_site->domain ); ?>' +
						'; path=<?php echo esc_js( $current_site->path ); ?>';
					jQuery( '#make-welcome-toggle' ).text( '<?php esc_attr_e( 'Show welcome box', 'wporg' ); ?>' );
				}

				jQuery( '.make-welcome .entry-content' ).slideToggle();
				jQuery( '.make-welcome .post-edit-link' ).toggle();
			} );
		}
	</script>
	<?php
}
add_action( 'wp_footer', __NAMESPACE__ . '\inline_scripts' );

function welcome_box() {
	$welcome      = get_page_by_path( 'welcome' );
	$cookie       = 'welcome-' . get_current_blog_id();
	$hash         = isset( $_COOKIE[ $cookie ] ) ? $_COOKIE[ $cookie ] : '';
	$content_hash = $welcome ? md5( $welcome->post_content ) : '';

	if ( ! $welcome ) {
		return;
	}

	if ( ! $hash || $content_hash !== $hash ) {
		$class = '';
		$label = __( 'Hide welcome box', 'wporg' );
	} else {
		$class = 'hidden';
		$label = __( 'Show welcome box', 'wporg' );
	}

	$columns = preg_split( '|<hr\s*/?>|', $welcome->post_content );
	if ( count( $columns ) === 2 ) {
		$welcome->post_content = "<div class='content-area'>\n\n{$columns[0]}</div><div class='widget-area'>\n\n{$columns[1]}</div>";
	}

	setup_postdata( $welcome );

	// Disable Jetpack sharing buttons
	add_filter( 'sharing_show', '__return_false' );
	?>
	<div class="make-welcome">
		<div class="entry-meta">
			<?php edit_post_link( __( 'Edit', 'wporg' ), '', '', $welcome->ID, 'post-edit-link ' . $class ); ?>
			<button type="button" id="make-welcome-toggle" data-hash="<?php echo $content_hash; ?>" data-cookie="<?php echo $cookie; ?>"><?php echo $label; ?></button>
		</div>
		<div class="entry-content clear <?php echo $class; ?>"">
			<?php the_content(); ?>
		</div>
	</div>
	<?php
	remove_filter( 'sharing_show', '__return_false' );
	wp_reset_postdata();
}
add_action( 'wporg_breathe_after_header', __NAMESPACE__ . '\welcome_box' );

function javascript_notice() {
	?>
	<noscript class="js-disabled-notice">
		<?php _e( 'Please enable JavaScript to view this page properly.', 'wporg' ); ?>
	</noscript>
	<?php
}
add_action( 'wp_footer', __NAMESPACE__ . '\javascript_notice' );

/**
 * Adds each site's slug to the body class, so that CSS rules can target specific sites.
 *
 * @param array $classes Array of CSS classes.
 * @return array Array of CSS classes.
 */
function add_site_slug_to_body_class( $classes ) {
	$current_site = get_site( get_current_blog_id() );

	$classes[] = 'wporg-make';
	if ( $current_site ) {
		$classes[] = 'make-' . trim( $current_site->path, '/' );
	}

	return $classes;
}
add_filter( 'body_class', __NAMESPACE__ . '\add_site_slug_to_body_class' );

/**
 * Defines `link_before` and `link_after` to make icon items accessible for screen readers.
 *
 * @param object  $args  An object of wp_nav_menu() arguments.
 * @param WP_Post $item  Menu item data object.
 * @return object An object of wp_nav_menu() arguments.
 */
function add_screen_reader_text_for_icon_menu_items( $args, $item ) {
	if ( in_array( 'icon', $item->classes, true ) ) {
		$args->link_before = '<span class="screen-reader-text">';
		$args->link_after  = '</span>';
	}

	return $args;
}
add_filter( 'nav_menu_item_args', __NAMESPACE__ . '\add_screen_reader_text_for_icon_menu_items', 10, 2 );

/**
 * Disables Jetpack Mentions on any handbook page or comment.
 *
 * More precisely, this prevents the linked mentions from being shown. A more
 * involved approach (to include clearing meta-cached data) would be needed to
 * more efficiently prevent mentions from being looked for in the first place.
 *
 * @param string $linked  The linked mention.
 * @param string $mention The term being mentioned.
 * @return string
 */
function disable_mentions_for_handbook( $linked, $mention ) {
	if ( function_exists( 'wporg_is_handbook' ) && wporg_is_handbook() && ! is_single( 'credits' ) ) {
		return '@' . $mention;
	}

	return $linked;
}
add_filter( 'jetpack_mentions_linked_mention', __NAMESPACE__ . '\disable_mentions_for_handbook', 10, 2 );

/**
 * More contextual link title for post authors.
 *
 * @param array    $bootstrap_model O2 user model.
 * @param \WP_User $user_data       User data.
 *
 * @return array
 */
function user_model( $bootstrap_model, $user_data ) {
	/* translators: 1: User display_name; 2: User nice_name */
	$bootstrap_model['urlTitle'] = sprintf( __( 'Profile of %1$s (%2$s)', 'wporg' ), $user_data->display_name, '@' . $user_data->user_nicename );

	return $bootstrap_model;
}
add_filter( 'o2_user_model', __NAMESPACE__ . '\user_model', 10, 2 );

/**
 * Fixes bug in (or at least in using) SyntaxHighlighter code shortcodes that
 * causes double-encoding of `>` and '&' characters.
 *
 * @param string $content The text being handled as code.
 * @return string
 */
function fix_code_entity_encoding( $content ) {
	return str_replace( [ '&amp;gt;', '&amp;amp;' ], [ '&gt;', '&amp;' ], $content );
}
add_filter( 'syntaxhighlighter_htmlresult', __NAMESPACE__ . '\fix_code_entity_encoding', 20 );

/**
 * Register translations for plugins without their own GlotPress project.
 */
// wp-content/plugins/wporg-o2-posting-access/wporg-o2-posting-access.php
/* translators: %s: Post title */
__( 'Pending Review: %s', 'wporg' );
__( 'Submit for review', 'wporg' );
_n_noop( '%s post awaiting review', '%s posts awaiting review', 'wporg' );
