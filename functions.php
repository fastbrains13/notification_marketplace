<?php
declare(strict_types=1);

const LOG_VERBOSE = true;
const DEFAULT_LOOKBACK_SECONDS = 600; // 10 minutes for first run

function dbg(string $s): void {
    if (!LOG_VERBOSE) return;
    $dir = __DIR__ . '/storage';
    if (!is_dir($dir)) { @mkdir($dir, 0775, true); }
    $line = '['.date('Y-m-d H:i:s').'] '.$s.PHP_EOL;
    @file_put_contents($dir.'/debug.log', $line, FILE_APPEND);
}

// ---- Telegram ----
function tg_send(string $token, string $chatId, string $text): array {
    $url = "https://api.telegram.org/bot{$token}/sendMessage";
    $payload = [
        'chat_id' => $chatId,
        'text' => $text,
        'parse_mode' => 'HTML',
        'disable_web_page_preview' => true,
    ];
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 12,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
    ]);
    $res = curl_exec($ch);
    $errno = curl_errno($ch);
    $err  = curl_error($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);

    if ($errno !== 0) {
        $e = "Telegram cURL error: {$errno} {$err}";
        dbg($e);
        return ['ok' => false, '_http_code' => $code, 'error' => $e];
    }
    $decoded = json_decode((string)$res, true);
    if (!is_array($decoded)) {
        $e = "Telegram decode error: ".substr((string)$res, 0, 500);
        dbg($e);
        return ['ok' => false, '_http_code' => $code, 'error' => 'Bad Telegram JSON'];
    }
    $decoded['_http_code'] = $code;
    return $decoded;
}

// ---- Storage (dedup + last_run) ----
function storage_path(): string {
    $dir = __DIR__ . '/storage';
    if (!is_dir($dir)) { @mkdir($dir, 0775, true); }
    $file = rtrim($dir, '/').'/sent.json';
    if (!file_exists($file)) { file_put_contents($file, json_encode(['sent'=>[], 'last_run'=>[]], JSON_UNESCAPED_UNICODE)); }
    return $file;
}
function storage_load(string $file): array {
    $raw = @file_get_contents($file);
    $data = json_decode((string)$raw, true);
    if (!is_array($data)) { $data = ['sent'=>[], 'last_run'=>[]]; }
    if (!isset($data['sent']) || !is_array($data['sent'])) $data['sent'] = [];
    if (!isset($data['last_run']) || !is_array($data['last_run'])) $data['last_run'] = [];
    return $data;
}
function storage_save(string $file, array $data): void {
    file_put_contents($file, json_encode($data, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT));
}
function make_key(string $store, string $market, string $orderId): string {
    return $store.'|'.$market.'|'.$orderId;
}
function last_run_get(array $storage, string $store, string $market): int {
    return (int)($storage['last_run'][$store][$market] ?? 0);
}
function last_run_set(array &$storage, string $store, string $market, int $ts): void {
    if (!isset($storage['last_run'][$store])) $storage['last_run'][$store] = [];
    $storage['last_run'][$store][$market] = $ts;
}

// ---- cURL helper with IPv4 + DNS fallback ----
function http_json(string $url, array $options = [], array $headers = [], ?array $body = null): ?array {
    $ch = curl_init($url);
    $opts = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
    ];
    // DNS fallback
    $host = parse_url($url, PHP_URL_HOST);
    $port = parse_url($url, PHP_URL_PORT) ?: 443;
    if ($host) {
        $ip = gethostbyname($host);
        if ($ip && $ip !== $host) {
            $opts[CURLOPT_RESOLVE] = ["{$host}:{$port}:{$ip}"];
        }
    }
    if ($body !== null) {
        $opts[CURLOPT_POST] = true;
        $opts[CURLOPT_POSTFIELDS] = json_encode($body, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        $headers[] = 'Content-Type: application/json';
        $opts[CURLOPT_HTTPHEADER] = $headers;
    }
    if ($options) $opts = $options + $opts;
    curl_setopt_array($ch, $opts);
    $res = curl_exec($ch);
    $errno = curl_errno($ch);
    $err  = curl_error($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);
    if ($errno !== 0) {
        dbg("HTTP error {$errno} {$err} for {$url}");
        return null;
    }
    if ($code >= 400) {
        dbg("HTTP {$code} for {$url}: ".substr((string)$res, 0, 500));
    }
    $decoded = json_decode((string)$res, true);
    if (!is_array($decoded)) {
        dbg("Bad JSON from {$url}: ".substr((string)$res, 0, 500));
        return null;
    }
    return $decoded;
}

