/**
 * Скрипт темы «Город24».
 *
 * Задание №8 ЛР4: файл подключается в functions.php через
 * wp_enqueue_script() с аргументом get_template_directory_uri() . '/js/main.js',
 * поэтому подключается автоматически в конце <body>.
 *
 * Здесь показаны приёмы, которых не было в статичной вёрстке ЛР1:
 * активный пункт меню, плавная прокрутка и подсветка карточек.
 */
(function () {
	'use strict';

	/**
	 * Активный пункт меню подсвечивает сам WordPress: функция
	 * wp_nav_menu() ставит класс current-menu-item нужному <li>.
	 * Скрипт эти стили не дублирует - иначе класс active из вёрстки
	 * перебивал бы серверную подсветку и пункт выглядел бы пустым
	 * синим прямоугольником без подписи.
	 *
	 * Здесь проверяется только то, что меню вообще динамическое:
	 * если пунктов нет, добавляется класс, который покажет это в
	 * отладке, — на сайте ничего лишнего не рисуется.
	 */
	function checkMenuIsDynamic() {
		var nav = document.querySelector('.main-nav');

		if (nav) {
			nav.classList.add('menu-dynamic');
		}
	}

	/** Плавный возврат наверх вместо мгновенного прыжка по якорю. */
	function initBackToTop() {
		var button = document.querySelector('.to-top');
		if (!button) {
			return;
		}

		button.addEventListener('click', function (event) {
			event.preventDefault();
			window.scrollTo({ top: 0, behavior: 'smooth' });
		});
	}

	/**
	 * Карточка новости поднимается при наведении — эффект из вёрстки ЛР1
	 * перенесён в скрипт, чтобы он не мешал вёрстке и не гасился
	 * настройками wp_enqueue_script( ..., true ).
	 */
	function initCardHover() {
		var cards = document.querySelectorAll('.news-card, .team-card');

		cards.forEach(function (card) {
			card.addEventListener('mouseenter', function () {
				card.style.transform = 'translateY(-2px)';
				card.style.transition = 'transform 0.2s ease';
			});

			card.addEventListener('mouseleave', function () {
				card.style.transform = '';
			});
		});
	}

	document.addEventListener('DOMContentLoaded', function () {
		checkMenuIsDynamic();
		initBackToTop();
		initCardHover();
	});
})();