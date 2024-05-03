<?php
header('Content-Type: text/html; charset=utf-8');

function getAllGamesOnPage($url)
{
    // Получение HTML-кода
    $html = file_get_contents($url);

    $dom = new DOMDocument();
    @$dom->loadHTML($html);
    $xpath = new DOMXPath($dom);

    $elements = $xpath->query('//h2[@class="post-title entry-title"]/a');

    foreach ($elements as $element) {
        echo $element->getAttribute('href') . "\n";

        // Запись в PHP-файл
        $filename = "gameslist.txt";

        $file = fopen($filename, "a", 'UTF-8');

        fwrite($file,  $element->getAttribute('href') . "\n" );

        fclose($file);
    }

}

for ($i =0; $i < 802; $i++) {
    getAllGamesOnPage("https://thelastgame.ru/page/" . $i);
}

?>