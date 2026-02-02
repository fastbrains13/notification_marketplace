<?php
function getOzonOrders($ozon) {
    $url = 'https://api-seller.ozon.ru/v3/posting/fbs/unfulfilled/list';

    $headers = [
        "Client-Id: {$ozon['client_id']}",
        "Api-Key: {$ozon['api_key']}",
        "Content-Type: application/json"
    ];

    $body = json_encode([
        'dir' => 'ASC',
        'filter' => [
            'status' => 'awaiting_packaging',
            'cutoff_from' => date('c', strtotime('-2 days'))
        ],
        'limit' => 50,
        'offset' => 0,
        'with' => [
            'analytics_data' => true,
            'barcodes' => true
        ]
    ]);

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    curl_setopt($ch, CURLOPT_POST, true);

    $response = curl_exec($ch);
    curl_close($ch);

    return json_decode($response, true);
}

function getWbOrders($wb) {
    $url = 'https://marketplace-api.wildberries.ru/api/v3/orders/new?limit=100';

    $headers = [
        "Authorization: {$wb['token']}",
        "Content-Type: application/json"
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');

    $response = curl_exec($ch);
    curl_close($ch);

    return json_decode($response, true);
}

function getYandexOrders($ya) {
    $url = "https://api.partner.market.yandex.ru/campaigns/{$ya['campaign_id']}/orders.json?status=PROCESSING";

    $headers = [
        "Authorization: Bearer {$ya['oauth_token']}",
        "Content-Type: application/json"
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
    $response = curl_exec($ch);
    curl_close($ch);

    return json_decode($response, true);
}

function loadStorage() {
    $path = __DIR__ . '/storage.json';
    if (!file_exists($path)) {
        return [];
    }
    $json = file_get_contents($path);
    return json_decode($json, true);
}

function saveStorage($storage) {
    $path = __DIR__ . '/storage.json';
    file_put_contents($path, json_encode($storage, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

function sendTelegram($chat_id, $message) {
    $token = '7455328246:AAFpjel_xkY5ISkJtBv61vKT-VbyBrQH3aE';
    $url = "https://api.telegram.org/bot{$token}/sendMessage?chat_id={$chat_id}&text=" . urlencode($message) . "&parse_mode=HTML";
    file_get_contents($url);
}

function processOrders($orders, $platform, $storeName, $chat_id) {
    foreach ($orders as $order) {
        $msg = "🧪 Магазин: {$storeName}
";
        $msg .= "🆕 Новый заказ [{$platform}]
";
        $msg .= "📦 Список товаров:
";

        if ($platform === 'yandex' && isset($order['items'])) {
            foreach ($order['items'] as $item) {
                $name = $item['offerName'] ?? 'Неизвестный товар';
                $qty = $item['count'] ?? 1;
                $msg .= "- {$name}: {$qty} шт
";
            }
        } elseif ($platform === 'wildberries') {
            $nmId = $order['nmId'] ?? 'неизв.';
            $qty = 1;
            $msg .= "- Товар #{$nmId}: {$qty} шт (остаток: неизвестно)
";
        } else {
            $msg .= "- товаров нет
";
        }

        $price = $order['salePrice'] ?? 0;
        $msg .= "💰 Сумма заказа: {$price} ₽";

        sendTelegram($chat_id, $msg);
    }
}

function wbRubFromSalePrice($salePrice) {
    if ($salePrice === null || !is_numeric($salePrice)) return 0.0;
    return round(((float)$salePrice) / 100, 2);
}

function formatRub($amount) {
    if (abs($amount - round($amount)) < 0.001) {
        return number_format((int)round($amount), 0, '.', ' ');
    }
    return number_format($amount, 2, '.', ' ');
}

function formatYandexMessage($storeName, $order, $id, $yaConfig) {
    $lines = [];
    $lines[] = "🚕 Новый заказ [Yandex Market]";
    $lines[] = "📃 Заказ №{$id}";
    $lines[] = "📦 Список товаров:";

    foreach ($order['items'] as $item) {
        $name = $item['offerName'] ?? 'Товар';
        $qty = $item['count'] ?? 1;
        $sku = $item['shopSku'] ?? null;
        $stock = getYandexStock($yaConfig, $sku);
        $lines[] = "- {$name}: {$qty} шт (остаток: {$stock})";
    }

    $sum = $order['buyerTotal'] ?? 0;
    $sum = $sum + $order['subsidies']['amount'];
    $lines[] = "💰 Сумма заказа: {$sum} ₽";

    return implode("\n", $lines);
}

function getYandexStock($yaConfig, $sku) {
    if (!$sku) return "?";
    $url = "https://api.partner.market.yandex.ru/campaigns/{$yaConfig['campaign_id']}/offers/stock.json?offerIds[]=$sku";
    $headers = [
        'Authorization: Bearer ' . $yaConfig['oauth_token'],
        'Content-Type: application/json',
        'Accept: application/json'
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    $response = curl_exec($ch);
    if ($response === false) {
        error_log("Failed to fetch Yandex stock for SKU: {$sku}");
        return "?";
    }
    curl_close($ch);

    $res = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE || !isset($res['result']['stocks'][0]['count'])) {
        error_log("Invalid response for Yandex stock: " . json_last_error_msg());
        return "?";
    }
    return $res['result']['stocks'][0]['count'];
}

function formatOzonMessage($storeName, $posting, $id, $ozonConfig) {
    $lines = [];
    $lines[] = "🚕 Новый заказ [Ozon]";
    $lines[] = "📃 Заказ №{$id}";
    $lines[] = "📦 Список товаров:";
    
    $sum = 0;

    foreach ($posting['products'] as $product) {
        $name = $product['name'] ?? 'Товар';
        $qty = $product['quantity'] ?? 1;
        $sku = $product['sku'] ?? null;
        $stock = getOzonStock($ozonConfig, $sku);
        $lines[] = "- {$name}: {$qty} шт (остаток: {$stock})";
        $sum = $sum + $product['price'];
    }

    //$sum = $posting['price'] ?? 0;
    $lines[] = "💰 Сумма заказа: {$sum} ₽";

    return implode("\n", $lines);
}


function getOzonStock($ozonConfig, $sku) {
    if (!$sku) return "?";
    $data = ["sku" => [$sku]];
    $response = apiPost('https://api-seller.ozon.ru/v1/product/info/stocks-by-warehouse/fbs', $ozonConfig, $data);
    if (!$response || !isset($response['result'][0]['present'])) {
        error_log("Failed to fetch Ozon stock for SKU: {$sku}");
        return "?";
    }
    return $response['result'][0]['present'];
}

function formatWbMessage($storeName, $order, $id, $wbConfig) {
    $lines = [];
    $lines[] = "🧪 Магазин: {$storeName}";
    $lines[] = "🆕 Новый заказ [Wildberries]";
    $lines[] = "📃 Заказ №{$id}";
    $lines[] = "📦 Список товаров:";

    $nmId = $order['nmId'] ?? null;

    // в WB FBS обычно 1, но на всякий случай:
    $qty = (int)($order['quantity'] ?? $order['count'] ?? 1);
    if ($qty < 1) $qty = 1;

    $stock = $nmId ? getWbStock($wbConfig, $nmId) : '?';

    // TODO: можно добавить название через Content API
    $lines[] = "- {$nmId}: {$qty} шт (остаток: {$stock})";

    // ВАЖНО: salePrice в копейках
    $salePrice = $order['salePrice'] ?? null;
    $sum = wbRubFromSalePrice($salePrice) * $qty;

    $lines[] = "💰 Сумма заказа: " . formatRub($sum) . " ₽";

    return implode("\n", $lines);
}

/*function formatWbMessage($storeName, $order, $id, $wbConfig) {
    $lines = [];
    $lines[] = "🚕 Новый заказ [Wildberries]";
    $lines[] = "📃 Заказ №{$id}";
    $lines[] = "📦 Список товаров:";

    $nmId = $order['nmId'] ?? null;
    $qty = 1;
    $stock = getWbStock($wbConfig, $nmId);
    $lines[] = "- Товар #{$nmId}: {$qty} шт (остаток: {$stock})";

    //$sum = $order['salePrice'] ?? $order['price'] ?? 0;
     $sum = $order['convertedPrice'] / 10000;
    $lines[] = "💰 Сумма заказа: {$sum} ₽";

    return implode("\n", $lines);
}*/

function getWbStock($wbConfig, $nmId) {
    if (!$nmId) return "?";
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://marketplace-api.wildberries.ru/api/v3/stocks?nm=' . $nmId);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: ' . $wbConfig['token']]);
    $output = curl_exec($ch);
    if ($output === false) {
        error_log("Failed to fetch Wildberries stock for nmId: {$nmId}");
        return "?";
    }
    curl_close($ch);

    $res = json_decode($output, true);
    if (json_last_error() !== JSON_ERROR_NONE || !isset($res['stocks'][0]['amount'])) {
        error_log("Invalid response for Wildberries stock: " . json_last_error_msg());
        return "?";
    }
    return $res['stocks'][0]['amount'];
}

function apiPost($url, $config, $body) {
    $headers = [
        'Client-Id: ' . ($config['client_id'] ?? ''),
        'Api-Key: ' . ($config['api_key'] ?? ''),
        'Content-Type: application/json'
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    $response = curl_exec($ch);
    if ($response === false) {
        error_log("Failed to make POST request to {$url}");
        return null;
    }
    curl_close($ch);
    $decodedResponse = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log("Failed to decode JSON response: " . json_last_error_msg());
        return null;
    }
    return $decodedResponse;
}

?>
