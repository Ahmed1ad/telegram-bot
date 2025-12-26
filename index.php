<?php
http_response_code(200);

/* ================= CONFIG ================= */
$BOT_TOKEN = getenv("BOT_TOKEN");
$ADMIN_ID = 1739124234;
$ADMIN_EMAIL = "ad45821765@gmail.com";
$DASH_SECRET = "SUPER_ADMIN_2025";

/* ================= FILES ================= */
$F = [
 "users"=>"users.json",
 "products"=>"products.json",
 "pending"=>"pending.json",
 "orders"=>"orders.json",
 "topups"=>"topups.json",
 "logs"=>"logs.json"
];

/* ================= HELPERS ================= */
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
function adminOnly($id){
 global $ADMIN_ID;
 if($id!=$ADMIN_ID){
  send($id,"❌ هذا الأمر مخصص للإدارة فقط");
  return false;
 }
 return true;
}
function sendAdminEmail($subject,$message){
 global $ADMIN_EMAIL;
 $headers="From: Marketplace Bot <no-reply@railway.app>\r\n";
 $headers.="Content-Type: text/html; charset=UTF-8\r\n";
 @mail($ADMIN_EMAIL,$subject,$message,$headers);
}

/* ================= DASHBOARD (WEB) ================= */
if($_SERVER["REQUEST_METHOD"]==="GET"){
 if(!isset($_GET["admin"]) || $_GET["admin"]!==$DASH_SECRET){
  exit("Access Denied");
 }
 $users=load($F["users"]);
 $orders=load($F["orders"]);
 echo "<h2>Admin Dashboard</h2>";
 echo "<p>Users: ".count($users)."</p>";
 echo "<p>Orders: ".count($orders)."</p>";
 exit;
}

/* ================= UPDATE ================= */
$update=json_decode(file_get_contents("php://input"),true);
if(!$update) exit;

/* ================= MENU ================= */
$menu=[
 "keyboard"=>[
  ["🛍 المنتجات","➕ إضافة منتج"],
  ["💰 محفظتي","➕ إضافة رصيد"],
  ["👮‍♂️ أوامر الإدارة"],
  ["ℹ️ المساعدة"]
 ],
 "resize_keyboard"=>true
];

/* ================= MESSAGE ================= */
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

 /* START */
 if($text=="/start"){
  send($id,"🛒 <b>Marketplace Bot</b>\nاختر من القائمة 👇",$menu);
  exit;
 }

 /* WALLET */
 if($text=="💰 محفظتي"){
  send($id,"💰 رصيدك: <b>{$users[$id]['wallet']}</b>");
  exit;
 }

 /* TOPUP */
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

  send($ADMIN_ID,
"💳 طلب شحن جديد
🆔 $tid
👤 $id
💰 $amt

/accept_topup $tid
/reject_topup $tid");

  sendAdminEmail(
   "طلب شحن جديد",
   "User ID: $id<br>Amount: $amt"
  );

  send($id,"⏳ تم إرسال طلب الشحن للإدارة");
  logEvent("TOPUP REQUEST $id $amt");
  exit;
 }

 /* ADMIN COMMANDS LIST */
 if($text=="👮‍♂️ أوامر الإدارة"){
  send($id,
"👮‍♂️ <b>أوامر الإدارة</b>

📊 لوحة التحكم
📦 الطلبات
💳 طلبات الشحن");
  exit;
 }

 /* ADMIN: ACCEPT TOPUP */
 if(strpos($text,"/accept_topup")===0){
  if(!adminOnly($id)) exit;
  $tid=intval(explode(" ",$text)[1]??0);
  $topups=load($F["topups"]);
  $users=load($F["users"]);

  foreach($topups as &$t){
   if($t["id"]==$tid && $t["status"]=="pending"){
    $users[$t["user"]]["wallet"]+=$t["amount"];
    $t["status"]="accepted";
    save($F["users"],$users);
    save($F["topups"],$topups);
    send($t["user"],"✅ تم شحن رصيدك {$t['amount']}");
    logEvent("TOPUP ACCEPTED $tid");
   }
  }
  exit;
 }

 /* ADMIN: REJECT TOPUP */
 if(strpos($text,"/reject_topup")===0){
  if(!adminOnly($id)) exit;
  $tid=intval(explode(" ",$text)[1]??0);
  $topups=load($F["topups"]);

  foreach($topups as &$t){
   if($t["id"]==$tid){
    $t["status"]="rejected";
    save($F["topups"],$topups);
    send($t["user"],"❌ تم رفض طلب الشحن");
    logEvent("TOPUP REJECTED $tid");
   }
  }
  exit;
 }

 /* HELP */
 if($text=="ℹ️ المساعدة"){
  send($id,"ℹ️ استخدم القائمة للتنقل داخل البوت");
  exit;
 }
}
