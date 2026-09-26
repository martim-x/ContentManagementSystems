<?php
if ( post_password_required() ) {
	return;
}
?>
<section id="comments" class="comments-area">
	<?php if ( have_comments() ) : ?>
		<h2>Комментарии (<?php echo esc_html( get_comments_number() ); ?>)</h2>
		<ul class="comment-list">
			<?php
			wp_list_comments( array(
				'style'       => 'ul',
				'avatar_size' => 40,
			) );
			?>
		</ul>
		<?php
		the_comments_pagination( array(
			'prev_text' => '←',
			'next_text' => '→',
		) );
		?>
	<?php endif; ?>

	<?php comment_form(); ?>
</section>
