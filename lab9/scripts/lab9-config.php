<?php
/**
 * Конфигурация ЛР9: блоки, статьи, страницы, меню.
 * Предполагает, что база ЛР8 уже настроена (lab8-config.php).
 */
// База ЛР8
$base = __DIR__ . '/lab8-config.php';
if (file_exists($base)) {
    require $base;
}

use Drupal\node\Entity\Node;
use Drupal\menu_link_content\Entity\MenuLinkContent;
use Drupal\block_content\Entity\BlockContent;
use Drupal\block\Entity\Block;
use Drupal\file\Entity\File;

// ---------- 1. Регион с двумя блоками ----------
for ($i = 1; $i <= 2; $i++) {
    $bid = 'lab9_block_' . $i;
    // block content
    $existing_blocks = \Drupal::entityQuery('block_content')
        ->condition('info', "Блок {$i} ЛР9")
        ->accessCheck(FALSE)
        ->execute();
    if ($existing_blocks) {
        $bcid = reset($existing_blocks);
        $bc = BlockContent::load($bcid);
    } else {
        $bc = BlockContent::create([
            'info' => "Блок {$i} ЛР9",
            'type' => 'basic',
            'body' => ['value' => "Содержимое блока {$i} (ЛР9). Произвольный текст.", 'format' => 'basic_html'],
        ]);
        $bc->save();
    }
    // place
    if (!Block::load($bid)) {
        $placed = Block::create([
            'id' => $bid,
            'plugin' => 'block_content:' . $bc->uuid(),
            'region' => 'sidebar_first',
            'theme' => 'bootstrap5',
            'weight' => $i,
            'visibility' => [],
            'settings' => ['label' => "Блок {$i}", 'label_display' => 'visible'],
        ]);
        $placed->save();
        echo "блок {$i}: размещён в sidebar_first\n";
    } else {
        echo "блок {$i}: уже размещён\n";
    }
}

// ---------- 2. Статья + пункт меню ----------
$article = Node::create([
    'type' => 'article',
    'title' => 'Пример статьи ЛР9',
    'body' => ['value' => 'Текст статьи для изучения настроек материала.', 'format' => 'basic_html'],
    'status' => 1,
]);
$article->save();
echo "статья: создана (node/{$article->id()})\n";

// menu link for article
$link = MenuLinkContent::create([
    'title' => 'Пример статьи',
    'link' => ['uri' => 'internal:/node/' . $article->id()],
    'menu_name' => 'main',
]);
$link->save();
echo "пункт меню для статьи: создан\n";

// ---------- 3. Три страницы ----------
// 3.1 История фирмы
$history = Node::create([
    'type' => 'page',
    'title' => 'История фирмы Lenovo',
    'body' => [
        'value' => '<p>История основания фирмы-производителя ноутбуков Lenovo. '
            . 'Текст и картинки. Ссылка на официальный сайт: '
            . '<a href="https://www.lenovo.com">lenovo.com</a>.</p>',
        'format' => 'basic_html',
    ],
    'status' => 1,
]);
$history->save();
echo "страница «История фирмы»: node/{$history->id()}\n";

// 3.2 Галерея (colorbox)
// generate two images
$img_urls = [];
for ($i = 1; $i <= 2; $i++) {
    $img = imagecreatetruecolor(120, 80);
    $bg = imagecolorallocate($img, 50 + $i * 60, 100, 150);
    imagefill($img, 0, 0, $bg);
    $fname = "gallery{$i}.png";
    $fpath = "public://{$fname}";
    ob_start();
    imagepng($img);
    $data = ob_get_clean();
    imagedestroy($img);
    file_put_contents($fpath, $data);

    $file = File::create(['uri' => $fpath, 'status' => 1]);
    $file->setSize(filesize($fpath));
    $file->setMimeType('image/png');
    $file->save();
    $url = '/sites/default/files/' . $fname;
    $img_urls[] = $url;
    echo "  изображение {$i}: {$url}\n";
}

$gallery_html = '<p>Галерея фотографий (colorbox):</p>';
foreach ($img_urls as $u) {
    $gallery_html .= '<a class="colorbox" href="' . $u . '"><img src="' . $u . '" width="120" alt="фото" /></a> ';
}
$gallery = Node::create([
    'type' => 'page',
    'title' => 'Галерея ноутбуков',
    'body' => ['value' => $gallery_html, 'format' => 'basic_html'],
    'status' => 1,
]);
$gallery->save();
echo "страница «Галерея»: node/{$gallery->id()}\n";

// 3.3 Прайс-лист
$price_html = '<table border="1" cellpadding="4"><thead><tr><th>Модель</th><th>Цена</th></tr></thead>'
    . '<tbody><tr><td>Lenovo IdeaPad G1</td><td>1200 BYN</td></tr>'
    . '<tr><td>Lenovo IdeaPad N2</td><td>1500 BYN</td></tr></tbody></table>';
$price = Node::create([
    'type' => 'page',
    'title' => 'Прайс-лист ноутбуков',
    'body' => ['value' => $price_html, 'format' => 'basic_html'],
    'status' => 1,
]);
$price->save();
echo "страница «Прайс-лист»: node/{$price->id()}\n";

// ---------- 4. Меню ----------
// Пункты для трёх страниц
foreach ([
    ['История фирмы', $history->id()],
    ['Галерея', $gallery->id()],
    ['Прайс-лист', $price->id()],
] as $item) {
    $ml = MenuLinkContent::create([
        'title' => $item[0],
        'link' => ['uri' => 'internal:/node/' . $item[1]],
        'menu_name' => 'main',
    ]);
    $ml->save();
    echo "пункт меню «{$item[0]}»: создан\n";
}

// «Каталог» с пустой ссылкой + подпункты терминов
$catalog = MenuLinkContent::create([
    'title' => 'Каталог',
    'link' => ['uri' => 'route:<nolink>'],
    'menu_name' => 'main',
    'weight' => 5,
]);
$catalog->save();
echo "пункт меню «Каталог» (пустая ссылка): создан\n";

$terms = \Drupal::entityTypeManager()->getStorage('taxonomy_term')
    ->loadByProperties(['vid' => 'lenovo']);
foreach ($terms as $t) {
    $child = MenuLinkContent::create([
        'title' => $t->getName(),
        'link' => ['uri' => 'internal:/taxonomy/term/' . $t->id()],
        'menu_name' => 'main',
        'parent' => 'menu_link_content:' . $catalog->uuid(),
    ]);
    $child->save();
    echo "  подпункт «{$t->getName()}»: создан\n";
}

// Дополнительное меню в футере
$footer_links = [
    ['Главная', 1],
    ['Контакты', $history->id()],
];
foreach ($footer_links as $fl) {
    $fml = MenuLinkContent::create([
        'title' => $fl[0],
        'link' => ['uri' => 'internal:/node/' . $fl[1]],
        'menu_name' => 'footer',
    ]);
    $fml->save();
    echo "пункт футер-меню «{$fl[0]}»: создан\n";
}

echo "ЛР9: конфигурация завершена\n";