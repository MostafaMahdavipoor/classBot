<?php
require_once 'config.php';
require_once 'BotHandler.php';
require_once 'Database.php';
require_once 'FileHandler.php';

$update = json_decode(file_get_contents('php://input'), TRUE);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (isset($update['message'])) {
    $message = $update['message'];
    $chatId = $message['chat']['id'];
    $text = $message['text'] ?? '';
    $messageId = $message['message_id'] ?? null;;

    $bot = new BotHandler($chatId, $text, $messageId, $message);
} elseif (isset($update['callback_query'])) {
    $callbackQuery = $update['callback_query'];
    $chatId = $callbackQuery['message']['chat']['id'];
    $messageId = $callbackQuery['message']['message_id'] ?? null;

    $bot = new BotHandler($chatId, '', $messageId, $callbackQuery['message']);
    $bot->handleCallbackQuery($callbackQuery);
} elseif (isset($update['pre_checkout_query'])) {
    $bot = new BotHandler(null, null, null, null);
    $bot->handlePreCheckoutQuery($update);
}
