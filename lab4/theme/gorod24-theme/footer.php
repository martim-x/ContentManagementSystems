<?php
/**
 * Подвал сайта.
 *
 * Задание №9: в файл footer.php перенесён подвал из вёрстки ЛР1,
 * в конце вызывается wp_footer() — он выводит то, что буфер wp_head()
 * отложил на конец документа (скрипты с атрибутом in_footer, служебные
 * блоки, комментарии).
 */
?>

</main>

<footer class="site-footer">
	<div class="footer-inner">
		<div>
			<a class="logo" href="<?php echo esc_url( home_url( '/' ) ); ?>">
				<?php
				// Задание №11 — то же динамическое имя, что и в шапке.
				$gorod24_name = get_bloginfo( 'name' );
				$gorod24_base = preg_replace( '/\d+$/', '', $gorod24_name );
				$gorod24_num  = substr( $gorod24_name, strlen( $gorod24_base ) );

				echo esc_html( $gorod24_base );
				if ( '' !== $gorod24_num ) {
					echo '<span class="dot">' . esc_html( $gorod24_num ) . '</span>';
				}
				?>
			</a>

			<?php
			// Задание №11 — описание сайта из Настроек → Общих.
			?>
			<p class="footer-contacts" style="margin-top: 12px;">
				<?php bloginfo( 'description' ); ?>
			</p>
		</div>

		<div>
			<h3>Разделы</h3>
			<?php
			// Задание №10 — второе динамическое меню, подвал.
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

	<div class="footer-bottom">
		© <?php echo esc_html( gmdate( 'Y' ) ); ?> ООО «Городской портал». Лицензия СМИ № 0009551
	</div>
</footer>

<a class="to-top" href="#" title="Наверх">↑</a>

<?php
// Задание №9 — обязательный вызов в конце документа.
wp_footer();
?>
</body>
</html>
