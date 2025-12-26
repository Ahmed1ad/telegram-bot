<?php
http_response_code(200);

/* ================= CONFIG ================= */
$TOKEN = getenv("BOT_TOKEN");
$ADMIN_ID = 1739124234;
$COMMISSION = 0.10;                 // 10%
$DASH_SECRET = "ADMIN_SECRET_123";  // غيّره لكلمة سر قوية

/* ================= FILES ================= */
$F = [
  "users"=>"users.json",
  "products"=>"products.json",
  "pending"=>"pending.json",
  "orders"=>"orders.json",
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
function adminOnly($id){
  global $ADMIN_ID;
  if($id != $ADMIN_ID){
    send($id,"❌ هذا الأمر مخصص للإدارة فقط");
    return false;
  }
  return true;
}

/* ================= DASHBOARD (WEB) =================
   افتحه من المتصفح:
   https://YOUR-DOMAIN/?admin=ADMIN_SECRET_123
===================================================== */
if ($_SERVER["REQUEST_METHOD"] === "GET") {
  if(!isset($_GET["admin"]) || $_GET["admin"] !== $DASH_SECRET){
    exit("Access Denied");
  }

  $users = load($F["users"]);
  $products = load($F["products"]);
  $orders = load($F["orders"]);

  // تحديث حالة طلب من الويب
  if(isset($_GET["done"])){
    foreach($orders as &$o){
      if($o["id"] == $_GET["done"]) $o["status"] = "completed";
    }
    save($F["orders"],$orders);
    header("Location: /?admin=".$_GET["admin"]);
    exit;
  }

  // حساب الإحصائيات
  $totalSales = 0;
  $adminProfit = $users[$ADMIN_ID]["wallet"] ?? 0;
  foreach($orders as $o){ $totalSales += ($o["price"] ?? 0); }

  echo "<!DOCTYPE html><html><head><meta charset='utf-8'>
  <title>Admin Dashboard</title>
  <style>
  body{font-family:Arial;background:#0f172a;color:#fff;margin:0;padding:20px}
  h1{margin:0 0 20px}
  .cards{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:16px}
  .card{background:#111827;padding:16px;border-radius:12px}
  .big{font-size:32px;color:#38bdf8}
  table{width:100%;border-collapse:collapse;margin-top:20px}
  th,td{border-bottom:1px solid #1f2937;padding:8px;text-align:left}
  a{color:#38bdf8;text-decoration:none}
  .muted{color:#94a3b8}
  </style></head><body>";

  echo "<h1>📊 Admin Dashboard</h1>
  <div class='cards'>
    <div class='card'><div class='big'>".count($users)."</div><div class='muted'>Users</div></div>
    <div class='card'><div class='big'>".count($products)."</div><div class='muted'>Products</div></div>
    <div class='card'><div class='big'>".count($orders)."</div><div class='muted'>Orders</div></div>
    <div class='card'><div class='big'>".$totalSales."</div><div class='muted'>Total Sales</div></div>
    <div class='card'><div class='big'>".$adminProfit."</div><div class='muted'>Admin Profit</div></div>
  </div>";

  echo "<h2 style='margin-top:30px'>📦 Orders</h2>
  <table><tr><th>ID</th><th>Buyer</th><th>Seller</th><th>Price</th><th>Status</th><th>Action</th></tr>";
  foreach($orders as $o){
    echo "<tr>
      <td>{$o['id']}</td>
      <td>{$o['buyer_name']} ({$o['buyer_id']})</td>
      <td>{$o['seller_id']}</td>
      <td>{$o['price']}</td>
      <td>{$o['status']}</td>
      <td>".($o['status']!='completed' ? "<a href='?admin=".$_GET["admin"]."&done={$o['id']}'>✅ Complete</a>" : "—")."</td>
    </tr>";
  }
  echo "</table></body></html>";
  exit;
}

/* ================= UPDATE ================= */
$update=json_decode(file_get_contents("php://input"),true);
if(!$update) exit;

/* ================= MENUS ================= */
$menu = [
  "keyboard"=>[
    ["🛍 المنتجات","➕ إضافة منتج"],
    ["💰 محفظتي","➕ إضافة رصيد"],
    ["📦 الطلبات","📊 لوحة التحكم"],
    ["ℹ️ المساعدة"]
  ],
  "resize_keyboard"=>true
];

/* ================= MESSAGE ================= */
if(isset($update["message"])){
  $m=$update["message"];
  $id=$m["chat"]["id"];
  $text=$m["text"]??"";
  $from=$m["from"];
  $name=trim(($from["first_name"]??"")." ".($from["last_name"]??""));
  $username=$from["username"]??"";

  $users=load($F["users"]);
  if(!isset($users[$id])){
    $users[$id]=[
      "wallet"=>0,
      "name"=>$name,
      "username"=>$username
    ];
    save($F["users"],$users);
  }

  /* START / MENU */
  if($text=="/start" || $text=="/menu"){
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
    send($id,"💳 أرسل:\n<code>/topup 100</code>\nسيصل الطلب للإدارة.");
    exit;
  }
  if(strpos($text,"/topup")===0){
    $amount=intval(explode(" ",$text)[1]??0);
    if($amount<=0){ send($id,"❌ مبلغ غير صحيح"); exit; }
    send($ADMIN_ID,"💳 طلب شحن\n👤 {$users[$id]['name']} (@{$users[$id]['username']})\n🆔 $id\n💰 $amount");
    send($id,"⏳ تم إرسال طلب الشحن للإدارة");
    exit;
  }

  /* PRODUCTS (LIST VIEW) */
  if($text=="🛍 المنتجات"){
    $products=load($F["products"]);
    if(!$products){ send($id,"📭 لا توجد منتجات"); exit; }
    foreach($products as $p){
      send($id,"📦 <b>{$p['name']}</b>\n💰 {$p['price']}",
      ["inline_keyboard"=>[
        [["text"=>"👁️ الصورة","callback_data"=>"img_".$p["id"]],
         ["text"=>"🛒 شراء","callback_data"=>"buy_".$p["id"]]]
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
    [$nameP,$price,$desc]=array_map("trim",explode("|",$text));
    $pending=load($F["pending"]);
    $pid=time();
    $pending[]=[
      "id"=>$pid,"seller_id"=>$id,
      "name"=>$nameP,"price"=>$price,
      "desc"=>$desc,"photo"=>null
    ];
    save($F["pending"],$pending);
    send($id,"🖼 أرسل صورة المنتج");
    send($ADMIN_ID,"🆕 منتج للمراجعة\n📦 $nameP\n💰 $price\n/approve $pid");
    exit;
  }

  if(isset($m["photo"])){
    $pending=load($F["pending"]);
    $last=array_key_last($pending);
    if(isset($pending[$last]) && $pending[$last]["seller_id"]==$id){
      $pending[$last]["photo"]=$m["photo"][0]["file_id"];
      save($F["pending"],$pending);
      send($id,"⏳ في انتظار موافقة الإدارة");
    }
    exit;
  }

  /* ORDERS (USER) */
  if($text=="📦 الطلبات"){
    $orders=load($F["orders"]);
    $mine=array_filter($orders,fn($o)=>$o["buyer_id"]==$id);
    if(!$mine){ send($id,"لا توجد طلبات"); exit; }
    foreach($mine as $o){
      send($id,"🧾 طلب #{$o['id']}\n💰 {$o['price']}\n🔄 {$o['status']}");
    }
    exit;
  }

  /* ADMIN BUTTONS (VISIBLE للجميع لكن محمية) */
  if($text=="📊 لوحة التحكم"){
    if(!adminOnly($id)) exit;
    send($id,"📊 افتح لوحة التحكم:\nhttps://".$_SERVER["HTTP_HOST"]."/?admin=".$GLOBALS["DASH_SECRET"]);
    exit;
  }

  if(strpos($text,"/approve")===0){
    if(!adminOnly($id)) exit;
    $pid=explode(" ",$text)[1];
    $pending=load($F["pending"]);
    foreach($pending as $k=>$p){
      if($p["id"]==$pid){
        $products=load($F["products"]);
        $products[]=$p;
        save($F["products"],$products);
        unset($pending[$k]);
        save($F["pending"],array_values($pending));
        send($p["seller_id"],"🎉 تم قبول منتجك ونشره");
      }
    }
    exit;
  }

  /* HELP */
  if($text=="ℹ️ المساعدة"){
    send($id,"ℹ️ المساعدة\n• بيع وشراء\n• تواصل آمن\n• الإدارة وسيط");
    exit;
  }
}

/* ================= CALLBACK ================= */
if(isset($update["callback_query"])){
  $cb=$update["callback_query"];
  $id=$cb["from"]["id"];
  $data=$cb["data"];

  if(strpos($data,"img_")===0){
    $pid=str_replace("img_","",$data);
    $products=load($F["products"]);
    foreach($products as $p){
      if($p["id"]==$pid){
        sendPhotoMsg($id,$p["photo"],"📦 {$p['name']}\n💰 {$p['price']}");
      }
    }
    exit;
  }

  if(strpos($data,"buy_")===0){
    $pid=str_replace("buy_","",$data);
    $products=load($F["products"]);
    $users=load($F["users"]);
    $orders=load($F["orders"]);

    foreach($products as $p){
      if($p["id"]==$pid){
        $fee=$p["price"]*$COMMISSION;
        $users[$p["seller_id"]]["wallet"]+=($p["price"]-$fee);
        $users[$ADMIN_ID]["wallet"]+=$fee;
        save($F["users"],$users);

        $oid=time();
        $orders[]=[
          "id"=>$oid,
          "product_id"=>$pid,
          "price"=>$p["price"],
          "buyer_id"=>$id,
          "buyer_name"=>$users[$id]["name"],
          "seller_id"=>$p["seller_id"],
          "status"=>"pending"
        ];
        save($F["orders"],$orders);

        send($id,"✅ تم إنشاء الطلب");
        send($p["seller_id"],"📦 تم بيع منتجك");
        send($ADMIN_ID,"💰 بيع جديد\n🧾 #$oid\n💵 {$p['price']}");
      }
    }
    exit;
  }
}
