<?php
http_response_code(200);
date_default_timezone_set("Africa/Cairo");

/* ========= CONFIG ========= */
$BOT_TOKEN = getenv("BOT_TOKEN");
$DASH_SECRET = "SUPER_ADMIN_2025";

/* ========= FILES ========= */
$CONTENT = "content.json";
$SCHEDULE = "schedule.json";
$TARGETS = "targets.json";
$LOGS = "publish_logs.json";

/* ========= HELPERS ========= */
function load($f){
    if(!file_exists($f)) file_put_contents($f,"[]");
    return json_decode(file_get_contents($f), true);
}
function save($f,$d){
    file_put_contents($f, json_encode($d, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
}
function sendMsg($chat,$text){
    global $BOT_TOKEN;
    @file_get_contents(
        "https://api.telegram.org/bot$BOT_TOKEN/sendMessage?" .
        http_build_query([
            "chat_id"=>$chat,
            "text"=>$text,
            "parse_mode"=>"HTML"
        ])
    );
}

/* ========= DASHBOARD (GET + POST) ========= */
if (isset($_GET["admin"]) && $_GET["admin"] === $DASH_SECRET) {

    $content  = load($CONTENT);
    $schedule = load($SCHEDULE);

    /* HANDLE POSTS */
    if ($_SERVER["REQUEST_METHOD"] === "POST") {

        if (isset($_POST["add_content"])) {
            $content[] = [
                "id" => time(),
                "text" => trim($_POST["text"])
            ];
            save($CONTENT, $content);
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
            save($SCHEDULE, $schedule);
        }
    }

    /* DASHBOARD UI */
    echo "<!DOCTYPE html><html><head><meta charset='UTF-8'>
    <title>Dashboard</title>
    <style>
    body{font-family:Arial;background:#0f172a;color:#fff;padding:20px}
    textarea,input,select{width:100%;margin:6px 0;padding:8px}
    button{padding:8px 16px;margin-top:8px;cursor:pointer}
    .box{background:#111827;padding:15px;margin-bottom:20px;border-radius:8px}
    </style></head><body>";

    echo "<div class='box'><h2>📿 إضافة منشور</h2>
    <form method='post'>
        <textarea name='text' required></textarea>
        <button name='add_content'>حفظ</button>
    </form></div>";

    echo "<div class='box'><h2>⏰ جدولة منشور</h2>
    <form method='post'>
        <select name='content_id'>";
    foreach($content as $c){
        echo "<option value='{$c['id']}'>" .
             htmlspecialchars(substr($c["text"],0,40)) .
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
    </form></div>";

    echo "</body></html>";
    exit;
}

/* ========= SCHEDULER ========= */
$nowTime  = date("H:i");
$today    = date("Y-m-d");
$dayWeek  = date("w");
$dayMonth = date("j");

$content  = load($CONTENT);
$schedule = load($SCHEDULE);
$targets  = load($TARGETS);
$logs     = load($LOGS);

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
                sendMsg($t, $c["text"]);
            }
            $s["last_run"] = $today;
            $logs[] = date("Y-m-d H:i") . " | Published schedule {$s['id']}";
        }
    }
}

save($SCHEDULE, $schedule);
save($LOGS, $logs);

/* ========= TELEGRAM UPDATES ========= */
$update = json_decode(file_get_contents("php://input"), true);
if (!$update) exit;

/* AUTO REGISTER GROUPS / CHANNELS */
if (isset($update["my_chat_member"])) {
    $chat_id = $update["my_chat_member"]["chat"]["id"];
    $status  = $update["my_chat_member"]["new_chat_member"]["status"];

    if (in_array($status, ["administrator", "member"])) {
        $targets = load($TARGETS);
        if (!in_array($chat_id, $targets)) {
            $targets[] = $chat_id;
            save($TARGETS, $targets);
        }
    }
}

/* SIMPLE BOT RESPONSE */
if (isset($update["message"])) {
    sendMsg(
        $update["message"]["chat"]["id"],
        "🤖 البوت يعمل تلقائيًا\n⏰ النشر يتم حسب الجدولة"
    );
}
