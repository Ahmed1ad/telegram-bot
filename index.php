<?php
ini_set('display_errors', 0);
error_reporting(0);

$TOKEN = "7069425588:AAEPY8t51GF85-3MsICl5kChNcRzgRvWgjY";
$ADMIN_ID = 1739124234;
$BOT_STATUS = "online";
$SPAM_SECONDS = 10;

$update = json_decode(file_get_contents("php://input"), true);
if (!$update) exit;

/* ========= دوال ========= */

function send($id, $text, $kb = null) {
    global $TOKEN;
    $data = [
        "chat_id" => $id,
        "text" => $text,
        "parse_mode" => "HTML"
    ];
    if ($kb) $data["reply_markup"] = json_encode($kb);

    $ch = curl_init("https://api.telegram.org/bot$TOKEN/sendMessage");
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_exec($ch);
    curl_close($ch);
}

function save($file, $text) {
    file_put_contents($file, $text."\n", FILE_APPEND);
}

function isSpam($id) {
    global $SPAM_SECONDS;
    $data = file_exists("spam.json") ? json_decode(file_get_contents("spam.json"), true) : [];
    $now = time();

    if (isset($data[$id]) && ($now - $data[$id]) < $SPAM_SECONDS) {
        return true;
    }
    $data[$id] = $now;
    file_put_contents("spam.json", json_encode($data));
    return false;
}

/* ========= الرسائل ========= */

if (isset($update["message"])) {

    $m = $update["message"];
    $id = $m["chat"]["id"];
    $text = $m["text"] ?? "";
    $name = $m["from"]["first_name"];

    // حفظ المستخدم
    save("users.txt", $id);

    // سبام
    if ($id != $ADMIN_ID && isSpam($id)) {
        send($id, "⛔ <b>تم كتمك مؤقتًا</b>\nمنع السبام.");
        exit;
    }

    // /start
    if ($text == "/start") {
        $kb = [
            "inline_keyboard" => [
                [["text"=>"📩 تواصل مع الإدارة","callback_data"=>"contact"]],
                [["text"=>"ℹ️ معلومات","callback_data"=>"info"]]
            ]
        ];

        send($id,
"👋 <b>مرحبًا $name</b>
🟢 حالة البوت: <b>$BOT_STATUS</b>

اختر من القائمة 👇", $kb);
        exit;
    }

    /* ===== أوامر الأدمن ===== */
    if ($id == $ADMIN_ID) {

        if ($text == "/users") {
            $count = count(array_unique(file("users.txt")));
            send($id, "👥 عدد الأعضاء: <b>$count</b>");
        }

        if ($text == "/status") {
            send($id, "⚙️ حالة البوت الحالية: <b>$BOT_STATUS</b>");
        }

        if (strpos($text, "/reply") === 0) {
            $ex = explode(" ", $text, 3);
            if (count($ex) < 3) exit;
            send($ex[1], "📩 <b>رد الإدارة:</b>\n".$ex[2]);
        }

        exit;
    }

    // رد تلقائي
    if ($BOT_STATUS == "offline") {
        send($id, "🔴 الإدارة غير متاحة حاليًا\nسيتم الرد عليك لاحقًا.");
    }

    // إرسال رسالة للأدمن + حفظ
    save("messages.txt", "[$id] $text");

    send($ADMIN_ID,
"📨 <b>رسالة جديدة</b>
👤 $name
🆔 <code>$id</code>

💬 $text");

    send($id, "✅ تم إرسال رسالتك للإدارة.");
}

/* ========= الأزرار ========= */

if (isset($update["callback_query"])) {
    $id = $update["callback_query"]["message"]["chat"]["id"];
    $d = $update["callback_query"]["data"];

    if ($d == "contact") {
        send($id, "✍️ اكتب رسالتك وسيتم توصيلها مباشرة.");
    }

    if ($d == "info") {
        send($id,
"ℹ️ <b>بوت تواصل رسمي</b>
• رد سريع
• حفظ المحادثات
• حماية من السبام");
    }
}
