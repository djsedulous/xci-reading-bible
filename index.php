<?php

$next = !empty($_GET['next']) ? 1 + (int) $_GET['next'] : 1;
$planStartDateTime = new DateTime("28.11.2022");
$dayDateTime = (new DateTime())->sub(new DateInterval('P5D'))
    ->modify("+$next Tuesday");
$startDay = ($planStartDateTime)->diff($dayDateTime)->days - 1;

global $books;
$books = getBooks();

$days = array(
    'Воскресенье', 'Понедельник', 'Вторник', 'Среда',
    'Четверг', 'Пятница', 'Суббота'
);

$replaces = [
    "{TITLE}" => "План Чтения Библии - " . $dayDateTime->format("d.m.Y")
];
for ($i = 1; $i <= 7; $i++)
{
    $planForDay = getPlanByDay($startDay + $i);

    $replaces["{DAY{$i}_NAME}"] = $days[$dayDateTime->format('w')]
        . " <div class='day-num'>" . $startDay + $i . "</div>";
    $replaces["{DAY{$i}_DATE}"] = $dayDateTime->format("d.m.Y");
    $replaces["{DAY{$i}_CONTENT}"] = join("<br>", array_map(function ($content) use ($planForDay) {
        return parseData($content);
    }, $planForDay['segments'][0]["content"]));

    $dayDateTime->add(new DateInterval("P1D"));
}

$template = file_get_contents('template.html');
echo str_replace(array_keys($replaces), $replaces, $template);

function parseData($content) {
    global $books;
    $book = substr($content, 0, 3);
    $chapter = trim(substr($content, 4, 2), '.');

    $versesArray = explode("+", str_replace("$book.$chapter.", "", $content));

    $verses = "";
    if (count($versesArray) > 1)
    {
        $verses = ":" . implode('-', [min($versesArray), max($versesArray)]);
    }

    return "{$books[$book]} {$chapter}{$verses}";
}

function getBooks() {
    if (file_exists("books.json"))
    {
        return json_decode(file_get_contents("books.json"), true);
    }

    $response = makeRequest('https://nodejs.bible.com/api/bible/version/3.1?id=400');
    $data = [];
    array_map(function($book) use (&$data) {
        return $data[$book['usfm']] = $book['human'];
    }, $response['books']);

    file_put_contents("books.json", json_encode($data, JSON_PRETTY_PRINT| JSON_UNESCAPED_UNICODE));
    return $data;
}

function getPlanByDay($day)
{
    $response = makeRequest("https://plans.youversionapi.com/4.0/plans/2072/days/{$day}?together=false");
    array_shift($response['segments']);
    return $response;
}

function makeRequest($url) {
    $ch = curl_init($url);

    $headers = [
        'authority: plans.youversionapi.com',
        'accept: application/json',
        'accept-language: ru',
        'cache-control: no-cache',
        'origin: https://my.bible.com',
        'pragma: no-cache',
        'referer: https://my.bible.com/',
        'sec-ch-ua: "Not_A Brand";v="99", "Google Chrome";v="109", "Chromium";v="109"',
        'sec-ch-ua-mobile: ?0',
        'sec-ch-ua-platform: "macOS"',
        'sec-fetch-dest: empty',
        'sec-fetch-mode: cors',
        'sec-fetch-site: cross-site',
        'user-agent: Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/109.0.0.0 Safari/537.36',
        'x-youversion-app-platform: web',
        'x-youversion-app-version: 4',
        'x-youversion-client: youversion',
        'Content-Type: application/json',
    ];
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RESOLVE, ["plans.youversionapi.com:443:151.101.1.32"]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
    curl_setopt($ch, CURLOPT_ENCODING, 'gzip, deflate');

    $response = json_decode(curl_exec($ch), true);

    if (curl_errno($ch) > 0) {
        print_r( curl_getinfo($ch));
        echo curl_error($ch);
    }

    curl_close($ch);

    return $response;
}

