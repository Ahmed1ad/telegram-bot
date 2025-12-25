<?php
http_response_code(200);

/* ========== CONFIG ========== */
$TOKEN = getenv("BOT_TOKEN");
$ADMIN_ID = 1739124234;
$COMMISSION = 0.10;

/* ========== FILES ========== */
$FILES = [
  "products"=>"products.json",
  "pending"=>"pending.json",
  "users"=>"users.json",
  "orders"=>"orders.json",
  "ratings"=>"ratings.json"
];

/* ========== FUNCTIONS ========== */
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

/* ========== DASHBOARD (WEB) ========== */
if ($_SERVER["REQUEST_METHOD"] === "GET") {
  $users = load($FILES["users"]);
  $products = load($FILES["products"]);
  $orders = load($FILES["orders"]);

  echo "<h2>Admin Dashboard</h2>";
  echo "<p>👤 Users: ".count($users)."</p>";
  echo "<p>📦 Products: ".count($products)."</p>";
  echo "<p>🧾 Orders: ".count($orders)."</p>";
  exit;
}

/* ========== UPDATE ========== */
$update=json_decode(file_get_contents("php://input"),true);
if(!$update) exit;

/* ========== MESSAGE ========== */
if(isset($update["message"])){

  $m=$update["message"];
  $id=$m["chat"]["id"];
  $text=$m["text"]??"";

  $users=load($FILES["users"]);
  if(!isset($users[$id])){
    $users[$id]=["wallet"=>0,"premium"=>false];
    save($FILES["users"],$users);
  }

  /* START */
  if($text=="/start"){
    send($id,
"🛒 <b>Marketplace Bot</b>

✨ بيع واشتري بسهولة
💰 عمولة عادلة
🔐 تواصل آمن

الأوامر:
/shop – تصفح
/add – إضافة منتج
/balance – محفظتي");
    exit;
  }

  /* BALANCE */
  if($text=="/balance"){
    send($id,"💰 <b>رصيدك:</b> {$users[$id]['wallet']}");
    exit;
  }

  /* ADD */
  if($text=="/add"){
    send($id,"📦 أرسل المنتج:\n<code>الاسم | السعر | الوصف</code>");
    exit;
  }

  /* PRODUCT FORMAT */
  if(substr_count($text,"|")==2){
    [$name,$price,$desc]=array_map("trim",explode("|",$text));
    $pending=load($FILES["pending"]);
    $pid=time();
    $pending[]=[
      "id"=>$pid,"seller"=>$id,
      "name"=>$name,"price"=>$price,
      "desc"=>$desc,"photo"=>null
    ];
    save($FILES["pending"],$pending);

    send($id,"🖼 أرسل صورة المنتج");

    send($ADMIN_ID,
"🆕 <b>منتج جديد</b>
📦 $name
💰 $price
👤 Seller: <code>$id</code>
/approve $pid");

    exit;
  }

  /* PHOTO */
  if(isset($m["photo"])){
    $pending=load($FILES["pending"]);
    $last=array_key_last($pending);
    if($pending[$last]["seller"]==$id){
      $pending[$last]["photo"]=$m["photo"][0]["file_id"];
      save($FILES["pending"],$pending);
      send($id,"⏳ في انتظار موافقة الإدارة");
    }
    exit;
  }

  /* SHOP */
  if($text=="/shop"){
    $products=load($FILES["products"]);
    if(!$products){ send($id,"📭 لا توجد منتجات"); exit; }

    foreach($products as $p){
      $kb=["inline_keyboard"=>[
        [["text"=>"🛒 شراء","callback_data"=>"buy_".$p["id"]],
         ["text"=>"💬 تواصل","callback_data"=>"chat_".$p["id"]]]
      ]];
      sendPhotoMsg($id,$p["photo"],
"📦 <b>{$p['name']}</b>
💰 {$p['price']}
📝 {$p['desc']}",$kb);
    }
    exit;
  }

  /* ADMIN APPROVE */
  if($id==$ADMIN_ID && strpos($text,"/approve")===0){
    $pid=explode(" ",$text)[1];
    $pending=load($FILES["pending"]);
    foreach($pending as $k=>$p){
      if($p["id"]==$pid){
        $products=load($FILES["products"]);
        $products[]=$p;
        save($FILES["products"],$products);
        unset($pending[$k]);
        save($FILES["pending"],array_values($pending));
        send($p["seller"],"🎉 تم قبول منتجك");
      }
    }
    exit;
  }
}

/* ========== CALLBACK ========== */
if(isset($update["callback_query"])){

  $cb=$update["callback_query"];
  $id=$cb["from"]["id"];
  $data=$cb["data"];

  /* BUY */
  if(strpos($data,"buy_")===0){
    $pid=str_replace("buy_","",$data);
    $products=load($FILES["products"]);
    $users=load($FILES["users"]);
    $orders=load($FILES["orders"]);

    foreach($products as $p){
      if($p["id"]==$pid){
        $fee=$p["price"]*$COMMISSION;
        $users[$p["seller"]]["wallet"]+=($p["price"]-$fee);
        $users[$ADMIN_ID]["wallet"]+=$fee;
        save($FILES["users"],$users);

        $orders[]=[
          "product"=>$pid,
          "buyer"=>$id,
          "seller"=>$p["seller"],
          "status"=>"pending"
        ];
        save($FILES["orders"],$orders);

        send($id,"✅ تم إنشاء الطلب");
        send($p["seller"],"📦 تم بيع منتجك");
        send($ADMIN_ID,"💰 عملية بيع جديدة – عمولة $fee");
      }
    }
    exit;
  }

  /* CHAT */
  if(strpos($data,"chat_")===0){
    send($id,"🔐 التواصل يتم داخل البوت بدون كشف هوية");
    exit;
  }
}
