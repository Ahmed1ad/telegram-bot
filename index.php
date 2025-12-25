<?php
http_response_code(200);
echo "OK";

// اختبار بسيط جدًا
$TOKEN = getenv("BOT_TOKEN");

// اقرأ التحديث
$input = file_get_contents("php://input");
if (!$input) {
    exit;
}

$update = json_decode($input, true);
if (!isset($update["message"])) {
    exit;
}

$chat_id = $update["message"]["chat"]["id"];
$text = $update["message"]["text"] ?? "no text";

// رد
$url = "https://api.telegram.org/bot$TOKEN/sendMessage";
$data = [
    "chat_id" => $chat_id,
    "text" => "BOT OK ✅\nYou said: $text"
];

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_exec($ch);
curl_close($ch);
