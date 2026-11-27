<?php
if ( post_password_required() ) {
	return;
}
?>
<section id="comments" class="comments">
	<h2 class="comments-title">
		<?php
		$n = get_comments_number();
		echo $n ? esc_html( sprintf( _n( '%s comment', '%s comments', $n, 'tdn-news' ), number_format_i18n( $n ) ) ) : 'Join the conversation';
		?>
	</h2>

	<?php if ( have_comments() ) : ?>
		<ol class="comment-list">
			<?php
			wp_list_comments(
				array(
					'style'       => 'ol',
					'short_ping'  => true,
					'avatar_size' => 0,
					'callback'    => 'tdn_comment',
				)
			);
			?>
		</ol>
		<?php the_comments_pagination(); ?>
	<?php endif; ?>

	<?php if ( comments_open() ) : ?>
		<?php
		comment_form(
			array(
				'title_reply'          => 'Leave a comment',
				'title_reply_to'       => 'Reply to %s',
				'label_submit'         => 'Post comment',
				'comment_notes_before' => '<p class="comment-notes">Your email won\'t be published. Comments are moderated: keep it civil, on topic and factual. The Daily News desk may reply.</p>',
				'class_submit'         => 'tdn-btn',
			)
		);
		?>
	<?php else : ?>
		<p class="no-comments">Comments are closed for this story.</p>
	<?php endif; ?>
</section>
