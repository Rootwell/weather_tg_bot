<?php

include('vendor/autoload.php');

define('OMW_API_KEY', 'f49d2e1f2af5403364afaefc15ef69d0');
define('TG_API_URL', 'https://api.telegram.org/bot');
define('TG_API_KEY', '1783994096:AAGM-rGczxLn2F7BuYHz940Ro7lf_20Aedg');
define('FORMATTING_HORIZONTAL_LINE', '________');

$dataFromTg = json_decode(file_get_contents('php://input'), TRUE);


$chatId = $dataFromTg['message']['chat']['id'];
if ($dataFromTg['message']['from']['id'] == 361515342) {
    sendMessage($chatId, 'Пашол нахерен, Эмиль, погода тебе говно на голову будет ахаххахаъ');
}
if (isset($dataFromTg['message']['text'])) {
    $messageText = $dataFromTg['message']['text'];
}
file_put_contents("log.txt", print_r($dataFromTg, true), FILE_APPEND);
if (isset($messageText) && $messageText === '/start') {
    sendMessage($chatId, "Привет!
Я — робот предсказатель погоды.
Чтобы получить прогноз необходимо:
Указать город и временной промежуток.
Доступные временные промежутки:
сейчас, сегодня, завтра, на 4 дня

Пример:
Москва, завтра");
    die();
}

$words = [];

if (isset($messageText)) {
    $words = explode(', ', $messageText);
    $words[1] = mb_strtolower($words[1]);
    if (sizeof($words) !== 2 || !in_array($words[1], ['сейчас', 'сегодня', 'завтра', 'на 4 дня'])) {
        sendMessage($chatId, 'Неправильный формат запроса :(');
        die();
    }
    foreach ($words as &$word) {
        $word = trim($word, " \n\r\t\v\0,");
    }
} else {
    sendMessage($chatId, 'Не понимаю тебя :(');
}

//ТОЛЬКО ГОРОДА РОССИИ
$city = $words[0];
//СЕЙЧАС
//СЕГОДНЯ
//ЗАВТРА
//НА 4 ДНЯ ВПЕРЁД
$timeForecast = $words[1];
$coordsUrl = "https://nominatim.openstreetmap.org/search?email=victoria20011005@gmail.com&city=$city&format=json";

$dataCoords = json_decode(file_get_contents($coordsUrl), true);
if ($dataCoords === []) {
    sendMessage($chatId, "Не удалось найти информацию для $city :(");
    die();
}
$lat = $dataCoords[0]['lat'];
$lon = $dataCoords[0]['lon'];

$dataWeather = json_decode(file_get_contents("http://api.openweathermap.org/data/2.5/onecall?lat=$lat&lon=$lon&lang=ru&units=metric&appid=" . OMW_API_KEY), true);

$forecastResponse = '';

switch ($timeForecast) {
    case "сейчас":
        $forecastResponse .= buildForecastMessageForNow($dataWeather, $city);
        break;
    case "сегодня":
        $forecastResponse .= buildForecastMessageForToday($dataWeather, $city);
        break;
    case "завтра":
        $forecastResponse .= buildForecastMessageForTomorrow($dataWeather, $city);
        break;
    case "на 4 дня":
        $forecastResponse .= buildForecastMessageFor4Days($dataWeather, $city);

}

sendMessage($chatId, $forecastResponse);

function sendMessage(int $chatId, string $message)
{
    $data['chat_id'] = $chatId;
    $data['text'] = $message;
    sendRequestToTg($data, 'sendMessage');
}

function sendRequestToTg(array $data, string $method)
{
    $request = TG_API_URL . TG_API_KEY . "/$method" . "?" . http_build_query($data);
    return file_get_contents($request);
}

function buildForecastMessagePart(array $moment): string
{
    $buildedForecast = '';
    $buildedForecast .= "{$moment['weather'][0]['description']}\n";
    if (is_array($moment['temp'])) {
        $buildedForecast .= "Температура с утра: {$moment['temp']['morn']}℃\n";
        $buildedForecast .= "Температура днём: {$moment['temp']['day']}℃\n";
        $buildedForecast .= "Температура вечером: {$moment['temp']['eve']}℃\n";
    } else {
        $buildedForecast .= "Температура: {$moment['temp']}℃\n";
        $buildedForecast .= "Чувствуется как: {$moment['feels_like']}℃\n";
    }
    $buildedForecast .= "Ветер: {$moment['wind_speed']} м/с\n";
    $buildedForecast .= "Влажность: {$moment['humidity']}%\n";
    return $buildedForecast;
}

function buildForecastMessageForNow(array $rawForecastData, string $city): string
{
    $responseForecastMessage = "Погода в $city сейчас:\n" . FORMATTING_HORIZONTAL_LINE . "\n";
    $responseForecastMessage .= buildForecastMessagePart($rawForecastData['current']);
    return $responseForecastMessage;
}

function buildForecastMessageByIndexesOnForecastData(array $indexes, array $data): Generator
{
    foreach ($indexes as $i) {
        yield buildForecastMessagePart($data[$i]);
    }
}

function buildForecastMessageForToday(array $rawForecastData, string $city): string
{
    $responseForecastMessage = "Погода в $city через 2, 6 и 12 часов:\n" . FORMATTING_HORIZONTAL_LINE . "\n";
    $hours = [2, 6, 12];
    foreach (buildForecastMessageByIndexesOnForecastData($hours, $rawForecastData['hourly']) as $forecastForHour) {
        $responseForecastMessage .= $forecastForHour . FORMATTING_HORIZONTAL_LINE . "\n";
    }
    return $responseForecastMessage;
}

function buildForecastMessageForTomorrow(array $rawForecastData, string $city): string
{
    $responseForecastMessage = "Погода в $city на завтра:\n" . FORMATTING_HORIZONTAL_LINE . "\n";
    foreach (buildForecastMessageByIndexesOnForecastData([1], $rawForecastData['hourly']) as $forecastForDay) {
        $responseForecastMessage .= $forecastForDay . FORMATTING_HORIZONTAL_LINE . "\n";
    }
    return $responseForecastMessage;
}

function buildForecastMessageFor4Days(array $rawForecastData, string $city): string
{
    $responseForecastMessage = "Погода в $city на 4 следующих дня:\n" . FORMATTING_HORIZONTAL_LINE . "\n";
    $days = [1, 2, 3, 4];
    foreach (buildForecastMessageByIndexesOnForecastData($days, $rawForecastData['daily']) as $forecastForDay) {
        $responseForecastMessage .= $forecastForDay . FORMATTING_HORIZONTAL_LINE . "\n";
    }
    return $responseForecastMessage;
}

file_put_contents("logCoords.txt", print_r($dataWeather, true) . "\n");


