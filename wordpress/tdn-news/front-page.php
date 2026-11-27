<?php
/**
 * Homepage: summary of all stories, grouped by section, newest first.
 * Each story: small picture, bold title, brief, time.
 */
get_header();
?>
<div class="wrap">
	<div class="summary-grid">
		<?php
		$tdn_any = false;
		foreach ( TDN_SECTIONS as $slug => $name ) :
			$term = get_category_by_slug( $slug );
			if ( ! $term ) {
				continue;
			}
			$posts = get_posts(
				array(
					'numberposts' => 8,
					'post_status' => 'publish',
					'category'    => $term->term_id,
					'orderby'     => 'date',
					'order'       => 'DESC',
				)
			);
			if ( ! $posts ) {
				continue;
			}
			$tdn_any = true;
			?>
			<section class="summary-block cat-<?php echo esc_attr( $slug ); ?>">
				<div class="summary-head">
					<h2><a href="<?php echo esc_url( get_category_link( $term ) ); ?>"><?php echo esc_html( $name ); ?></a></h2>
					<a class="more" href="<?php echo esc_url( get_category_link( $term ) ); ?>">More <?php echo esc_html( $name ); ?> →</a>
				</div>
				<?php foreach ( $posts as $i => $p ) : ?>
					<article class="summary-item<?php echo 0 === $i ? ' is-latest' : ''; ?>">
						<a class="summary-thumb" href="<?php echo esc_url( get_permalink( $p ) ); ?>" tabindex="-1" aria-hidden="true">
							<?php tdn_thumb( $p->ID, $slug, $name ); ?>
						</a>
						<div class="summary-text">
							<h3><a href="<?php echo esc_url( get_permalink( $p ) ); ?>"><?php echo esc_html( get_the_title( $p ) ); ?></a></h3>
							<p class="brief"><?php echo esc_html( tdn_excerpt( $p->ID, 0 === $i ? 34 : 24 ) ); ?></p>
							<div class="meta"><?php echo esc_html( tdn_time_ago( $p->ID ) ); ?></div>
						</div>
					</article>
				<?php endforeach; ?>
			</section>
		<?php endforeach; ?>
		<?php if ( ! $tdn_any ) : ?>
			<p class="empty-note">Stories will appear here once they're published.</p>
		<?php endif; ?>
	</div>
</div>
<?php
get_footer();
