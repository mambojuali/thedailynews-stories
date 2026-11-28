<?php
get_header();
if ( is_page() ) :
	while ( have_posts() ) :
		the_post();
		?>
		<div class="wrap">
			<article class="article page-content">
				<h1><?php the_title(); ?></h1>
				<div class="entry-content"><?php the_content(); ?></div>
			</article>
		</div>
		<?php
	endwhile;
	get_footer();
	return;
endif;
?>
<div class="wrap">
	<div class="archive-head">
		<h1>
			<?php
			if ( is_category() ) {
				single_cat_title();
			} elseif ( is_tag() ) {
				echo '#' . esc_html( single_tag_title( '', false ) );
			} elseif ( is_search() ) {
				echo 'Search: ' . esc_html( get_search_query() );
			} elseif ( is_archive() ) {
				the_archive_title();
			} else {
				echo 'Latest news';
			}
			?>
		</h1>
	</div>
	<div class="home-body">
		<div class="archive-list">
			<?php if ( have_posts() ) : ?>
				<?php
				while ( have_posts() ) :
					the_post();
					$has = has_post_thumbnail();
					?>
					<article class="row <?php echo $has ? '' : 'no-thumb'; ?>">
						<div>
							<?php tdn_kicker(); ?>
							<h2><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
							<p class="excerpt"><?php echo esc_html( tdn_excerpt( null, 40 ) ); ?></p>
							<div class="meta"><?php echo esc_html( tdn_time_ago() ); ?><span class="sep">·</span><?php echo esc_html( tdn_reading_time() ); ?></div>
						</div>
						<?php if ( $has ) : ?>
							<a href="<?php the_permalink(); ?>"><?php the_post_thumbnail( 'tdn-card' ); ?></a>
						<?php endif; ?>
					</article>
				<?php endwhile; ?>
				<div class="pagination"><?php echo paginate_links(); ?></div>
			<?php else : ?>
				<p class="empty-note">No stories found.</p>
			<?php endif; ?>
		</div>
		<?php get_sidebar(); ?>
	</div>
</div>
<?php
get_footer();
