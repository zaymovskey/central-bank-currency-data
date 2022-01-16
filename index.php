<?php
declare(strict_types = 1);

$dynamicUrl = 'http://www.cbr.ru/scripts/XML_dynamic.asp';
$currenciesCodesUrl = 'http://www.cbr.ru/scripts/XML_val.asp?d=0';

$ch = curl_init();
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

set_error_handler(function($errno, $errstr, $errfile, $errline) {
    if (0 === error_reporting()) {
        return false;
    }
    throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
});


function getCurrenciesCodesArray($ch, string $url): array
{
    curl_setopt($ch, CURLOPT_URL, $url);
    $currenciesCodesString = curl_exec($ch);
    $currenciesCodesXml = new SimpleXMLElement($currenciesCodesString);
    $currenciesCodesArray = [];
    foreach ($currenciesCodesXml as $tag) {
        $currenciesCodesArray += [(string) $tag->Name => (string) $tag->ParentCode];
    }
    return $currenciesCodesArray;
}

function getsCurrencyDynamicUrl(string $code, string $url): string
{
    $todayDateObj = new DateTime(date('m/d/Y', time()));
    $todayDateString = date_format($todayDateObj, 'd/m/Y');
    $daysAgoDateString = date_format($todayDateObj->modify(sprintf('-%d day', 90)), 'd/m/Y');

    $options = [
        'date_req1' => $daysAgoDateString,
        'date_req2' => $todayDateString,
        'VAL_NM_RQ' => $code
    ];

    return $url . '?' . urldecode(http_build_query($options));
}

function getCurrencyDynamicXml($ch, string $url): SimpleXMLElement
{
    curl_setopt($ch, CURLOPT_URL, $url);
    $currencyDynamicString = curl_exec($ch);
    return new SimpleXMLElement($currencyDynamicString);
}

function getCurrencyValuesArray(SimpleXMLElement $currencyDynamicXml): array
{
    $currencyValuesArray = [];
    foreach ($currencyDynamicXml as $tag) {
        $stringValue = (string) $tag->Value;
        $floatValue = (float) str_replace(',', '.', $stringValue);
        $currencyValuesArray += [(string) $tag->attributes()->Date => $floatValue];
    }
    return $currencyValuesArray;
}

$currenciesCodesArray = getCurrenciesCodesArray($ch, $currenciesCodesUrl);

foreach ($currenciesCodesArray as $name => $code) {
    $currencyDynamicUrl = getsCurrencyDynamicUrl($code, $dynamicUrl);
    $currencyDynamicXml = getCurrencyDynamicXml($ch, $currencyDynamicUrl);
    $currencyValuesArray = getCurrencyValuesArray($currencyDynamicXml);

    try {
        $maxValue = max($currencyValuesArray);
        $minValue = min($currencyValuesArray);
        $averageValue = array_sum($currencyValuesArray) / count($currencyValuesArray);
        $averageValueRuble = 1 / $averageValue;

        $maxValueDate = array_search($maxValue, $currencyValuesArray);
        $minValueDate = array_search($minValue, $currencyValuesArray);
    } catch (ErrorException $e) {
        echo $name.PHP_EOL;
        echo 'Нет данных'.PHP_EOL;
        echo ''.PHP_EOL;
        continue;
    }

    echo $name.PHP_EOL;
    echo 'Дата максимального значения: ' . $maxValueDate.PHP_EOL;
    echo 'Максимальное значение: ' . $maxValue.PHP_EOL;
    echo 'Дата минимального значения: ' . $minValueDate.PHP_EOL;
    echo 'Минимальное значение: ' . $minValue.PHP_EOL;
    echo 'Среднее значение курса рубля к текущей валюте: ' . $averageValueRuble.PHP_EOL;
    echo ''.PHP_EOL;
}

curl_close($ch);
