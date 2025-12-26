<?php
http_response_code(200);

/* ================= CONFIG ================= */
$TOKEN = getenv("BOT_TOKEN");
$ADMIN_ID = 1739124234;
$COMMISSION = 0.10;

/* ================= FILES ================= */
$F = [
  "products"=>"products.json",
  "pending"=>"pending.json",
  "users"=>"users.json",
  "orders"=>"orders.json"
];

/* ================= HELPERS ================= */
function load($f){
  if(!file_exists($f)) file_put_contents($f,"[]");
  return json_decode(file_get_contents($f),true);
}
function save($f,$d){
  file_put_contents($f,json_encode($d,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
}
function send($id,$txt,$kb=null){
  global $TOKEN;
  $data=["chat_id"=>$id,"text"=>$txt,"parse_mode"=>"HTML"];
  if($kb) $data["reply_markup"]=json_encode($kb);
  $ch=curl_init("https://api.telegram.org/bot$TOKEN/sendMessage");
  curl_setopt($ch,CURLOPT_POST,1);
  curl_setopt($ch,CURLOPT_POSTFIELDS,$data);
  curl_setopt($ch,CURLOPT_RETURNTRANSFER,1);
  curl_exec($ch); curl_close($ch);
}
function sendPhotoMsg($id,$photo,$cap,$kb=null){
  global $TOKEN;
  $data=["chat_id"=>$id,"photo"=>$photo,"caption"=>$cap,"parse_mode"=>"HTML"];
  if($kb) $data["reply_markup"]=json_encode($kb);
  $ch=curl_init("https://api.telegram.org/bot$TOKEN/sendPhoto");
  curl_setopt($ch,CURLOPT_POST,1);
  curl_setopt($ch,CURLOPT_POSTFIELDS,$data);
  curl_setopt($ch,CURLOPT_RETURNTRANSFER,1);
  curl_exec($ch); curl_close($ch);
}

/* ================= DASHBOARD (ADMIN WEB) ================= */
if ($_SERVER["REQUEST_METHOD"] === "GET") {

  $users = load($F["users"]);
  $products = load($F["products"]);
  $orders = load($F["orders"]);

  echo '
<!DOCTYPE html>
<html>
<head>
<title>Marketplace Dashboard</title>
<style>
body{font-family:Arial;background:#0f172a;color:#fff;margin:0;padding:20px}
h1{margin-bottom:20px}
.cards{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:20px}
.card{background:#111827;padding:20px;border-radius:12px;box-shadow:0 0 20px #000}
.card h2{margin:0;font-size:32px;color:#38bdf8}
.card p{margin:5px 0 0;color:#94a3b8}
.footer{margin-top:40px;color:#64748b}
</style>
</head>
<body>
<h1>📊 Admin Dashboard</h1>
<div class="cards">
  <div class="card"><h2>'.count($users).'</h2><p>Users</p></div>
  <div class="card"><h2>'.count($products).'</h2><p>Products</p></div>
  <div class="card"><h2>'.count($orders).'</h2><p>Orders</p></div>
</div>
<div class="footer">Marketplace Bot – Admin Panel</div>
</body>
</html>';
  exit;
}

/* ================= UPDATE ================= */
$update=json_decode(file_get_contents("php://input"),true);
if(!$update) exit;

/* ================= MESSAGE ================= */
if(isset($update["message"])){

  $m=$update["message"];
  $id=$m["chat"]["id"];
  $text=$m["text"]??"";

  $users=load($F["users"]);
  if(!isset($users[$id])){
    $users[$id]=["wallet"=>0];
    save($F["users"],$users);
  }

  $isAdmin = ($id==$ADMIN_ID);

  /* START / MENU */
  if($text=="/start" || $text=="/menu"){
    send($id,
"🛒 <b>Marketplace Bot</b>

اختر من القائمة 👇",
    ["keyboard"=>[
      ["🛍 المنتجات","➕ إضافة منتج"],
      ["💰 محفظتي","➕ إضافة رصيد"],
      ["ℹ️ المساعدة"]
    ],
    "resize_keyboard"=>true]);
    exit;
  }

  /* WALLET */
  if($text=="💰 محفظتي"){
    send($id,"💰 رصيدك الحالي: <b>{$users[$id]['wallet']}</b>");
    exit;
  }

  /* ADD BALANCE */
  if($text=="➕ إضافة رصيد"){
    send($id,
"💳 <b>إضافة رصيد</b>

أرسل المبلغ بالشكل:
<code>/topup 100</code>

سيتم مراجعته من الإدارة");
    exit;
  }

  if(strpos($text,"/topup")===0){
    $amount = intval(explode(" ",$text)[1] ?? 0);
    if($amount<=0){ send($id,"❌ مبلغ غير صحيح"); exit; }

    send($ADMIN_ID,
"💳 طلب شحن رصيد
👤 User: <code>$id</code>
💰 Amount: $amount");

    send($id,"⏳ تم إرسال طلب الشحن للإدارة");
    exit;
  }

  /* SHOP (LIST VIEW) */
  if($text=="🛍 المنتجات"){
    $products=load($F["products"]);
    if(!$products){ send($id,"📭 لا توجد منتجات"); exit; }

    foreach($products as $p){
      send($id,
"📦 <b>{$p['name']}</b>
💰 {$p['price']}",
      ["inline_keyboard"=>[
        [
          ["text"=>"👁️ الصورة","callback_data"=>"img_".$p["id"]],
          ["text"=>"🛒 شراء","callback_data"=>"buy_".$p["id"]]
        ]
      ]]);
    }
    exit;
  }

  /* ADD PRODUCT */
  if($text=="➕ إضافة منتج"){
    send($id,"📦 أرسل:\n<code>الاسم | السعر | الوصف</code>");
    exit;
  }

  if(substr_count($text,"|")==2){
    [$name,$price,$desc]=array_map("trim",explode("|",$text));
    $pending=load($F["pending"]);
    $pid=time();
    $pending[]=[
      "id"=>$pid,"seller"=>$id,
      "name"=>$name,"price"=>$price,
      "desc"=>$desc,"photo"=>null
    ];
    save($F["pending"],$pending);

    send($id,"🖼 أرسل صورة المنتج");

    send($ADMIN_ID,
"🆕 منتج جديد
📦 $name
💰 $price
/approve $pid");
    exit;
  }

  if(isset($m["photo"])){
    $pending=load($F["pending"]);
    $last=array_key_last($pending);
    if($pending[$last]["seller"]==$id){
      $pending[$last]["photo"]=$m["photo"][0]["file_id"];
      save($F["pending"],$pending);
      send($id,"⏳ في انتظار موافقة الإدارة");
    }
    exit;
  }

  if($isAdmin && strpos($text,"/approve")===0){
    $pid=explode(" ",$text)[1];
    $pending=load($F["pending"]);
    foreach($pending as $k=>$p){
      if($p["id"]==$pid){
        $products=load($F["products"]);
        $products[]=$p;
        save($F["products"],$products);
        unset($pending[$k]);
        save($F["pending"],array_values($pending));
        send($p["seller"],"🎉 تم قبول منتجك");
      }
    }
    exit;
  }
}

/* ================= CALLBACK ================= */
if(isset($update["callback_query"])){

  $cb=$update["callback_query"];
  $id=$cb["from"]["id"];
  $data=$cb["data"];

  /* SHOW IMAGE */
  if(strpos($data,"img_")===0){
    $pid=str_replace("img_","",$data);
    $products=load($F["products"]);
    foreach($products as $p){
      if($p["id"]==$pid){
        sendPhotoMsg($id,$p["photo"],
"📦 {$p['name']}
💰 {$p['price']}");
      }
    }
    exit;
  }

  /* BUY */
  if(strpos($data,"buy_")===0){
    $pid=str_replace("buy_","",$data);
    $products=load($F["products"]);
    $users=load($F["users"]);
    $orders=load($F["orders"]);

    foreach($products as $p){
      if($p["id"]==$pid){
        $fee=$p["price"]*$COMMISSION;
        $users[$p["seller"]]["wallet"]+=($p["price"]-$fee);
        $users[$ADMIN_ID]["wallet"]+=$fee;
        save($F["users"],$users);

        $orders[]=["product"=>$pid,"buyer"=>$id,"status"=>"pending"];
        save($F["orders"],$orders);

        send($id,"✅ تم إنشاء الطلب");
        send($p["seller"],"📦 تم بيع منتجك");
        send($ADMIN_ID,"💰 عملية بيع جديدة – عمولة $fee");
      }
    }
    exit;
  }
}
