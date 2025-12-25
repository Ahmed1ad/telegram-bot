<?php
// لازم نرجع 200 OK لتلجرام
http_response_code(200);
echo "OK";

// نجيب التوكن من Environment Variable
$TOKEN = getenv("BOT_TOKEN");
if (!$TOKEN) {
    exit;
}

// نقرأ التحديث من تلجرام
$input = file_get_contents("php://input");
if (!$input) {
    exit;
}

$update = json_decode($input, true);
if (!isset($update["message"])) {
    exit;
}

$chat_id = $update["message"]["chat"]["id"];
$text = $update["message"]["text"] ?? "";

// رد بسيط
if ($text === "/start") {
    sendMessage($TOKEN, $chat_id, "✅ البوت شغال تمام!\n\nاكتب أي رسالة وهرد عليك.");
} else {
    sendMessage($TOKEN, $chat_id, "📩 انت كتبت:\n$text");
}

// دالة الإرسال
function sendMessage($token, $chat_id, $message) {
    $url = "https://api.telegram.org/bot$token/sendMessage";

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
