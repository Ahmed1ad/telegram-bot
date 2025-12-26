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
    return json_decode(file_get_contents($f),true);
}
function save($f,$d){
    file_put_contents($f,json_encode($d,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
}
function send($chat,$text){
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

/* ========= HANDLE POSTS (FIX BLANK PAGE) ========= */
if(isset($_GET["admin"]) && $_GET["admin"] === $DASH_SECRET){

    $content  = load($CONTENT);
    $schedule = load($SCHEDULE);

    if(isset($_POST["add_content"])){
        $content[]=[
            "id"=>time(),
            "text"=>trim($_POST["text"])
        ];
        save($CONTENT,$content);
    }

    if(isset($_POST["add_schedule"])){
        $schedule[]=[
            "id"=>time(),
            "content_id"=>$_POST["content_id"],
            "type"=>$_POST["type"], // daily / weekly / monthly / once
            "time"=>$_POST["time"],
            "day"=>$_POST["day"] ?? null,
            "date"=>$_POST["date"] ?? null,
            "last_run"=>""
        ];
        save($SCHEDULE,$schedule);
    }
}

/* ========= DASHBOARD ========= */
if(isset($_GET["admin"]) && $_GET["admin"] === $DASH_SECRET){

    $content  = load($CONTENT);
    $schedule = load($SCHEDULE);

    echo "<!DOCTYPE html><html><head><meta charset='UTF-8'>
    <title>Dashboard</title>
    <style>
    body{font-family:Arial;background:#0f172a;color:#fff;padding:20px}
    textarea,input,select{width:100%;margin:5px 0;padding:8px}
    button{padding:8px 15px;margin-top:5px}
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
        echo "<option value='{$c['id']}'>".htmlspecialchars(substr($c["text"],0,40))."</option>";
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
$nowTime = date("H:i");
$today = date("Y-m-d");
$dayWeek = date("w");
$dayMonth = date("j");

$content = load($CONTENT);
$schedule = load($SCHEDULE);
$targets = load($TARGETS);
$logs = load($LOGS);

foreach($schedule as &$s){
    if($s["time"] !== $nowTime) continue;
    if($s["last_run"] === $today) continue;

    $run=false;
    if($s["type"]==="daily") $run=true;
    if($s["type"]==="weekly" && $s["day"]==$dayWeek) $run=true;
    if($s["type"]==="monthly" && $s["day"]==$dayMonth) $run=true;
    if($s["type"]==="once" && $s["date"]==$today) $run=true;

    if(!$run) continue;

    foreach($content as $c){
        if($c["id"]==$s["content_id"]){
            foreach($targets as $t){
                send($t,$c["text"]);
            }
            $s["last_run"]=$today;
            $logs[]=date("Y-m-d H:i")." | Published {$s['id']}";
        }
    }
}
save($SCHEDULE,$schedule);
save($LOGS,$logs);

/* ========= TELEGRAM UPDATES ========= */
$update=json_decode(file_get_contents("php://input"),true);
if(!$update) exit;

/* AUTO REGISTER CHANNELS / GROUPS */
if(isset($update["my_chat_member"])){
    $chat_id = $update["my_chat_member"]["chat"]["id"];
    $status = $update["my_chat_member"]["new_chat_member"]["status"];

    if(in_array($status,["administrator","member"])){
        $targets = load($TARGETS);
        if(!in_array($chat_id,$targets)){
            $targets[] = $chat_id;
            save($TARGETS,$targets);
        }
    }
}

/* BOT RESPONSE */
if(isset($update["message"])){
    send($update["message"]["chat"]["id"],
    "🤖 البوت يعمل تلقائيًا\n⏰ النشر يتم حسب الجدولة");
}    if(!isset($_GET["admin"]) || $_GET["admin"]!==$DASH_SECRET){
        exit("Access Denied");
    }

    $content = load($CONTENT);
    $schedule = load($SCHEDULE);
    $targets = load($TARGETS);

    /* ADD CONTENT */
    if(isset($_POST["add_content"])){
        $content[]=[
            "id"=>time(),
            "text"=>$_POST["text"]
        ];
        save($CONTENT,$content);
        header("Location:?admin=".$_GET["admin"]); exit;
    }

    /* ADD TARGET */
    if(isset($_POST["add_target"])){
        $targets[]=[
            "id"=>$_POST["chat_id"],
            "name"=>$_POST["name"]
        ];
        save($TARGETS,$targets);
        header("Location:?admin=".$_GET["admin"]); exit;
    }

    /* ADD SCHEDULE */
    if(isset($_POST["add_schedule"])){
        $schedule[]=[
            "id"=>time(),
            "content_id"=>$_POST["content_id"],
            "type"=>$_POST["type"], // daily / weekly / monthly / once
            "time"=>$_POST["time"],
            "day"=>$_POST["day"] ?? null,
            "date"=>$_POST["date"] ?? null,
            "last_run"=>""
        ];
        save($SCHEDULE,$schedule);
        header("Location:?admin=".$_GET["admin"]); exit;
    }

    /* UI */
    echo "<h2>📿 المحتوى</h2>
    <form method='post'>
    <textarea name='text' required></textarea><br>
    <button name='add_content'>إضافة منشور</button>
    </form>";

    echo "<h2>📢 القنوات / الجروبات</h2>
    <form method='post'>
    <input name='chat_id' placeholder='-100xxxx أو @channel' required>
    <input name='name' placeholder='الاسم' required>
    <button name='add_target'>إضافة</button>
    </form>";

    echo "<h2>⏰ الجدولة</h2>
    <form method='post'>
    <select name='content_id'>";
    foreach($content as $c){
        echo "<option value='{$c['id']}'>".substr($c["text"],0,30)."</option>";
    }
    echo "</select>
    <select name='type'>
      <option value='daily'>يومي</option>
      <option value='weekly'>أسبوعي</option>
      <option value='monthly'>شهري</option>
      <option value='once'>مرة واحدة</option>
    </select>
    <input type='time' name='time' required>
    <input type='number' name='day' placeholder='يوم الأسبوع/الشهر'>
    <input type='date' name='date'>
    <button name='add_schedule'>حفظ</button>
    </form>";

    exit;
}

/* ========= SCHEDULER ========= */
$nowTime = date("H:i");
$today = date("Y-m-d");
$dayWeek = date("w");
$dayMonth = date("j");

$content = load($CONTENT);
$schedule = load($SCHEDULE);
$targets = load($TARGETS);
$logs = load($LOGS);

foreach($schedule as &$s){
    if($s["time"]!==$nowTime) continue;
    if($s["last_run"]==$today) continue;

    $run=false;
    if($s["type"]=="daily") $run=true;
    if($s["type"]=="weekly" && $s["day"]==$dayWeek) $run=true;
    if($s["type"]=="monthly" && $s["day"]==$dayMonth) $run=true;
    if($s["type"]=="once" && $s["date"]==$today) $run=true;

    if(!$run) continue;

    foreach($content as $c){
        if($c["id"]==$s["content_id"]){
            foreach($targets as $t){
                send($t["id"],$c["text"]);
            }
            $s["last_run"]=$today;
            $logs[]=date("Y-m-d H:i")." | Published {$s['id']}";
        }
    }
}
save($SCHEDULE,$schedule);
save($LOGS,$logs);

/* ========= TELEGRAM ========= */
$update=json_decode(file_get_contents("php://input"),true);
if(!$update) exit;

if(isset($update["message"])){
    send($update["message"]["chat"]["id"],
    "🤖 البوت يعمل تلقائيًا\n⏰ النشر يتم حسب الجدولة");
}
