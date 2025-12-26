<?php
http_response_code(200);

/* ================= CONFIG ================= */
define("BOT_TOKEN", getenv("BOT_TOKEN"));
define("ADMIN_ID", 1739124234);
define("DASH_SECRET", "SUPER_ADMIN_2025");

/* SMTP */
define("SMTP_HOST","smtp.gmail.com");
define("SMTP_USER","ad45821765@gmail.com");
define("SMTP_PASS","bgupebqkdhnwwemo");
define("ADMIN_EMAIL","ad45821765@gmail.com");

/* FILES */
$F = [
 "users"=>"users.json",
 "products"=>"products.json",
 "pending"=>"pending.json",
 "orders"=>"orders.json",
 "topups"=>"topups.json",
 "logs"=>"logs.json"
];

/* ================= HELPERS ================= */
function load($f){ if(!file_exists($f)) file_put_contents($f,"[]"); return json_decode(file_get_contents($f),true); }
function save($f,$d){ file_put_contents($f,json_encode($d,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE)); }
function logEvent($t){ $l=load("logs.json"); $l[]=date("Y-m-d H:i:s")." | ".$t; save("logs.json",$l); }

function send($id,$txt,$kb=null){
 $d=["chat_id"=>$id,"text"=>$txt,"parse_mode"=>"HTML"];
 if($kb) $d["reply_markup"]=json_encode($kb);
 file_get_contents("https://api.telegram.org/bot".BOT_TOKEN."/sendMessage?".http_build_query($d));
}

function adminOnly($id){
 if($id!=ADMIN_ID){ send($id,"❌ هذا الأمر مخصص للإدارة فقط"); return false; }
 return true;
}

/* ================= EMAIL (SMTP) ================= */
use PHPMailer\PHPMailer\PHPMailer;
require_once __DIR__."/PHPMailer.php";
require_once __DIR__."/SMTP.php";
require_once __DIR__."/Exception.php";

function sendAdminEmail($subject,$body){
 $m=new PHPMailer(true);
 $m->isSMTP();
 $m->Host=SMTP_HOST;
 $m->SMTPAuth=true;
 $m->Username=SMTP_USER;
 $m->Password=SMTP_PASS;
 $m->SMTPSecure="tls";
 $m->Port=587;
 $m->setFrom(SMTP_USER,"Marketplace Bot");
 $m->addAddress(ADMIN_EMAIL);
 $m->isHTML(true);
 $m->Subject=$subject;
 $m->Body=$body;
 $m->send();
}

/* ================= DASHBOARD ================= */
if($_SERVER["REQUEST_METHOD"]==="GET"){
 if(!isset($_GET["admin"])||$_GET["admin"]!==DASH_SECRET) exit("Access Denied");

 $users=load($F["users"]);
 $orders=load($F["orders"]);
 echo "<h1>Admin Dashboard</h1>";
 echo "<p>Users: ".count($users)."</p>";
 echo "<p>Orders: ".count($orders)."</p>";
 exit;
}

/* ================= UPDATE ================= */
$u=json_decode(file_get_contents("php://input"),true);
if(!$u) exit;

/* ================= MESSAGE ================= */
if(isset($u["message"])){
 $m=$u["message"];
 $id=$m["chat"]["id"];
 $text=$m["text"]??"";
 $users=load($F["users"]);

 if(!isset($users[$id])){
  $users[$id]=["wallet"=>0,"name"=>$m["from"]["first_name"]];
  save($F["users"],$users);
 }

 /* MENU */
 if($text=="/start"){
  send($id,"🛒 <b>Marketplace Bot</b>",[
   "keyboard"=>[
    ["🛍 المنتجات","➕ إضافة منتج"],
    ["💰 محفظتي","➕ إضافة رصيد"],
    ["👮‍♂️ أوامر الإدارة"],
    ["ℹ️ المساعدة"]
   ],
   "resize_keyboard"=>true
  ]);
 }

 /* TOPUP */
 if(strpos($text,"/topup")===0){
  $amt=intval(explode(" ",$text)[1]??0);
  if($amt<=0){ send($id,"❌ مبلغ غير صحيح"); exit; }

  $t=load($F["topups"]);
  $tid=time();
  $t[]=["id"=>$tid,"user"=>$id,"amount"=>$amt,"status"=>"pending"];
  save($F["topups"],$t);

  send(ADMIN_ID,"💳 طلب شحن جديد\nID:$tid\nUser:$id\nAmount:$amt\n/accept_topup $tid\n/reject_topup $tid");
  sendAdminEmail("طلب شحن جديد","User:$id<br>Amount:$amt");
  send($id,"⏳ تم إرسال طلب الشحن للإدارة");
 }

 /* ADMIN COMMANDS LIST */
 if($text=="👮‍♂️ أوامر الإدارة"){
  send($id,
"👮‍♂️ <b>أوامر الإدارة</b>

📊 لوحة التحكم
📦 الطلبات
💳 طلبات الشحن
🧩 المنتجات المعلقة
👤 المستخدمين
💰 الأرباح");
 }
}

/* ================= ADMIN ACTIONS ================= */
if(isset($u["message"])){
 $id=$u["message"]["chat"]["id"];
 $text=$u["message"]["text"]??"";

 if(strpos($text,"/accept_topup")===0){
  if(!adminOnly($id)) exit;
  $tid=intval(explode(" ",$text)[1]);
  $t=load($F["topups"]);
  $users=load($F["users"]);
  foreach($t as &$r){
   if($r["id"]==$tid && $r["status"]=="pending"){
    $users[$r["user"]]["wallet"]+=$r["amount"];
    $r["status"]="accepted";
    save($F["users"],$users);
    save($F["topups"],$t);
    send($r["user"],"✅ تم شحن رصيدك {$r['amount']}");
   }
  }
 }

 if(strpos($text,"/reject_topup")===0){
  if(!adminOnly($id)) exit;
  $tid=intval(explode(" ",$text)[1]);
  $t=load($F["topups"]);
  foreach($t as &$r){
   if($r["id"]==$tid){
    $r["status"]="rejected";
    save($F["topups"],$t);
    send($r["user"],"❌ تم رفض طلب الشحن");
   }
  }
 }
}
