<!doctype html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<a class="screen-reader-text" href="#main">Skip to content</a>

<div class="topbar">
	<div class="wrap">
		<span><?php echo esc_html( wp_date( 'l, j F Y' ) ); ?></span>
		<div class="topbar-right">
		<a class="subscribe-btn" href="#subscribe">Subscribe</a>
		<form role="search" method="get" class="search-form" action="<?php echo esc_url( home_url( '/' ) ); ?>">
			<label class="screen-reader-text" for="s">Search</label>
			<input type="search" id="s" name="s" placeholder="Search The Daily News" value="<?php echo esc_attr( get_search_query() ); ?>">
		</form>
		</div>
	</div>
</div>
<?php tdn_notice(); ?>

<header class="masthead">
	<div class="wrap">
		<p class="site-title"><a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php bloginfo( 'name' ); ?></a></p>
		<?php if ( get_bloginfo( 'description' ) ) : ?>
			<p class="site-tagline"><?php bloginfo( 'description' ); ?></p>
		<?php endif; ?>
	</div>
</header>

<nav class="sections" aria-label="Sections">
	<div class="wrap">
		<?php
		$tdn_cur = '';
		if ( is_single() ) {
			$c       = tdn_primary_cat();
			$tdn_cur = $c ? $c->slug : '';
		}
		tdn_sections_nav( $tdn_cur );
		?>
	</div>
</nav>

<?php
$tdn_ticker = get_posts( array( 'numberposts' => 5, 'post_status' => 'publish' ) );
if ( $tdn_ticker ) :
	?>
<div class="ticker">
	<div class="wrap">
		<span class="label">Latest</span>
		<ul>
			<?php foreach ( $tdn_ticker as $p ) : ?>
				<li><a href="<?php echo esc_url( get_permalink( $p ) ); ?>"><?php echo esc_html( get_the_title( $p ) ); ?></a></li>
			<?php endforeach; ?>
		</ul>
	</div>
</div>
<?php endif; ?>

<main id="main">
