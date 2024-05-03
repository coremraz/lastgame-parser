<?php
header('Content-Type: text/html; charset=utf-8');
function parseGameInfo($text)
{
    // Удаляем "Информация о игре"
    $text = trim(str_replace('Информация о игре', '', $text));

    // Разбиваем описание на строки
    $lines = explode("\n", $text);

    // Создаем ассоциативный массив
    $info = [];
    foreach ($lines as $line) {
        // Разбиваем строку на ключ и значение
        $parts = explode(':', $line);
        if (count($parts) === 2) {
            // Очищаем ключ и значение
            $originalKey = trim($parts[0]);
            $value = trim($parts[1]);

            // Обрабатываем специальные случаи
            switch ($originalKey) {
                case 'Жанр':
                    // Преобразуем "Экшены" в ["Экшены"]
                    $value = explode(',', $value);
                    break;
                case 'Язык интерфейса':
                    // Преобразуем "английский, русский" в ["английский", "русский"]
                    $value = explode(',', $value);
                    break;
                case 'Версия':
                    // Добавляем "1." к версии, если она не начинается с цифры
                    if (!preg_match('/^\d/', $value)) {
                        $value = "$value";
                    }
                    break;
            }

            // Преобразуем ключи в английский
            $keys = [
                'Год выпуска' => 'release_date',
                'Жанр' => 'genre',
                'Разработчик' => 'developer',
                'Версия' => 'version',
                'Язык интерфейса' => 'lang',
                'Таблетка' => 'crack',
            ];
            $key = $keys[$originalKey];

            // Добавляем ключ и значение в массив
            $info[$key] = $value;
        }
    }

    return $info;
}

function cleanTitle($title) {
    $title = strtolower(preg_replace('/[^a-z0-9]+/i', '_', $title));
    return trim($title, '_');
}

function gzdecodee($d)
{
    $f = ord(substr($d, 3, 1));
    $h = 10;
    $e = 0;
    if ($f & 4) {
        $e = unpack('v', substr($d, 10, 2));
        $e = $e[1];
        $h += 2 + $e;
    }
    if ($f & 8) {
        $h = strpos($d, chr(0), $h) + 1;
    }
    if ($f & 16) {
        $h = strpos($d, chr(0), $h) + 1;
    }
    if ($f & 2) {
        $h += 2;
    }
    $u = gzinflate(substr($d, $h));
    if ($u === FALSE) {
        $u = $d;
    }
    return $u;
}


// Функция для проверки совпадения
function matchesRegex($text, $pattern)
{
    return preg_match($pattern, $text) === 1;
}

function parseInfo($url)
{
    // Получение HTML-кода
    $html = file_get_contents($url);
    $dom = new DOMDocument();
    @$dom->loadHTML($html);
    $xpath = new DOMXPath($dom);

    //info

    $gameInfo = trim(str_replace("'", "`",str_replace(" ", " ", $xpath->query('//div[@class="entry-inner"]/div[@style="float: left;width:50%;"]')->item(0)->textContent)));

    $info = (object)parseGameInfo($gameInfo);

// Поиск описания

    $description = trim(str_replace("'", "`",str_replace(" ", " ", $xpath->query('//div[@class="entry-inner"]/p')->item(0)->textContent)));

    // Поиск заголовка
    $title = trim(str_replace("'", "`",str_replace(" ", " ", preg_replace('/^(.*?)(?:—|–|-).*/', '$1', $description))));
    $clearTitle = trim(str_replace("]", ")",str_replace("[", "(",str_replace("'", "`",str_replace(".", "_",(str_replace("|", "",str_replace("__", "_",str_replace("\\", "",str_replace("/", "",str_replace("?", "",str_replace(",", "_", str_replace(":", "", str_replace(" ", "_", $title))))))))))))));

    mkdir('./games/' . $clearTitle);
// Поиск изображения
    $src = $xpath->query('//div[@class="entry-inner"]/p[1]/strong/a/@href | //div[@class="entry-inner"]/p[1]/a/@href | //div[@class="entry-inner"]/p[1]/img/@src')->item(0)->textContent;

// Сохранение изображения
    $filename = $clearTitle . '.jpg';
    file_put_contents('./games/' . $clearTitle . "/" . $filename, file_get_contents($src));

    //Скриншоты
    mkdir('./games/' . $clearTitle . "/screenshots");
    for ($i = 1; $i < 4; $i++) {
        $screenshots[] = "screenshot_" . $i . "_" . $filename;
        $filename = $clearTitle . '.jpg';
        file_put_contents('./games/' . $clearTitle . "/screenshots/" . "screenshot_" . $i . "_" . $filename, file_get_contents($xpath->query("//div[@id='gamepics']/p[1]/a[$i]/@href | //div[@id='gamepics']/p[1]/strong/a[$i]/@href")->item(0)->textContent));
    }


    //скачивание торрента

    $download = $xpath->query('//*[@class="btn_green"]')->item(0);

    $torrUrl = $download->getAttribute('href');
    $pattern1 = '/.*\.torrent/';
    echo "url: " . $torrUrl . "\n";
    if (matchesRegex($torrUrl, $pattern1)) {
        $torrent = file_get_contents($torrUrl, FILE_BINARY);

        $torrent = gzdecodee($torrent);
        $torrentName = "torrentFreaks_" . str_replace(".", "",$clearTitle) . '.torrent';
        file_put_contents('./games/' . $clearTitle . "/" . $torrentName, $torrent);

    } else {
        echo "no link";
    }


// Формирование массива
    $data = [
        $title => [
            "title" => $title,
            "path_title" => $clearTitle,
            "cover" => $clearTitle . ".jpg",
            "description" => $description,
            "info" => [
                "release_date" => $info->release_date,
                "genre" => $info->genre,
                "developer" => $info->developer,
                "version" => $info->version,
                "lang" => $info->lang,
                "crack" => $info->crack,
            ],
            "screenshots" => $screenshots,
            "download" => $torrentName,
            "url" => trim(str_replace("/", "", str_replace("https://thelastgame.ru/", " ", $url)))
        ]
    ];

// Запись в PHP-файл
    $filename = './games/' . $clearTitle . "/" . 'output.php';

    $file = fopen($filename, "w", 'UTF-8');

    fwrite($file, "<?php\n\n");
    fwrite($file, '$data = ' . var_export($data, true) . ';');
    fwrite($file, "\n\n?>");

    fclose($file);

}

$filename = "gameslist.txt";

$lines = file($filename);

foreach ($lines as $key => $line) {
    $line = trim($line); // Remove leading/trailing whitespace

    if (empty($line)) {
        continue; // Skip empty lines
    }

    try {
        $html = file_get_contents($line);
        echo "current: " . $line . "\n";
        parseInfo($line);

        // Remove the line from the array
        unset($lines[$key]);

        // Write the modified array back to the file
        file_put_contents($filename, implode("\n", $lines));
    } catch (Exception $e) {
        echo "Error processing URL: " . $line . " - " . $e->getMessage() . "\n";
        continue; // Skip to next line on error
    }
}


?>