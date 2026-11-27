<?php
get_header();
while ( have_posts() ) :
	the_post();
	$cat = tdn_primary_cat();
	?>
	<div class="wrap">
		<article class="article">
			<?php tdn_kicker(); ?>
			<h1><?php the_title(); ?></h1>
			<?php if ( has_excerpt() ) : ?>
				<p class="standfirst"><?php echo esc_html( get_the_excerpt() ); ?></p>
			<?php endif; ?>
			<div class="byline">
				<span>By <strong><?php bloginfo( 'name' ); ?> Staff</strong></span>
				<span class="sep">·</span>
				<time datetime="<?php echo esc_attr( get_the_date( 'c' ) ); ?>"><?php echo esc_html( get_the_date( 'j F Y, g:i a' ) ); ?> AEST</time>
				<span class="sep">·</span>
				<span><?php echo esc_html( tdn_reading_time() ); ?></span>
				<?php if ( get_the_modified_time( 'U' ) - get_the_time( 'U' ) > HOUR_IN_SECONDS ) : ?>
					<span class="sep">·</span><span>Updated <?php echo esc_html( get_the_modified_date( 'j M, g:i a' ) ); ?></span>
				<?php endif; ?>
			</div>
			<?php if ( has_post_thumbnail() ) : ?>
				<figure class="hero-img">
					<?php the_post_thumbnail( 'large' ); ?>
					<?php
					$tdn_tid     = get_post_thumbnail_id();
					$tdn_caption = wp_get_attachment_caption( $tdn_tid );
					$tdn_src     = get_post_meta( $tdn_tid, '_tdn_source_page', true );
					if ( $tdn_caption ) :
						?>
						<figcaption><?php echo esc_html( $tdn_caption ); ?><?php if ( $tdn_src ) : ?> · <a href="<?php echo esc_url( $tdn_src ); ?>" target="_blank" rel="noopener nofollow">Source</a><?php endif; ?></figcaption>
					<?php endif; ?>
				</figure>
			<?php endif; ?>
			<div class="entry-content"><?php the_content(); ?></div>
			<?php
			$tags = get_the_tags();
			if ( $tags ) :
				?>
				<div class="article-tags">
					<?php foreach ( $tags as $t ) : ?>
						<a href="<?php echo esc_url( get_tag_link( $t ) ); ?>">#<?php echo esc_html( $t->name ); ?></a>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>

			<?php
			$related = $cat ? get_posts( array( 'numberposts' => 3, 'post_status' => 'publish', 'category' => $cat->term_id, 'post__not_in' => array( get_the_ID() ) ) ) : array();
			if ( $related ) :
				?>
				<section class="related">
					<h2>More in <?php echo esc_html( $cat->name ); ?></h2>
					<div class="cards"><?php foreach ( $related as $p ) { tdn_card( $p->ID, '', false ); } ?></div>
				</section>
			<?php endif; ?>
		</article>
	</div>
	<?php
endwhile;
get_footer();
