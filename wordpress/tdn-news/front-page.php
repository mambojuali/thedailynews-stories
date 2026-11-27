<?php
get_header();

$tdn_seen = array();
$lead     = get_posts( array( 'numberposts' => 5, 'post_status' => 'publish' ) );
?>
<div class="wrap">
	<?php if ( $lead ) : ?>
		<?php
		$top        = array_shift( $lead );
		$tdn_seen[] = $top->ID;
		$has        = has_post_thumbnail( $top );
		?>
		<section class="lead-zone">
			<article class="lead-story <?php echo $has ? '' : 'no-thumb'; ?>">
				<?php if ( $has ) : ?>
					<a class="thumb" href="<?php echo esc_url( get_permalink( $top ) ); ?>"><?php echo get_the_post_thumbnail( $top, 'large' ); ?></a>
				<?php endif; ?>
				<?php tdn_kicker( $top->ID ); ?>
				<h2><a href="<?php echo esc_url( get_permalink( $top ) ); ?>"><?php echo esc_html( get_the_title( $top ) ); ?></a></h2>
				<p class="excerpt"><?php echo esc_html( tdn_excerpt( $top->ID, 45 ) ); ?></p>
				<div class="meta"><?php echo esc_html( tdn_time_ago( $top->ID ) ); ?><span class="sep">·</span><?php echo esc_html( tdn_reading_time( $top->ID ) ); ?></div>
			</article>
			<div class="rail">
				<?php foreach ( $lead as $p ) : $tdn_seen[] = $p->ID; ?>
					<div class="rail-item">
						<?php tdn_kicker( $p->ID ); ?>
						<h3><a href="<?php echo esc_url( get_permalink( $p ) ); ?>"><?php echo esc_html( get_the_title( $p ) ); ?></a></h3>
						<div class="meta"><?php echo esc_html( tdn_time_ago( $p->ID ) ); ?></div>
					</div>
				<?php endforeach; ?>
			</div>
		</section>
	<?php else : ?>
		<section class="lead-zone"><p class="empty-note">Stories will appear here once they're published.</p></section>
	<?php endif; ?>

	<div class="home-body">
		<div class="sections-col">
			<?php
			foreach ( TDN_SECTIONS as $slug => $name ) :
				$term = get_category_by_slug( $slug );
				if ( ! $term ) {
					continue;
				}
				$posts = get_posts(
					array(
						'numberposts'  => 5,
						'post_status'  => 'publish',
						'category'     => $term->term_id,
						'post__not_in' => $tdn_seen,
					)
				);
				?>
				<section class="section-block cat-<?php echo esc_attr( $slug ); ?>">
					<div class="section-head">
						<h2><a href="<?php echo esc_url( get_category_link( $term ) ); ?>"><?php echo esc_html( $name ); ?></a></h2>
						<a class="more" href="<?php echo esc_url( get_category_link( $term ) ); ?>">More <?php echo esc_html( $name ); ?> →</a>
					</div>
					<?php if ( $posts ) : ?>
						<div class="cards">
							<?php
							foreach ( $posts as $i => $p ) {
								tdn_card( $p->ID, 0 === $i ? 'first' : '', true, 0 === $i ? 'large' : 'tdn-card' );
							}
							?>
						</div>
					<?php else : ?>
						<p class="empty-note">No <?php echo esc_html( strtolower( $name ) ); ?> stories yet.</p>
					<?php endif; ?>
				</section>
			<?php endforeach; ?>
		</div>
		<?php get_sidebar(); ?>
	</div>
</div>
<?php
get_footer();
