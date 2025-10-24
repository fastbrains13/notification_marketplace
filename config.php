<?php
return [
  // Telegram
  'bot_token' => '000000000:REPLACE_ME',
  'telegram_chat_id' => '-1000000000000',

  // Stores credentials (fill only what you use)
  'stores' => [
    'AquaCam' => [
      'ozon' => [
        'client_id' => 'REPLACE_ME',
        'api_key'   => 'REPLACE_ME',
      ],
      'wildberries' => [
        'token' => 'WB_REPLACE_ME',
        // ack removed in v8 (read-only)
      ],
      'yandex' => [
        'campaign_id' => 12345678,
        'oauth_token' => 'REPLACE_ME',
      ],
    ],
    'Action Sport' => [
      'ozon' => [
        'client_id' => 'REPLACE_ME',
        'api_key'   => 'REPLACE_ME',
      ],
      'wildberries' => [
        'token' => 'WB_REPLACE_ME',
      ],
      'yandex' => [
        'campaign_id' => 12345678,
        'oauth_token' => 'REPLACE_ME',
      ],
    ],
  ],
];
