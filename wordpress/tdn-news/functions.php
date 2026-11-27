<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const TDN_SECTIONS = array( 'australia' => 'Australia', 'business' => 'Business', 'property' => 'Property', 'sports' => 'Sports', 'technology' => 'Technology', 'world' => 'World' );

add_action( 'after_setup_theme', function () {
	add_theme_support( 'title-tag' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'html5', array( 'search-form', 'gallery', 'caption', 'style', 'script' ) );
	add_image_size( 'tdn-card', 800, 450, true );
} );

add_action( 'wp_enqueue_scripts', function () {
	wp_enqueue_style( 'tdn-news', get_stylesheet_uri(), array(), wp_get_theme()->get( 'Version' ) );
} );

/** Primary category of a post (first of the four sections, else first category). */
function tdn_primary_cat( $post_id = null ) {
	$cats = get_the_category( $post_id );
	if ( ! $cats ) {
		return null;
	}
	foreach ( $cats as $c ) {
		if ( isset( TDN_SECTIONS[ $c->slug ] ) ) {
			return $c;
		}
	}
	return $cats[0];
}

function tdn_kicker( $post_id = null ) {
	$c = tdn_primary_cat( $post_id );
	if ( ! $c ) {
		return;
	}
	printf( '<a class="kicker cat-%s" href="%s">%s</a>', esc_attr( $c->slug ), esc_url( get_category_link( $c ) ), esc_html( $c->name ) );
}

function tdn_time_ago( $post_id = null ) {
	$t    = get_post_time( 'U', true, $post_id );
	$diff = time() - $t;
	if ( $diff < DAY_IN_SECONDS ) {
		return human_time_diff( $t ) . ' ago';
	}
	return get_the_date( 'j M Y', $post_id );
}

function tdn_reading_time( $post_id = null ) {
	$words = str_word_count( wp_strip_all_tags( get_post_field( 'post_content', $post_id ) ) );
	return max( 1, (int) round( $words / 230 ) ) . ' min read';
}

function tdn_excerpt( $post_id = null, $words = 28 ) {
	$e = get_the_excerpt( $post_id );
	return wp_trim_words( $e, $words, '…' );
}

/** Small story picture: featured image if set, else a section-coloured tile. */
function tdn_thumb( $post_id, $slug = '', $name = '' ) {
	if ( has_post_thumbnail( $post_id ) ) {
		echo get_the_post_thumbnail( $post_id, 'tdn-thumb', array( 'loading' => 'lazy', 'alt' => '' ) );
		return;
	}
	if ( ! $slug ) {
		$c    = tdn_primary_cat( $post_id );
		$slug = $c ? $c->slug : '';
		$name = $c ? $c->name : '';
	}
	echo '<span class="thumb-fallback cat-' . esc_attr( $slug ) . '"><span>' . esc_html( $name ?: 'News' ) . '</span></span>';
}

add_action( 'after_setup_theme', function () {
	add_image_size( 'tdn-thumb', 320, 200, true );
}, 20 );

/** Card markup used across the theme. */
function tdn_card( $post_id, $class = '', $show_excerpt = true, $size = 'tdn-card' ) {
	$has = has_post_thumbnail( $post_id );
	echo '<article class="card ' . esc_attr( $class ) . ( $has ? '' : ' no-thumb' ) . '">';
	if ( $has ) {
		echo '<a class="thumb" href="' . esc_url( get_permalink( $post_id ) ) . '">' . get_the_post_thumbnail( $post_id, $size ) . '</a>';
	}
	echo '<div class="card-body">';
	tdn_kicker( $post_id );
	echo '<h3><a href="' . esc_url( get_permalink( $post_id ) ) . '">' . esc_html( get_the_title( $post_id ) ) . '</a></h3>';
	if ( $show_excerpt ) {
		echo '<p class="excerpt">' . esc_html( tdn_excerpt( $post_id ) ) . '</p>';
	}
	echo '<div class="meta">' . esc_html( tdn_time_ago( $post_id ) ) . '</div>';
	echo '</div></article>';
}

