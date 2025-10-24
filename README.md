# notification-marketplace

Бот для уведомлений о новых заказах с маркетплейсов (Ozon, Wildberries, Яндекс.Маркет) в Telegram.

## Возможности
- Ozon: только "свежие" заказы с момента прошлого запуска (по статусам `awaiting_packaging`, `awaiting_deliver`, `acceptance_in_progress`).
- Wildberries: новые заказы через `/api/v3/orders/new` (read-only, без acknowledge).
- Яндекс.Маркет: `PROCESSING` через `orders.json` с `Authorization: Bearer`.
- Дедупликация и хранение отметки времени последнего запуска (`storage/sent.json`).
- Подробные логи: `storage/debug.log`.

## Установка
1. Скопируйте файлы на сервер.
2. Заполните секреты в `config.php`.
3. Убедитесь, что установлен PHP с расширением cURL.

## Запуск
```bash
php check_orders.php
```

## Cron
Ежеминутный запуск:
```cron
* * * * * /usr/bin/php /path/to/notification-marketplace/check_orders.php >/dev/null 2>&1
```

## Формат Telegram-сообщения
```
📅 Магазин: <store>
🚕 Новый заказ [<market>]
📃 Заказ №<id>
📦 Список товаров:
- <название>: <кол-во> шт
💰 Сумма заказа: <сумма> ₽
```

## GitHub (приватный репозиторий)
Создайте пустой приватный репозиторий `notification-marketplace`, затем:
```bash
cd notification-marketplace
git init
git branch -m main
git add .
git commit -m "Initial commit"
git remote add origin https://github.com/<ваш_ник>/notification-marketplace.git
git push -u origin main
```

## Замечания
- Первый запуск берёт окно последних 10 минут (чтобы не слать историю). Порог настраивается в `functions.php` через `DEFAULT_LOOKBACK_SECONDS`.
- Для Wildberries необходимо рабочее DNS-разрешение `suppliers-api.wildberries.ru` на сервере.
- Логи смотрите в `storage/debug.log`.
```