// ---- Helpers for items formatting ----
function items_to_bullets(array $items): string {
    if (!$items) return '';
    $lines = [];
    foreach ($items as $it) {
        $lines[] = '- ' . $it;
    }
    return implode("\n", $lines);
}

// ---- OZON ----
function ozon_list_since(array $ozon, int $sinceTs, ?string $status = null): ?array {
    $url = 'https://api-seller.ozon.ru/v3/posting/fbs/list';
    $headers = [
        'Client-Id: '.$ozon['client_id'],
        'Api-Key: '.$ozon['api_key'],
        'Content-Type: application/json'
    ];
    $from = date('c', $sinceTs);
    $to   = date('c'); // now
    $filter = [
        'since' => $from,
        'to'    => $to,
    ];
    if ($status) $filter['status'] = $status;
    $body = [
        'dir' => 'ASC',
        'filter' => $filter,
        'limit' => 100,
        'offset' => 0,
        'with' => [
            'analytics_data' => true,
            'barcodes' => false,
            'financial_data' => false,
        ],
    ];
    dbg("OZON since request: ".json_encode($body));
    return http_json($url, [], $headers, $body);
}

function ozon_parse_orders(array $resp): array {
    $out = [];
    $postings = $resp['result']['postings'] ?? [];
    if (!is_array($postings)) $postings = [];
    foreach ($postings as $p) {
        $orderId = (string)($p['posting_number'] ?? '');
        $sum = 0.0;
        $items = $p['products'] ?? [];
        $parts = [];
        if (is_array($items)) {
            foreach ($items as $prod) {
                $price = (float)($prod['price'] ?? 0);
                $qty = (int)($prod['quantity'] ?? 1);
                $sum += $price * $qty;
                $name = (string)($prod['name'] ?? 'Товар');
                $parts[] = htmlspecialchars("{$name}: {$qty} шт");
            }
        }
        $out[] = [
            'id' => $orderId,
            'sum' => $sum,
            'items_bullets' => items_to_bullets($parts),
        ];
    }
    dbg("OZON parsed ".count($out)." orders");
    return $out;
}

// ---- Wildberries ----
function wb_list_new(array $wb): ?array {
    $url = 'https://marketplace-api.wildberries.ru/api/v3/orders/new?limit=100';
    $headers = [
        'Authorization: '.$wb['token'],
        'Content-Type: application/json'
    ];
    dbg("WB new request");
    return http_json($url, [], $headers, null);
}

function wb_parse_orders(array $resp): array {
    $out = [];
    $orders = $resp['orders'] ?? null;
    if (is_array($orders)) {
        foreach ($orders as $o) {
            $id = (string)($o['id'] ?? $o['orderId'] ?? '');
            $rawSum = (float)($o['totalPrice'] ?? $o['convertedPrice'] ?? 0);
            $sum = $rawSum > 0 ? $rawSum / 100 : 0;
            $name = (string)($o['article'] ?? $o['supplierArticle'] ?? 'Товар');
            $qty  = (int)($o['quantity'] ?? 1);
            $parts = [ htmlspecialchars("{$name}: {$qty} шт") ];
            $out[] = ['id'=>$id, 'sum'=>$sum, 'items_bullets'=>items_to_bullets($parts)];
        }
    }
    dbg("WB parsed ".count($out)." orders");
    return $out;
}

