<aside class="sidebar">
	<section class="widget">
		<h2>Latest news</h2>
		<ol class="latest-list">
			<?php foreach ( get_posts( array( 'numberposts' => 8, 'post_status' => 'publish' ) ) as $p ) : ?>
				<li><div><a href="<?php echo esc_url( get_permalink( $p ) ); ?>"><?php echo esc_html( get_the_title( $p ) ); ?></a><span class="meta"><?php echo esc_html( tdn_time_ago( $p->ID ) ); ?></span></div></li>
			<?php endforeach; ?>
		</ol>
	</section>
	<section class="widget about-box">
		<h2>About</h2>
		<p><strong><?php bloginfo( 'name' ); ?></strong> covers Australian national news, business and property, and the world's biggest stories — clear, sourced and to the point.</p>
	</section>
</aside>
