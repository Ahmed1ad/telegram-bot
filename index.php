<?php
http_response_code(200);
date_default_timezone_set("Africa/Cairo");

/* ========== CONFIG ========== */
$BOT_TOKEN   = getenv("BOT_TOKEN");
$DASH_SECRET = "SUPER_ADMIN_2025";

/* ========== FILES ========== */
$CONTENT = "content.json";
$SCHEDULE = "schedule.json";
$TARGETS = "targets.json";
$LOGS = "publish_logs.json";

/* ========== HELPERS ========== */
function loadData($f){
    if(!file_exists($f)) file_put_contents($f,"[]");
    return json_decode(file_get_contents($f), true);
}
function saveData($f,$d){
    file_put_contents($f, json_encode($d, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
}
function sendTG($chat,$text){
    global $BOT_TOKEN;
    @file_get_contents(
        "https://api.telegram.org/bot$BOT_TOKEN/sendMessage?".
        http_build_query([
            "chat_id"=>$chat,
            "text"=>$text,
            "parse_mode"=>"HTML"
        ])
    );
}

/* =========================================================
   DASHBOARD (GET + POST) – حديثة وبلا صفحة بيضا
========================================================= */
if (isset($_GET["admin"]) && $_GET["admin"] === $DASH_SECRET) {

    $content  = loadData($CONTENT);
    $schedule = loadData($SCHEDULE);

    /* ---------- HANDLE POST ---------- */
    if ($_SERVER["REQUEST_METHOD"] === "POST") {

        if (isset($_POST["add_content"])) {
            $content[] = [
                "id" => time(),
                "text" => trim($_POST["text"])
            ];
            saveData($CONTENT, $content);
        }

        if (isset($_POST["add_schedule"])) {
            $schedule[] = [
                "id" => time(),
                "content_id" => $_POST["content_id"],
                "type" => $_POST["type"], // daily / weekly / monthly / once
                "time" => $_POST["time"],
                "day" => $_POST["day"] ?? null,
                "date" => $_POST["date"] ?? null,
                "last_run" => ""
            ];
            saveData($SCHEDULE, $schedule);
        }
    }

    /* ---------- UI ---------- */
    echo "<!DOCTYPE html><html><head><meta charset='UTF-8'>
    <title>Islamic Auto Publisher</title>
    <style>
    body{font-family:Tahoma;background:#0f172a;color:#fff;padding:20px}
    h2{margin-top:0}
    textarea,input,select{width:100%;padding:8px;margin:6px 0}
    button{padding:8px 16px;background:#22c55e;border:0;color:#000;cursor:pointer}
    .box{background:#111827;padding:15px;margin-bottom:20px;border-radius:10px}
    </style></head><body>";

    echo "<div class='box'>
        <h2>📿 إضافة منشور</h2>
        <form method='post'>
            <textarea name='text' rows='5' required></textarea>
            <button name='add_content'>حفظ المنشور</button>
        </form>
    </div>";

    echo "<div class='box'>
        <h2>⏰ جدولة منشور</h2>
        <form method='post'>
            <select name='content_id'>";
    foreach($content as $c){
        echo "<option value='{$c['id']}'>".
             htmlspecialchars(substr($c["text"],0,40)).
             "</option>";
    }
    echo "</select>

            <select name='type'>
                <option value='daily'>يومي</option>
                <option value='weekly'>أسبوعي</option>
                <option value='monthly'>شهري</option>
                <option value='once'>مرة واحدة</option>
            </select>

            <input type='time' name='time' required>
            <input type='number' name='day' placeholder='يوم الأسبوع / الشهر'>
            <input type='date' name='date'>
            <button name='add_schedule'>حفظ الجدولة</button>
        </form>
    </div>";

    echo "</body></html>";
    exit;
}

/* =========================================================
   SCHEDULER – النشر التلقائي
   (يعمل مع Cron / Ping كل دقيقة)
========================================================= */
$nowTime  = date("H:i");
$today    = date("Y-m-d");
$dayWeek  = date("w"); // 0-6
$dayMonth = date("j"); // 1-31

$content  = loadData($CONTENT);
$schedule = loadData($SCHEDULE);
$targets  = loadData($TARGETS);
$logs     = loadData($LOGS);

foreach ($schedule as &$s) {

    if ($s["time"] !== $nowTime) continue;
    if ($s["last_run"] === $today) continue;

    $run = false;
    if ($s["type"] === "daily") $run = true;
    if ($s["type"] === "weekly"  && $s["day"] == $dayWeek)  $run = true;
    if ($s["type"] === "monthly" && $s["day"] == $dayMonth) $run = true;
    if ($s["type"] === "once"    && $s["date"] == $today)   $run = true;

    if (!$run) continue;

    foreach ($content as $c) {
        if ($c["id"] == $s["content_id"]) {
            foreach ($targets as $t) {
                sendTG($t["chat_id"], $c["text"]);
            }
            $s["last_run"] = $today;
            $logs[] = date("Y-m-d H:i")." | Published schedule {$s['id']}";
        }
    }
}

saveData($SCHEDULE, $schedule);
saveData($LOGS, $logs);

/* =========================================================
   TELEGRAM UPDATES
   – رسالة تفعيل للجروبات والقنوات
========================================================= */
$update = json_decode(file_get_contents("php://input"), true);
if (!$update) exit;

/* ----- تفعيل الجروب / القناة ----- */
if (isset($update["message"]) && isset($update["message"]["chat"])) {

    $chat = $update["message"]["chat"];
    $chat_id = $chat["id"];
    $type = $chat["type"];
    $text = trim($update["message"]["text"] ?? "");

    // تفعيل فقط في جروب أو قناة
    if (in_array($type, ["group","supergroup","channel"]) && $text !== "") {

        $targets = loadData($TARGETS);
        $exists = false;

        foreach($targets as $t){
            if($t["chat_id"] == $chat_id){
                $exists = true;
                break;
            }
        }

        if(!$exists){
            $targets[] = [
                "chat_id" => $chat_id,
                "activated_at" => date("Y-m-d H:i")
            ];
            saveData($TARGETS, $targets);
        }
    }
}

/* ----- رد بسيط في الخاص ----- */
if (isset($update["message"]) && $update["message"]["chat"]["type"]=="private") {
    sendTG(
        $update["message"]["chat"]["id"],
        "🤖 البوت يعمل تلقائيًا\n📢 أضفني مشرف بالقناة أو الجروب ثم أرسل أي رسالة للتفعيل"
    );
}
