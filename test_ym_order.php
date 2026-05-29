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

foreach ($items as $i => $it) {
    echo "--- Товар #" . ($i + 1) . " ---\n";
    echo "  offerName: " . ($it['offerName'] ?? 'N/A') . "\n";
    echo "  count: " . ($it['count'] ?? 'N/A') . "\n";
    echo "  price: " . ($it['price'] ?? 'N/A') . "\n";
    echo "  buyerPrice: " . ($it['buyerPrice'] ?? 'N/A') . "\n";
    echo "\n";
}

// 6. Сравнение способов получения суммы
echo "=== СПОСОБЫ ПОЛУЧЕНИЯ СУММЫ ===\n";

// Способ 1: Старый (price * count)
echo "1. Старый способ (price * count):\n";
$oldSum = 0;
foreach ($items as $it) {
    $oldSum += (float)($it['price'] ?? 0) * (int)($it['count'] ?? 1);
}
echo "   Результат: {$oldSum} руб.\n\n";

// Способ 2: buyerTotal (только то, что платит покупатель)
echo "2. buyerTotal (что платит покупатель):\n";
$buyerTotal = (float)($targetOrder['buyerTotal'] ?? 0);
echo "   buyerTotal: {$buyerTotal} руб.\n\n";

// Способ 3: subsidies (субсидии от Яндекс Маркета)
echo "3. Субсидии от Яндекс Маркета:\n";
$subsidiesTotal = 0;
$subsidies = $targetOrder['subsidies'] ?? [];
foreach ($subsidies as $sub) {
    $subsidiesTotal += (float)($sub['amount'] ?? 0);
    echo "   - {$sub['type']}: {$sub['amount']} руб.\n";
}
echo "   Итого субсидий: {$subsidiesTotal} руб.\n\n";

// Способ 4: ПРАВИЛЬНЫЙ (buyerTotal + subsidies)
echo "4. ПРАВИЛЬНЫЙ СПОСОБ (buyerTotal + subsidies):\n";
$correctSum = $buyerTotal + $subsidiesTotal;
echo "   {$buyerTotal} + {$subsidiesTotal} = {$correctSum} руб.\n";
echo "   Это должно совпадать с ценой в интерфейсе ЯМ!\n\n";

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