// Optionally acknowledge WB new orders (requires array of ids)
// removed ack
function wb_acknowledge_disabled(array $wb, array $orderIds): ?array {
    if (empty($wb['ack'])) return null; // toggle by config flag
    if (!$orderIds) return null;
    $url = 'https://marketplace-api.wildberries.ru/api/v3/orders/acknowledge';
    $headers = [
        'Authorization: '.$wb['token'],
        'Content-Type: application/json'
    ];
    $body = ['orders' => array_map(fn($id)=>['id'=>(int)$id], $orderIds)];
    dbg("WB acknowledge: ".json_encode($body));
    return http_json($url, [], $headers, $body);
}

// ---- Yandex Market (Bearer + .json + status=PROCESSING) ----
function ym_list_processing(array $ym): ?array {
    $campaignId = $ym['campaign_id'];
    $url = "https://api.partner.market.yandex.ru/campaigns/{$campaignId}/orders.json?status=PROCESSING";
    $headers = [
        "Authorization: Bearer {$ym['oauth_token']}",
        'Content-Type: application/json'
    ];
    dbg("YM processing request (Bearer, .json, status=PROCESSING)");
    return http_json($url, [], $headers, null);
}

function ym_parse_orders(array $resp): array {
    $out = [];
    $orders = $resp['orders'] ?? ($resp['result']['orders'] ?? null);
    if (is_array($orders)) {
        foreach ($orders as $o) {
            $id = (string)($o['id'] ?? '');
            $items = $o['items'] ?? [];
            // Сумма заказа для продавца = buyerTotal + subsidies
            // buyerTotal — то, что платит покупатель
            // subsidies — субсидии от Яндекс Маркета (скидки, которые компенсирует ЯМ)
            $sum = (float)($o['buyerTotal'] ?? 0);
            $subsidies = $o['subsidies'] ?? [];
            foreach ($subsidies as $sub) {
                $sum += (float)($sub['amount'] ?? 0);
            }
            $parts = [];
            foreach ($items as $it) {
                $qty = (int)($it['count'] ?? 1);
                $name = (string)($it['offerName'] ?? 'Товар');
                $parts[] = htmlspecialchars("{$name}: {$qty} шт");
            }
            $out[] = ['id'=>$id, 'sum'=>$sum, 'items_bullets'=>items_to_bullets($parts)];
        }
    }
    dbg("YM parsed ".count($out)." orders");
    return $out;
}

// ---- Message builder (old style restored) ----
function build_message(string $store, string $market, array $order): string {
    $sumFmt = number_format((float)($order['sum'] ?? 0), 0, '.', ' ');
    $itemsBullets = $order['items_bullets'] ?? '';
    $id = htmlspecialchars((string)$order['id']);
    return "📅 Магазин: {$store}\n"
         . "🚕 Новый заказ [{$market}]\n"
         . "📃 Заказ №{$id}\n"
         . "📦 Список товаров:\n"
         . ($itemsBullets ? "{$itemsBullets}\n" : '')
         . "💰 Сумма заказа: {$sumFmt} ₽";
}