/** Wrap the importer's trailing "Sources" list in a styled box. */
add_filter( 'the_content', function ( $html ) {
	if ( ! is_singular( 'post' ) ) {
		return $html;
	}
	return preg_replace( '#<h2>\s*Sources\s*</h2>\s*(<ul>.*?</ul>)#is', '<aside class="sources-box"><h2>Sources</h2>$1</aside>', $html, 1 );
}, 20 );

/** No "[…]" on excerpts. */
add_filter( 'excerpt_more', function () {
	return '…';
} );

/** Hide default Uncategorized from nav lists. */
function tdn_sections_nav( $current = '' ) {
	echo '<ul>';
	echo '<li class="' . ( is_front_page() ? 'current' : '' ) . '"><a href="' . esc_url( home_url( '/' ) ) . '">Home</a></li>';
	foreach ( TDN_SECTIONS as $slug => $name ) {
		$term = get_category_by_slug( $slug );
		if ( ! $term ) {
			continue;
		}
		$cls = ( is_category( $slug ) || ( is_single() && $current === $slug ) ) ? 'current' : '';
		echo '<li class="' . esc_attr( $cls ) . '"><a href="' . esc_url( get_category_link( $term ) ) . '">' . esc_html( $name ) . '</a></li>';
	}
	echo '</ul>';
}

/** Meta description + Open Graph / Twitter Card tags (used by social previews and auto-posters). */
add_action( 'wp_head', function () {
	$single = is_singular( 'post' );
	$title  = $single ? get_the_title() : get_bloginfo( 'name' );
	$desc   = $single ? get_the_excerpt() : get_bloginfo( 'description' );
	$desc   = wp_trim_words( wp_strip_all_tags( (string) $desc ), 35, '' );
	$url    = $single ? get_permalink() : home_url( '/' );
	$img    = $single && has_post_thumbnail() ? get_the_post_thumbnail_url( null, 'full' ) : get_option( 'tdn_share_default_url', '' );

	if ( $desc ) {
		echo '<meta name="description" content="' . esc_attr( $desc ) . '">' . "\n";
	}
	$tags = array(
		'og:site_name'   => get_bloginfo( 'name' ),
		'og:type'        => $single ? 'article' : 'website',
		'og:title'       => $title,
		'og:description' => $desc,
		'og:url'         => $url,
		'og:locale'      => 'en_AU',
	);
	foreach ( $tags as $k => $v ) {
		echo '<meta property="' . esc_attr( $k ) . '" content="' . esc_attr( $v ) . '">' . "\n";
	}
	if ( $img ) {
		echo '<meta property="og:image" content="' . esc_url( $img ) . '">' . "\n";
		echo '<meta property="og:image:width" content="1200"><meta property="og:image:height" content="630">' . "\n";
	}
	if ( $single ) {
		echo '<meta property="article:published_time" content="' . esc_attr( get_the_date( 'c' ) ) . '">' . "\n";
		$c = tdn_primary_cat();
		if ( $c ) {
			echo '<meta property="article:section" content="' . esc_attr( $c->name ) . '">' . "\n";
		}
	}
	echo '<meta name="twitter:card" content="summary_large_image">' . "\n";
	echo '<meta name="twitter:title" content="' . esc_attr( $title ) . '">' . "\n";
	echo '<meta name="twitter:description" content="' . esc_attr( $desc ) . '">' . "\n";
	if ( $img ) {
		echo '<meta name="twitter:image" content="' . esc_url( $img ) . '">' . "\n";
	}
}, 1 );

/** Put the story's picture in the RSS feed so auto-posters (Postiz, dlvr.it etc.) attach it. */
add_action( 'rss2_ns', function () {
	echo 'xmlns:media="http://search.yahoo.com/mrss/"' . "\n";
} );
add_action( 'rss2_item', function () {
	if ( has_post_thumbnail() ) {
		$src = wp_get_attachment_image_src( get_post_thumbnail_id(), 'full' );
		if ( $src ) {
			echo '<media:content url="' . esc_url( $src[0] ) . '" medium="image" type="image/png" width="' . (int) $src[1] . '" height="' . (int) $src[2] . '" />' . "\n";
			echo '<enclosure url="' . esc_url( $src[0] ) . '" type="image/png" length="0" />' . "\n";
		}
	}
} );
