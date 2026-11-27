</main>

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
