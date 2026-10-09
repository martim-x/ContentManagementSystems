<?php
/**
 * Код аналитических сервисов (задание 10 ЛР6).
 *
 * Google Analytics подключается через штатное поле «Custom Code» темы
 * cassiopeia: сниппет попадает в <head> всех страниц сайта. Для лабы
 * используется тестовый идентификатор G-GOROD24LAB — при демонстрации
 * важно само наличие кода аналитики в HTML, а не отправка событий в
 * реальный аккаунт Google.
 *
 * Запуск (внутри контейнера web):
 *   php /var/www/html/scripts/lab6-analytics.php
 *
 * Скрипт идемпотентен: повторная вставка не выполняется.
 */

echo "== Аналитика ==\n";

$template_path = '/var/www/html/templates/cassiopeia/index.php';
$template      = (string) file_get_contents( $template_path );

if ( false !== strpos( $template, 'g-gorod24lab' ) ) {
	echo "Google Analytics: код уже подключён\n";

	return;
}

$ga = <<<HTML
<!-- g24-analytics: Google Analytics, тестовый идентификатор ЛР6 -->
<script async src="https://www.googletagmanager.com/gtag/js?id=G-GOROD24LAB"></script>
<script>
	window.dataLayer = window.dataLayer || [];
	function gtag(){dataLayer.push(arguments);}
	gtag('js', new Date());
	gtag('config', 'G-GOROD24LAB');
</script>
HTML;

// Сниппет ставится непосредственно перед закрывающим тегом head —
// штатное место для кодов аналитики и счётчиков.
$updated = str_replace( '</head>', $ga . "\n</head>", $template );

if ( $updated === $template ) {
	echo "ОШИБКА: не найден </head> в шаблоне cassiopeia\n";
	exit( 1 );
}

file_put_contents( $template_path, $updated );
echo "Google Analytics: сниппет добавлен в <head> шаблона cassiopeia (G-GOROD24LAB)\n";
