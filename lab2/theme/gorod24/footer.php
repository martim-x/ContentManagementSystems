<footer class="site-footer">
	<div class="footer-inner">
		<div>
			<a class="logo" href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php echo gorod24_logo_html(); ?></a>
			<p class="footer-contacts" style="margin-top: 12px;">
				<?php bloginfo( 'description' ); ?><br>
				Проверка фактов, без фейков.
			</p>
		</div>
		<div>
			<h3>Разделы</h3>
			<?php
			wp_nav_menu( array(
				'theme_location' => 'footer',
				'container'      => false,
				'menu_class'     => 'footer-menu',
				'depth'          => 1,
				'fallback_cb'    => false,
			) );
			?>
		</div>
		<div>
			<h3>Контакты</h3>
			<p class="footer-contacts">
				+375 (17) 305-24-24<br>
				г. Минск, ул. Немига, 5<br>
				news@gorod24.by
			</p>
		</div>
	</div>
	<div class="footer-bottom">© <?php echo esc_html( date_i18n( 'Y' ) ); ?> ООО «Городской портал». Лицензия СМИ № 0009551</div>
</footer>

<a class="to-top" href="#" title="Наверх">↑</a>

<?php wp_footer(); ?>
</body>
</html>
