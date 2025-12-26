<?php
http_response_code(200);

/* ============== CONFIG ============== */
$BOT_TOKEN   = getenv("BOT_TOKEN");
$ADMIN_ID    = 1739124234;
$ADMIN_EMAIL = "ad45821765@gmail.com";
$DASH_SECRET = "SUPER_ADMIN_2025";

/* ============== FILES ============== */
$F = [
  "users"=>"users.json",
  "orders"=>"orders.json",
  "topups"=>"topups.json",
  "logs"=>"logs.json"
];

/* ============== HELPERS ============== */
function load($f){
  if(!file_exists($f)) file_put_contents($f,"[]");
  return json_decode(file_get_contents($f),true);
}
function save($f,$d){
  file_put_contents($f,json_encode($d,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
}
function logEvent($t){
  $l=load("logs.json");
  $l[]=date("Y-m-d H:i:s")." | ".$t;
  save("logs.json",$l);
}
function send($id,$txt,$kb=null){
  global $BOT_TOKEN;
  $data=["chat_id"=>$id,"text"=>$txt,"parse_mode"=>"HTML"];
  if($kb) $data["reply_markup"]=json_encode($kb);
  file_get_contents("https://api.telegram.org/bot$BOT_TOKEN/sendMessage?".http_build_query($data));
}

/* ============== DASHBOARD (FULL CONTROL) ============== */
if($_SERVER["REQUEST_METHOD"]==="GET"){
  if(!isset($_GET["admin"]) || $_GET["admin"]!==$DASH_SECRET){
    exit("Access Denied");
  }

  $users  = load($F["users"]);
  $orders = load($F["orders"]);
  $topups = load($F["topups"]);

  /* ---- ACTIONS ---- */

  // قبول شحن
  if(isset($_GET["accept_topup"])){
    foreach($topups as &$t){
      if($t["id"]==$_GET["accept_topup"] && $t["status"]=="pending"){
        $users[$t["user"]]["wallet"] += $t["amount"];
        $t["status"]="accepted";
        send($t["user"],"✅ تم شحن رصيدك {$t['amount']}");
        logEvent("TOPUP ACCEPTED {$t['id']}");
      }
    }
    save($F["users"],$users); save($F["topups"],$topups);
    header("Location: ?admin=".$_GET["admin"]); exit;
  }

  // رفض شحن
  if(isset($_GET["reject_topup"])){
    foreach($topups as &$t){
      if($t["id"]==$_GET["reject_topup"]){
        $t["status"]="rejected";
        send($t["user"],"❌ تم رفض طلب الشحن");
        logEvent("TOPUP REJECTED {$t['id']}");
      }
    }
    save($F["topups"],$topups);
    header("Location: ?admin=".$_GET["admin"]); exit;
  }

  // إضافة رصيد
  if(isset($_GET["add_balance"])){
    $uid=$_GET["add_balance"];
    $amt=intval($_GET["amount"]);
    $users[$uid]["wallet"]+=$amt;
    save($F["users"],$users);
    send($uid,"➕ تم إضافة $amt إلى رصيدك");
    logEvent("BALANCE ADD $uid $amt");
    header("Location: ?admin=".$_GET["admin"]); exit;
  }

  // خصم رصيد
  if(isset($_GET["remove_balance"])){
    $uid=$_GET["remove_balance"];
    $amt=intval($_GET["amount"]);
    $users[$uid]["wallet"]-=$amt;
    if($users[$uid]["wallet"]<0) $users[$uid]["wallet"]=0;
    save($F["users"],$users);
    send($uid,"➖ تم خصم $amt من رصيدك");
    logEvent("BALANCE REMOVE $uid $amt");
    header("Location: ?admin=".$_GET["admin"]); exit;
  }

  /* ---- UI ---- */
  echo "<h1>📊 Admin Dashboard</h1>";

  echo "<h2>💳 طلبات شحن الرصيد</h2>";
  foreach($topups as $t){
    if($t["status"]=="pending"){
      echo "User: {$t['user']} | Amount: {$t['amount']}
      <a href='?admin={$_GET['admin']}&accept_topup={$t['id']}'>✅ قبول</a>
      <a href='?admin={$_GET['admin']}&reject_topup={$t['id']}'>❌ رفض</a><br>";
    }
  }

  echo "<h2>👤 المستخدمين</h2>";
  foreach($users as $uid=>$u){
    echo "{$u['name']} (ID:$uid) | Balance: {$u['wallet']}
    <a href='?admin={$_GET['admin']}&add_balance=$uid&amount=100'>➕100</a>
    <a href='?admin={$_GET['admin']}&remove_balance=$uid&amount=50'>➖50</a><br>";
  }

  exit;
}

/* ============== TELEGRAM BOT ============== */
$update=json_decode(file_get_contents("php://input"),true);
if(!$update) exit;

if(isset($update["message"])){
  $m=$update["message"];
  $id=$m["chat"]["id"];
  $text=$m["text"]??"";
  $name=$m["from"]["first_name"]??"";

  $users=load($F["users"]);
  if(!isset($users[$id])){
    $users[$id]=["wallet"=>0,"name"=>$name];
    save($F["users"],$users);
  }

  /* MENU */
  $menu=[
    "keyboard"=>[
      ["💰 محفظتي","➕ إضافة رصيد"],
      ["ℹ️ المساعدة"]
    ],
    "resize_keyboard"=>true
  ];

  if($text=="/start"){
    send($id,"🛒 <b>Marketplace Bot</b>\nاختر من القائمة 👇",$menu);
    exit;
  }

  if($text=="💰 محفظتي"){
    send($id,"💰 رصيدك: <b>{$users[$id]['wallet']}</b>");
    exit;
  }

  if($text=="➕ إضافة رصيد"){
    send($id,"💳 أرسل:\n<code>/topup 100</code>");
    exit;
  }

  if(strpos($text,"/topup")===0){
    $amt=intval(explode(" ",$text)[1]??0);
    if($amt<=0){ send($id,"❌ مبلغ غير صحيح"); exit; }

    $topups=load($F["topups"]);
    $tid=time();
    $topups[]=["id"=>$tid,"user"=>$id,"amount"=>$amt,"status"=>"pending"];
    save($F["topups"],$topups);

    send($ADMIN_ID,"💳 طلب شحن جديد\nUser:$id\nAmount:$amt");
    send($id,"⏳ تم إرسال طلب الشحن للإدارة");
    logEvent("TOPUP REQUEST $id $amt");
    exit;
  }

  if($text=="ℹ️ المساعدة"){
    send($id,"ℹ️ تواصل مع الإدارة في حالة وجود مشكلة");
    exit;
  }
}
