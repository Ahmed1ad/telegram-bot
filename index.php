<?php
http_response_code(200);

/* ========== CONFIG ========== */
$BOT_TOKEN = getenv("BOT_TOKEN");
$ADMIN_ID = 1739124234;
$DASH_SECRET = "SUPER_ADMIN_2025";

/* ========== FILES ========== */
$USERS = "users.json";
$TOPUPS = "topups.json";
$LOGS = "logs.json";

/* ========== HELPERS ========== */
function load($f){
    if(!file_exists($f)) file_put_contents($f,"[]");
    return json_decode(file_get_contents($f), true);
}
function save($f,$d){
    file_put_contents($f, json_encode($d, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
}
function send($id,$text){
    global $BOT_TOKEN;
    file_get_contents(
        "https://api.telegram.org/bot$BOT_TOKEN/sendMessage?".
        http_build_query(["chat_id"=>$id,"text"=>$text,"parse_mode"=>"HTML"])
    );
}
function logEvent($t){
    $l = load("logs.json");
    $l[] = date("Y-m-d H:i:s")." | ".$t;
    save("logs.json",$l);
}

/* ========== DASHBOARD (GET) ========== */
if($_SERVER["REQUEST_METHOD"]==="GET"){
    if(!isset($_GET["admin"]) || $_GET["admin"]!==$DASH_SECRET){
        exit("Access Denied");
    }

    $users  = load($USERS);
    $topups = load($TOPUPS);

    /* ---- ACTIONS ---- */

    if(isset($_GET["accept"])){
        foreach($topups as &$t){
            if($t["id"]==$_GET["accept"] && $t["status"]=="pending"){
                $users[$t["user"]]["wallet"] += $t["amount"];
                $t["status"] = "accepted";
                send($t["user"],"✅ تم شحن رصيدك {$t['amount']}");
                logEvent("TOPUP ACCEPTED {$t['id']}");
            }
        }
        save($USERS,$users); save($TOPUPS,$topups);
        header("Location: ?admin=".$_GET["admin"]); exit;
    }

    if(isset($_GET["reject"])){
        foreach($topups as &$t){
            if($t["id"]==$_GET["reject"]){
                $t["status"] = "rejected";
                send($t["user"],"❌ تم رفض طلب الشحن");
                logEvent("TOPUP REJECTED {$t['id']}");
            }
        }
        save($TOPUPS,$topups);
        header("Location: ?admin=".$_GET["admin"]); exit;
    }

    if(isset($_GET["addbal"])){
        $uid=$_GET["addbal"]; $amt=intval($_GET["amt"]);
        $users[$uid]["wallet"] += $amt;
        save($USERS,$users);
        send($uid,"➕ تم إضافة $amt إلى رصيدك");
        header("Location: ?admin=".$_GET["admin"]); exit;
    }

    if(isset($_GET["subbal"])){
        $uid=$_GET["subbal"]; $amt=intval($_GET["amt"]);
        $users[$uid]["wallet"] -= $amt;
        if($users[$uid]["wallet"]<0) $users[$uid]["wallet"]=0;
        save($USERS,$users);
        send($uid,"➖ تم خصم $amt من رصيدك");
        header("Location: ?admin=".$_GET["admin"]); exit;
    }

    /* ---- UI ---- */
    echo "<!DOCTYPE html><html><head><meta charset='UTF-8'>
    <title>Admin Dashboard</title>
    <style>
    body{margin:0;font-family:Segoe UI;background:#0f172a;color:#fff}
    .nav{background:#020617;padding:15px;font-size:20px}
    .wrap{padding:20px}
    .cards{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:15px}
    .card{background:#111827;padding:20px;border-radius:12px}
    table{width:100%;border-collapse:collapse;margin-top:20px}
    th,td{padding:10px;border-bottom:1px solid #1f2937;text-align:left}
    .btn{padding:6px 10px;border-radius:6px;text-decoration:none;color:#fff;font-size:14px}
    .ok{background:#16a34a}
    .no{background:#dc2626}
    .add{background:#2563eb}
    .sub{background:#9333ea}
    </style>
    </head><body>";

    echo "<div class='nav'>📊 Admin Dashboard</div><div class='wrap'>";

    echo "<div class='cards'>
        <div class='card'>👤 Users<br><b>".count($users)."</b></div>
        <div class='card'>💳 Pending Topups<br><b>".count(array_filter($topups,fn($t)=>$t['status']=='pending'))."</b></div>
    </div>";

    echo "<h2>💳 طلبات شحن الرصيد</h2>
    <table><tr><th>User</th><th>Amount</th><th>Action</th></tr>";
    foreach($topups as $t){
        if($t["status"]=="pending"){
            echo "<tr>
            <td>{$t['user']}</td>
            <td>{$t['amount']}</td>
            <td>
            <a class='btn ok' href='?admin={$_GET['admin']}&accept={$t['id']}'>قبول</a>
            <a class='btn no' href='?admin={$_GET['admin']}&reject={$t['id']}'>رفض</a>
            </td></tr>";
        }
    }
    echo "</table>";

    echo "<h2>👤 المستخدمين</h2>
    <table><tr><th>ID</th><th>Name</th><th>Balance</th><th>Actions</th></tr>";
    foreach($users as $uid=>$u){
        echo "<tr>
        <td>$uid</td>
        <td>{$u['name']}</td>
        <td>{$u['wallet']}</td>
        <td>
        <a class='btn add' href='?admin={$_GET['admin']}&addbal=$uid&amt=100'>+100</a>
        <a class='btn sub' href='?admin={$_GET['admin']}&subbal=$uid&amt=50'>-50</a>
        </td></tr>";
    }
    echo "</table>";

    echo "</div></body></html>";
    exit;
}

/* ========== TELEGRAM BOT (POST) ========== */
$update = json_decode(file_get_contents("php://input"), true);
if(!$update) exit;

if(isset($update["message"])){
    $m = $update["message"];
    $id = $m["chat"]["id"];
    $text = $m["text"] ?? "";
    $name = $m["from"]["first_name"] ?? "User";

    $users = load($USERS);
    if(!isset($users[$id])){
        $users[$id] = ["name"=>$name,"wallet"=>0];
        save($USERS,$users);
    }

    if($text=="/start"){
        send($id,"🛒 <b>Marketplace Bot</b>\n\n💰 رصيدك: {$users[$id]['wallet']}\n\nاكتب:\n/topup 100 لشحن الرصيد");
        exit;
    }

    if(strpos($text,"/topup")===0){
        $amt=intval(explode(" ",$text)[1]??0);
        if($amt<=0){ send($id,"❌ مبلغ غير صحيح"); exit; }

        $topups=load($TOPUPS);
        $topups[]=["id"=>time(),"user"=>$id,"amount"=>$amt,"status"=>"pending"];
        save($TOPUPS,$topups);

        send($id,"⏳ تم إرسال طلب الشحن للإدارة");
        send($ADMIN_ID,"💳 طلب شحن جديد\nUser:$id\nAmount:$amt");
        logEvent("TOPUP REQUEST $id $amt");
        exit;
    }
}
