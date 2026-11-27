</main>

<?php if ( function_exists( 'tdn_subscribe_form' ) ) : ?>
<section id="subscribe" class="subscribe-band">
	<div class="wrap">
		<div class="sub-intro">
			<h2>Get The Daily News in your inbox</h2>
			<p>Choose the sections you care about and get every new story, or one tidy 7am digest.</p>
		</div>
		<?php tdn_subscribe_form( 'band' ); ?>
	</div>
</section>
<?php endif; ?>

<footer class="site-footer">
	<div class="wrap">
		<p class="foot-title"><?php bloginfo( 'name' ); ?></p>
		<p><?php bloginfo( 'description' ); ?></p>
		<nav aria-label="Footer sections"><?php tdn_sections_nav(); ?></nav>
		<p class="small">&copy; <?php echo esc_html( wp_date( 'Y' ) ); ?> <?php bloginfo( 'name' ); ?>. Stories are researched with AI assistance and reviewed by an editor before publication. Each story lists its sources.</p>
	</div>
</footer>
<?php wp_footer(); ?>
</body>
</html>
