<?php
declare(strict_types=1);
require_once __DIR__ . '/functions.php';
$cfg = app_config();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Bot Fixed — статус</title>
</head>
<body>
<h1>Bot Fixed — готово к проверке</h1>
<ul>
  <li>Лог-файл: <code><?= htmlspecialchars($cfg['log_file'], ENT_QUOTES, 'UTF-8') ?></code></li>
  <li>Стора: <code><?= htmlspecialchars($cfg['storage_path'], ENT_QUOTES, 'UTF-8') ?></code></li>
</ul>
<p><a href="test_send.php">Отправить тестовое уведомление</a> (можно добавить <code>?text=...</code>)</p>
</body>
</html>
