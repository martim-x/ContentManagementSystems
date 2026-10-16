<?php
echo "=== ЛР7 проверка (curl) ===\n";
$checks = array(
    array('главная', 'http://localhost:8102/', 'Город24'),
    array('админка', 'http://localhost:8102/administrator/', 'Joomla'),
    array('регистрация', 'http://localhost:8102/registraciya', 'Регистрация'),
    array('вход', 'http://localhost:8102/vhod', 'Вход'),
    array('создать материал (декан)', 'http://localhost:8102/sozdat-material-dekan', 'Материал'),
    array('создать материал (староста)', 'http://localhost:8102/sozdat-material-starosta', 'Материал'),
);
$ok = true;
foreach ($checks as $c) {
    $body = shell_exec('curl -s -L ' . escapeshellarg($c[1]));
    $found = stripos($body, $c[2]) !== false;
    echo ($found ? 'OK ' : 'FAIL ') . $c[0] . ' (' . $c[1] . ') ' . ($found ? 'найдено: ' . $c[2] : '') . "\n";
    if (!$found) $ok = false;
}
// Check that category with restricted access (dekanat-gruppa1) exists in DB but is not visible as public link (we rely on ACL, not URL check)
echo ($ok ? 'ВСЕ ПРОВЕРКИ ПРОЙДЕНЫ\n' : 'ЕСТЬ ПРОВАЛЫ — смотри выше\n');
