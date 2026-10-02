<?php
/**
 * Шаблон отдельной страницы.
 *
 * Задание №6: содержимое файла index.html из вёрстки ЛР1 перенесено сюда,
 * а шапка и подвал вынесены в header.php и footer.php (задание №9).
 *
 * Задание №12: содержимое страницы выводится не статическим HTML из файла,
 * а функциями the_post() и the_content() — то есть текст берётся из
 * редактора консоли WordPress (Медиа → Медиафайлы для картинок).
 *
 * Связи <a href="news.html"> заменены на get_permalink() и home_url(),
 * потому что в WordPress адреса задаются slug'ами в консоли, а не именами файлов.
 */

get_header();

while ( have_posts() ) :
	the_post();
	?>

	<article <?php post_class(); ?>>

		<?php if ( is_front_page() ) : ?>
			<?php
			// Блок акции — только на главной странице.
			// В вёрстке это был первый <div> внутри <main>.
			?>
			<div style="background-color: #3563e9; color: #ffffff; text-align: center; padding: 12px 24px; font-size: 14px;">
				<?php
				$gorod24_promo = get_theme_mod( 'gorod24_promo', 'Первый месяц премиума без рекламы — бесплатно для новых читателей до 31.10.2026' );
				echo esc_html( $gorod24_promo );
				?>
			</div>

			<section class="hero">
				<div class="hero-inner">
					<span class="hero-tag">Главное</span>
					<?php
					// Задание №12 — вызов the_post() в цикле выше открыл
					// текущую запись, поэтому the_title() и the_content()
					// читают данные из редактора консоли.
					the_title( '<h1>', '</h1>' );
					?>
					<a class="btn" href="<?php echo esc_url( home_url( '/novosti/' ) ); ?>">Читать в новостях</a>
				</div>
			</section>
		<?php endif; ?>

		<?php
		// Задание №12 — вывод содержимого страницы, созданного в консоли.
		// the_content() печатает всё, что пользователь набрал в редакторе,
		// включая картинки, загруженные через Медиафайлы.
		the_content();
		?>

		<?php if ( is_front_page() ) : ?>
			<div class="container">
				<div class="section-title">
					<h2>Последние новости</h2>
					<a class="all-link" href="<?php echo esc_url( home_url( '/novosti/' ) ); ?>">Все новости →</a>
				</div>

				<div class="news-grid">
					<?php
					// Последние 6 записей — цикл WP_Query, чтобы карточки
					// брались из базы, а не были вписаны в файл.
					$gorod24_news = new WP_Query( array(
						'post_type'           => 'post',
						'posts_per_page'      => 6,
						'ignore_sticky_posts' => true,
					) );

					if ( $gorod24_news->have_posts() ) :
						while ( $gorod24_news->have_posts() ) :
							$gorod24_news->the_post();

							$gorod24_cats = get_the_category();
							$gorod24_cat  = $gorod24_cats ? $gorod24_cats[0]->slug : 'gorod';
							?>
							<article class="news-card">
								<a href="<?php the_permalink(); ?>">
									<div class="thumb thumb-<?php echo esc_attr( $gorod24_cat ); ?>"></div>
									<?php if ( $gorod24_cats ) : ?>
										<span class="category"><?php echo esc_html( $gorod24_cats[0]->name ); ?></span>
									<?php endif; ?>
									<h3><?php the_title(); ?></h3>
									<time datetime="<?php echo esc_attr( get_the_date( 'Y-m-d' ) ); ?>">
										<?php echo esc_html( get_the_date( 'd F, H:i' ) ); ?>
									</time>
								</a>
							</article>
							<?php
						endwhile;
						wp_reset_postdata();
					endif;
					?>
				</div>
			</div>
		<?php endif; ?>

	</article>

	<?php
endwhile;

get_footer();