<?php
// مهم جدًا: رد ثابت لـ Telegram
http_response_code(200);
echo "OK";

// توكن البوت
$TOKEN = "PUT_YOUR_BOT_TOKEN_HERE";

// استقبال التحديث
$update = json_decode(file_get_contents("php://input"), true);
if (!$update) exit;

// لو في رسالة
if (isset($update["message"])) {

    $chat_id = $update["message"]["chat"]["id"];
    $text = $update["message"]["text"] ?? "";

    // رد على /start
    if ($text === "/start") {
        sendMessage($chat_id, "✅ البوت شغال تمام!\n\nاكتب أي رسالة وهرد عليك 👌");
    } else {
        sendMessage($chat_id, "📩 وصلت رسالتك:\n$text");
    }
}

// دالة الإرسال
function sendMessage($chat_id, $message) {
    global $TOKEN;

    $url = "https://api.telegram.org/bot$TOKEN/sendMessage";
    $data = [
        "chat_id" => $chat_id,
        "text" => $message
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_exec($ch);
    curl_close($ch);
}
