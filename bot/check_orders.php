<?php


require_once __DIR__ . '/config.php';
// require_once __DIR__ . '/functions.php';

 require_once __DIR__ . '/functions_final_with_process.php';

$storage = loadStorage();
$now = date('Y-m-d H:i:s');
$log = [];

//$log[] = "Time ; ".date('H:m');
//$log[] = "Time : ".time();

if (!isset($config['bot_token'], $config['telegram_chat_id'], $config['stores'])) {
    die("Ошибка: Отсутствуют обязательные ключи в конфигурации.");
}

$emojiFolder = "\u{1F4C2}";
$emojiCalendar = "\u{1F4C5}";

// Refactor API response validation into a reusable function
function validateApiResponse($response, $requiredKey) {
    return is_array($response) && isset($response[$requiredKey]);
}

// Improve Telegram API error handling with curl
function sendTelegramMessage($url) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    $error = curl_error($ch);
    curl_close($ch);

    if ($response === false) {
        return ['ok' => false, 'error' => $error];
    }

    return json_decode($response, true);
}

// Add file locking to saveStorage
function saveStorageWithLock($storage, $path) {
    $fp = fopen($path, 'c+');
    if (flock($fp, LOCK_EX)) {
        ftruncate($fp, 0);
        fwrite($fp, json_encode($storage));
        fflush($fp);
        flock($fp, LOCK_UN);
    }
    fclose($fp);
}



foreach ($config['stores'] as $storeName => $storeData) {
    $log[] = "\n=== $emojiFolder Магазин: $storeName ===";
    $messages = [];

    if (!isset($storage[$storeName])) {
        $storage[$storeName] = [
            'processed' => [
                'ozon' => [],
                'wildberries' => [],
                'yandex' => []
            ],
            'history' => []
        ];
        $log[] = "Создана структура хранилища";
    }

    // OZON
    if (!empty($storeData['ozon'])) {
        var_dump($storeData['ozon']);
        $ozonResult = getOzonOrders($storeData['ozon']);
        var_dump($ozonResult);
        $log[] = "Ozon API ответ: " . json_encode($ozonResult);
        if (!validateApiResponse($ozonResult, 'result')) {
            $log[] = "Ошибка: Неверный ответ от Ozon API.";
        } else {
            if (!empty($ozonResult['result']['postings'])) {
                foreach ($ozonResult['result']['postings'] as $posting) {
                    $id = $posting['posting_number'];
                    if (!in_array($id, $storage[$storeName]['processed']['ozon'])) {
                        $msg = formatOzonMessage($storeName, $posting, $id, $storeData['ozon']);
                        $messages[] = $msg;
                        $log[] = "Новый заказ Ozon: $id";
                        $storage[$storeName]['processed']['ozon'][] = $id;
                        $storage[$storeName]['history'][] = ['id' => $id, 'time' => $now, 'source' => 'ozon'];
                    }
                }
            }
        }
    }

    // WB
    if (!empty($storeData['wildberries'])) {
        $wbResult = getWbOrders($storeData['wildberries']);
        $log[] = "WB API ответ: " . json_encode($wbResult);
        if (!validateApiResponse($wbResult, 'orders')) {
            $log[] = "Ошибка: Неверный ответ от WB API.";
        } else {
            $invalidWbOrders = 0;
            if (!empty($wbResult['orders'])) {
                foreach ($wbResult['orders'] as $order) {
                    $id = $order['id'] ?? $order['rid'] ?? null;
                    if ($id && !in_array($id, $storage[$storeName]['processed']['wildberries'])) {
                        $msg = formatWbMessage($storeName, $order, $id, $storeData['wildberries']);
                        $messages[] = $msg;
                        $log[] = "Новый заказ WB: $id";
                        $storage[$storeName]['processed']['wildberries'][] = $id;
                        $storage[$storeName]['history'][] = ['id' => $id, 'time' => $now, 'source' => 'wildberries'];
                    } else {
                        $invalidWbOrders++;
                    }
                }
                if ($invalidWbOrders > 0) {
                    $log[] = "Ошибка: Не удалось определить ID для $invalidWbOrders заказов WB.";
                }
            }
        }
    }

    // YANDEX
    if (!empty($storeData['yandex'])) {
        $yaResult = getYandexOrders($storeData['yandex']);
        $log[] = "Yandex API ответ: " . json_encode($yaResult);
        if (!validateApiResponse($yaResult, 'orders')) {
            $log[] = "Ошибка: Неверный ответ от Yandex API.";
        } else {
            if (!empty($yaResult['orders'])) {
                foreach ($yaResult['orders'] as $order) {
                    if (isset($order['id'])) {
                        $id = $order['id'];
                        if (!in_array($id, $storage[$storeName]['processed']['yandex'])) {
                            $msg = formatYandexMessage($storeName, $order, $id, $storeData['yandex']);
                            $messages[] = $msg;
                            $log[] = "Новый заказ Yandex: $id";
                            $storage[$storeName]['processed']['yandex'][] = $id;
                            $storage[$storeName]['history'][] = ['id' => $id, 'time' => $now, 'source' => 'yandex'];
                        }
                    } else {
                        $log[] = "Ошибка: Не удалось определить ID заказа Yandex.";
                    }
                }
            }
        }
    }

    if (!empty($messages)) {
        $text = "$emojiCalendar Магазин: $storeName\n\n" . implode("\n\n", $messages);
        $chatId = $storeData['telegram_chat_id'] ?? $config['telegram_chat_id'];
        $telegramBaseUrl = $config['telegram_base_url'] ?? "https://api.telegram.org";
        $url = "{$telegramBaseUrl}/bot{$config['bot_token']}/sendMessage?chat_id={$chatId}&text=" . urlencode($text);
        $responseData = sendTelegramMessage($url);
        if (!$responseData['ok']) {
            $log[] = "Ошибка Telegram: " . ($responseData['error'] ?? json_encode($responseData));
        } else {
            $log[] = "Ответ Telegram: Успешно отправлено.";
        }
        $log[] = "Telegram отправка: $url";
    } else {
        $log[] = "Нет новых заказов.";
    }
}

saveStorageWithLock($storage, __DIR__ . '/storage.json');
file_put_contents($debugLogPath, implode("\n", $log) . "\n", FILE_APPEND);
