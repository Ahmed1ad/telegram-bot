<?php

/**
 * اسم البوت: MyAwesomeBot
 * الوصف: بوت تيليجرام متطور يدعم الرد الآلي، تحليل الصور، ولوحة تحكم بسيطة.
 */

// إعدادات التوكن والمعرفات الأساسية
define('API_KEY', '7069425588:AAHum419wO6f-pCQK0ighkg7ZcTGPls9LQw');
define('ADMIN_ID', 12345678); // استبدل هذا بمعرفك (Chat ID)

// استقبال البيانات القادمة من تيليجرام
$update = json_decode(file_get_contents('php://input'));
if (!$update) exit;

$message = $update->message ?? null;
$chat_id = $message->chat->id ?? null;
$text = $message->text ?? '';
$photo = $message->photo ?? null;
$from_id = $message->from->id ?? null;

/**
 * وظيفة لإرسال الطلبات إلى تيليجرام باستخدام cURL
 */
function botRequest($method, $datas = []) {
    $url = "https://api.telegram.org/bot" . API_KEY . "/" . $method;
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $datas);
    $res = curl_exec($ch);
    if (curl_error($ch)) {
        return (object) ['ok' => false, 'error' => curl_error($ch)];
    }
    curl_close($ch);
    return json_decode($res);
}

/**
 * حفظ البيانات في ملف JSON
 */
function saveData($userId, $data) {
    $db = json_decode(file_get_contents('database.json'), true) ?: [];
    $db[$userId] = $data;
    file_put_contents('database.json', json_encode($db));
}

// --- معالجة الأوامر ---

if ($text == '/start') {
    botRequest('sendMessage', [
        'chat_id' => $chat_id,
        'text' => "أهلاً بك في MyAwesomeBot! 🤖\nأنا بوت ذكي أستطيع الرد تلقائياً وتحليل الصور.",
        'reply_markup' => json_encode([
            'keyboard' => [
                [['text' => 'قائمة الأوامر'], ['text' => 'معلوماتي']]
            ],
            'resize_keyboard' => true
        ])
    ]);
    saveData($from_id, ['last_seen' => time(), 'username' => $message->from->username ?? 'Unknown']);
}

elseif ($text == '/help' || $text == 'قائمة الأوامر') {
    botRequest('sendMessage', [
        'chat_id' => $chat_id,
        'text' => "الأوامر المتاحة:\n/start - بدء البوت\n/help - عرض التعليمات\nأرسل صورة لتحليلها\nلوحة التحكم (للمسؤول فقط)"
    ]);
}

// --- تحليل الصور ---
elseif ($photo) {
    $file_id = end($photo)->file_id;
    botRequest('sendMessage', [
        'chat_id' => $chat_id,
        'text' => "لقد استلمت صورتك! 🖼️\nمعرف الملف (File ID): \n`$file_id`",
        'parse_mode' => 'Markdown'
    ]);
}

// --- الرد الآلي ---
elseif ($text == 'السلام عليكم') {
    botRequest('sendMessage', ['chat_id' => $chat_id, 'text' => 'وعليكم السلام ورحمة الله وبركاته، كيف يمكنني مساعدتك؟']);
}

// --- لوحة التحكم (للمسؤول) ---
elseif ($text == 'لوحة تحكم' && $from_id == ADMIN_ID) {
    $users = count(json_decode(file_get_contents('database.json'), true) ?: []);
    botRequest('sendMessage', [
        'chat_id' => $chat_id,
        'text' => "مرحباً أيها المدير! 🛠️\nعدد المستخدمين المخزنين: $users"
    ]);
}

// --- ميزة الصدى (Echo) كخيار افتراضي ---
else {
    if ($text != '') {
        botRequest('sendMessage', [
            'chat_id' => $chat_id,
            'text' => "لقد قلت: $text"
        ]);
    }
}
?>","explanation":"هذا السكريبت هو نظام متكامل لبوت تيليجرام بلغة PHP. يعتمد على تقنية Webhook لاستقبال التحديثات فورياً. يتضمن وظيفة إرسال عبر cURL لضمان الأمان والسرعة. السكريبت يدعم الأوامر الأساسية (/start و /help)، ويحتوي على منطق للرد الآلي على كلمات محددة، ومعالجة الصور من خلال استخراج الـ file_id الخاص بها. كما يتضمن نظام تخزين بسيط باستخدام ملف JSON لحفظ بيانات المستخدمين، مع ميزة التحقق من هوية المسؤول لفتح لوحة التحكم.","steps":["احصل على استضافة تدعم PHP و بروتوكول SSL (HTTPS) ضروري جداً لعمل Webhook.","قم بإنشاء ملف باسم index.php في الاستضافة وضع الكود بداخله.","قم بتعديل ADMIN_ID في الكود ليطابق معرف حسابك على تيليجرام.","قم بإنشاء ملف فارغ باسم database.json في نفس المجلد وأعطه تصريح كتابة (CHMOD 777).","قم بربط البوت برابط الـ Webhook الخاص بك عن طريق فتح الرابط التالي في المتصفح: https://api.telegram.org/bot7069425588:AAHum419wO6f-pCQK0ighkg7ZcTGPls9LQw/setWebhook?url=https://yourdomain.com/path/to/index.php","تأكد من استبدال yourdomain.com بالرابط الفعلي لموقعك."]}```
