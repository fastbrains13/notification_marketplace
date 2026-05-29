<?php
/**
 * Тестовый скрипт для проверки заказа Яндекс Маркета
 * Выводит сырой ответ API и распарсенные данные
 */
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

$orderId = '57602776064'; // ID заказа для проверки
$storeName = 'AquaCam';

echo "=== Тест заказа Яндекс Маркет ===\n\n";

// Получаем credentials
$ym = $config['stores'][$storeName]['yandex'] ?? null;
if (!$ym) {
    die("Не найдены credentials для Yandex Market в магазине {$storeName}\n");
}

$campaignId = $ym['campaign_id'];
$token = $ym['oauth_token'];

// 1. Запрос списка заказов в статусе PROCESSING
echo "1. Запрашиваем заказы в статусе PROCESSING...\n";
$url = "https://api.partner.market.yandex.ru/campaigns/{$campaignId}/orders.json?status=PROCESSING";
$headers = [
    "Authorization: Bearer {$token}",
    'Content-Type: application/json'
];

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 20,
    CURLOPT_HTTPHEADER => $headers,
    CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
]);
$res = curl_exec($ch);
$errno = curl_errno($ch);
$err = curl_error($ch);
$code = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
curl_close($ch);

if ($errno !== 0) {
    die("cURL error: {$errno} {$err}\n");
}

echo "HTTP код: {$code}\n\n";

$data = json_decode($res, true);
if (!$data) {
    die("Не удалось декодировать JSON:\n{$res}\n");
}

// 2. Ищем нужный заказ
$orders = $data['orders'] ?? ($data['result']['orders'] ?? []);
$targetOrder = null;

foreach ($orders as $o) {
    if ((string)($o['id'] ?? '') === $orderId) {
        $targetOrder = $o;
        break;
    }
}

if (!$targetOrder) {
    echo "Заказ #{$orderId} не найден в списке PROCESSING.\n";
    echo "Всего найдено заказов: " . count($orders) . "\n";
    if ($orders) {
        echo "Найденные ID заказов:\n";
        foreach ($orders as $o) {
            echo "  - " . ($o['id'] ?? 'unknown') . "\n";
        }
    }
    echo "\nПопробуем запросить конкретный заказ напрямую...\n\n";
    
    // 3. Запрос конкретного заказа по ID
    $urlDirect = "https://api.partner.market.yandex.ru/campaigns/{$campaignId}/orders/{$orderId}.json";
    $ch = curl_init($urlDirect);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
    ]);
    $res = curl_exec($ch);
    $errno = curl_errno($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);
    
    if ($errno !== 0) {
        die("cURL error при прямом запросе: {$errno}\n");
    }
    
    echo "HTTP код (прямой запрос): {$code}\n";
    $data = json_decode($res, true);
    $targetOrder = $data['order'] ?? null;
}

if (!$targetOrder) {
    die("Заказ #{$orderId} не найден.\n");
}

// 4. Выводим сырую структуру заказа
echo "\n=== СЫРОЙ ОТВЕТ API (заказ #{$orderId}) ===\n";
echo json_encode($targetOrder, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";

// 5. Парсим товары и выводим детали
echo "\n=== АНАЛИЗ СТРУКТУРЫ ===\n";
$items = $targetOrder['items'] ?? [];
echo "Количество товаров: " . count($items) . "\n\n";

$totalSum = 0;
foreach ($items as $i => $it) {
    echo "--- Товар #" . ($i + 1) . " ---\n";
    echo "  offerName: " . ($it['offerName'] ?? 'N/A') . "\n";
    echo "  count: " . ($it['count'] ?? 'N/A') . "\n";
    
    // Старый способ (price)
    $oldPrice = $it['price'] ?? 'N/A';
    echo "  price (старое поле): " . $oldPrice . "\n";
    
    // Новый способ (prices.payment.value)
    $paymentValue = $it['prices']['payment']['value'] ?? 'N/A';
    echo "  prices.payment.value: " . $paymentValue . "\n";
    
    // Другие поля prices
    if (isset($it['prices'])) {
        echo "  Вся структура prices:\n";
        echo "    " . json_encode($it['prices'], JSON_UNESCAPED_UNICODE) . "\n";
    }
    
    if (is_numeric($paymentValue)) {
        $totalSum += (float)$paymentValue;
    }
    echo "\n";
}

// 6. Итог
echo "=== ИТОГО ===\n";
echo "Сумма по старому методу (price * count): ";
$oldSum = 0;
foreach ($items as $it) {
    $oldSum += (float)($it['price'] ?? 0) * (int)($it['count'] ?? 1);
}
echo "{$oldSum} руб.\n";

echo "Сумма по новому методу (prices.payment.value): {$totalSum} руб.\n";

// 7. Тестируем функцию ym_parse_orders
echo "\n=== ТЕСТ ФУНКЦИИ ym_parse_orders ===\n";
$parsed = ym_parse_orders(['orders' => [$targetOrder]]);
if ($parsed) {
    $p = $parsed[0];
    echo "ID: {$p['id']}\n";
    echo "Сумма: {$p['sum']} руб.\n";
    echo "Товары:\n{$p['items_bullets']}\n";
}

echo "\n=== ГОТОВОЕ СООБЩЕНИЕ ===\n";
if ($parsed) {
    echo build_message($storeName, 'Yandex Market', $parsed[0]) . "\n";
}