// ---- Main orchestrator ----
function poll_and_notify(array $config): array {
    $sentFile = storage_path();
    $storage = storage_load($sentFile);
    $results = ['sent'=>0,'skipped'=>0,'errors'=>[], 'markets'=>[]];
    $now = time();

    $token = $config['bot_token'];
    $chat  = $config['telegram_chat_id'];

    foreach ($config['stores'] as $storeName => $creds) {
        dbg("== STORE {$storeName} ==");

        // OZON — only new since last run (no historical sweep)
        if (!empty($creds['ozon'])) {
            $sinceTs = last_run_get($storage, $storeName, 'ozon');
            if ($sinceTs <= 0) $sinceTs = $now - DEFAULT_LOOKBACK_SECONDS; // first run safety
            $statuses = ['awaiting_packaging','awaiting_deliver','acceptance_in_progress'];
            $orders = [];
            foreach ($statuses as $st) {
                $resp = ozon_list_since($creds['ozon'], $sinceTs, $st);
                if (is_array($resp)) { $orders = array_merge($orders, ozon_parse_orders($resp)); }
            }
            // dedup by id
            $uniq = [];
            foreach ($orders as $o) { $uniq[$o['id']] = $o; }
            $orders = array_values($uniq);

            foreach ($orders as $ord) {
                if (empty($ord['id'])) continue;
                $key = make_key($storeName, 'ozon', $ord['id']);
                if (isset($storage['sent'][$key])) { $results['skipped']++; continue; }
                $msg = build_message($storeName, 'Ozon', $ord);
                $tg = tg_send($token, $chat, $msg);
                if (!empty($tg['ok'])) {
                    $storage['sent'][$key] = ['ts'=>$now];
                    $results['sent']++;
                } else {
                    $results['errors'][] = ['market'=>'ozon','id'=>$ord['id'],'error'=>$tg['error'] ?? 'unknown'];
                }
            }
            $results['markets']['ozon'] = ($results['markets']['ozon'] ?? 0) + count($orders);
            last_run_set($storage, $storeName, 'ozon', $now);
        }

        // WB — v3 new; (optional) acknowledge if enabled
        if (!empty($creds['wildberries'])) {
            $resp = wb_list_new($creds['wildberries']);
            $orders = is_array($resp) ? wb_parse_orders($resp) : [];
            $wbIds = [];
            foreach ($orders as $ord) {
                if (empty($ord['id'])) continue;
                $key = make_key($storeName, 'wb', $ord['id']);
                if (isset($storage['sent'][$key])) { $results['skipped']++; continue; }
                $msg = build_message($storeName, 'Wildberries', $ord);
                $tg = tg_send($token, $chat, $msg);
                if (!empty($tg['ok'])) {
                    $storage['sent'][$key] = ['ts'=>$now];
                    $results['sent']++;
                    $wbIds[] = $ord['id'];
                } else {
                    $results['errors'][] = ['market'=>'wb','id'=>$ord['id'],'error'=>$tg['error'] ?? 'unknown'];
                }
            }
            // acknowledge if enabled
            // ack removed
            $results['markets']['wb'] = ($results['markets']['wb'] ?? 0) + count($orders);
            last_run_set($storage, $storeName, 'wb', $now);
        }

        // Yandex — PROCESSING only (as requested)
        if (!empty($creds['yandex'])) {
            $resp = ym_list_processing($creds['yandex']);
            $orders = is_array($resp) ? ym_parse_orders($resp) : [];
            foreach ($orders as $ord) {
                if (empty($ord['id'])) continue;
                $key = make_key($storeName, 'ym', $ord['id']);
                if (isset($storage['sent'][$key])) { $results['skipped']++; continue; }
                $msg = build_message($storeName, 'Yandex Market', $ord);
                $tg = tg_send($token, $chat, $msg);
                if (!empty($tg['ok'])) {
                    $storage['sent'][$key] = ['ts'=>$now];
                    $results['sent']++;
                } else {
                    $results['errors'][] = ['market'=>'ym','id'=>$ord['id'],'error'=>$tg['error'] ?? 'unknown'];
                }
            }
            $results['markets']['ym'] = ($results['markets']['ym'] ?? 0) + count($orders);
            last_run_set($storage, $storeName, 'ym', $now);
        }
    }

    // GC old keys after 14 days
    $ttl = 14 * 86400;
    foreach ($storage['sent'] as $k => $meta) {
        if (!empty($meta['ts']) && ($now - (int)$meta['ts']) > $ttl) {
            unset($storage['sent'][$k]);
        }
    }
    storage_save($sentFile, $storage);
    dbg("SUMMARY ".json_encode($results, JSON_UNESCAPED_UNICODE));
    return $results;
}
