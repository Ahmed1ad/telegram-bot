<?php
http_response_code(200);
echo "OK";

/* ========== CONFIG ========== */
$TOKEN = getenv("BOT_TOKEN");
$ADMIN_ID = 1739124234;
$COMMISSION = 0.10;

/* ========== FILES ========== */
$F = [
  "products"=>"products.json",
  "pending"=>"pending.json",
  "users"=>"users.json",
  "orders"=>"orders.json"
];

/* ========== HELPERS ========== */
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

/* ========== UPDATE ========== */
$update=json_decode(file_get_contents("php://input"),true);
if(!$update) exit;

/* ========== MESSAGE ========== */
if(isset($update["message"])){

  $m=$update["message"];
  $id=$m["chat"]["id"];
  $text=$m["text"]??"";

  $users=load($F["users"]);
  if(!isset($users[$id])){
    $users[$id]=["wallet"=>0,"role"=>"user"];
    save($F["users"],$users);
  }

  /* ===== START ===== */
  if($text=="/start"){
    send($id,
"🛒 <b>Marketplace Bot</b>

✨ بيع واشتري بسهولة
🔐 تواصل آمن
💰 نظام عمولة شفاف

اختر من الأوامر 👇

/shop – تصفح المنتجات
/add – إضافة منتج
/balance – محفظتي");
    exit;
  }

  /* ===== BALANCE ===== */
  if($text=="/balance"){
    send($id,"💰 <b>رصيدك الحالي:</b> {$users[$id]['wallet']}");
    exit;
  }

  /* ===== ADD PRODUCT ===== */
  if($text=="/add"){
    send($id,
"➕ <b>إضافة منتج</b>

أرسل البيانات بهذا الشكل:
<code>الاسم | السعر | الوصف</code>

ثم أرسل صورة المنتج 📸");
    exit;
  }

  /* ===== PRODUCT FORMAT ===== */
  if(substr_count($text,"|")==2){
    [$name,$price,$desc]=array_map("trim",explode("|",$text));
    $pending=load($F["pending"]);
    $pid=time();
    $pending[]=[
      "id"=>$pid,
      "seller"=>$id,
      "name"=>$name,
      "price"=>$price,
      "desc"=>$desc,
      "photo"=>null
    ];
    save($F["pending"],$pending);

    send($id,"🕒 تم إرسال المنتج للمراجعة، أرسل الصورة الآن");

    // 🔔 إشعار الأدمن
    send($GLOBALS["ADMIN_ID"],
"🆕 <b>منتج جديد للمراجعة</b>
📦 $name
💰 $price
👤 Seller ID: <code>$id</code>

للموافقة:
/approve $pid");

    exit;
  }

  /* ===== PRODUCT PHOTO ===== */
  if(isset($m["photo"])){
    $pending=load($F["pending"]);
    $last=array_key_last($pending);
    if(isset($pending[$last]) && $pending[$last]["seller"]==$id){
      $pending[$last]["photo"]=$m["photo"][0]["file_id"];
      save($F["pending"],$pending);
      send($id,"📸 تم حفظ الصورة – في انتظار موافقة الإدارة");
    }
    exit;
  }

  /* ===== SHOP ===== */
  if($text=="/shop"){
    $products=load($F["products"]);
    if(!$products){ send($id,"📭 لا توجد منتجات حالياً"); exit; }

    foreach($products as $p){
      $kb=["inline_keyboard"=>[
        [
          ["text"=>"🛒 شراء","callback_data"=>"buy_".$p["id"]],
          ["text"=>"💬 تواصل","callback_data"=>"chat_".$p["id"]]
        ]
      ]];
      sendPhotoMsg($id,$p["photo"],
"📦 <b>{$p['name']}</b>
💰 {$p['price']}
📝 {$p['desc']}",$kb);
    }
    exit;
  }

  /* ===== ADMIN APPROVE ===== */
  if($id==$ADMIN_ID && strpos($text,"/approve")===0){
    $pid=trim(explode(" ",$text)[1]);
    $pending=load($F["pending"]);
    foreach($pending as $k=>$p){
      if($p["id"]==$pid){
        $products=load($F["products"]);
        $products[]=$p;
        save($F["products"],$products);
        unset($pending[$k]);
        save($F["pending"],array_values($pending));
        send($p["seller"],"🎉 تم قبول منتجك ونشره في المتجر");
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

  /* ===== BUY ===== */
  if(strpos($data,"buy_")===0){
    $pid=str_replace("buy_","",$data);
    $products=load($F["products"]);
    $users=load($F["users"]);

    foreach($products as $p){
      if($p["id"]==$pid){
        $fee=$p["price"]*$COMMISSION;
        $sellerAmount=$p["price"]-$fee;

        $users[$p["seller"]]["wallet"]+=$sellerAmount;
        $users[$ADMIN_ID]["wallet"]+=$fee;
        save($F["users"],$users);

        // إشعارات
        send($id,"✅ <b>تم إنشاء الطلب</b>\nسيتم التواصل مع البائع");
        send($p["seller"],"📦 <b>تم بيع منتجك:</b> {$p['name']}");
        send($ADMIN_ID,
"💰 <b>طلب جديد</b>
📦 {$p['name']}
💵 السعر: {$p['price']}
👤 Seller: {$p['seller']}
🧾 عمولة: $fee");

      }
    }
    exit;
  }

  /* ===== CHAT ===== */
  if(strpos($data,"chat_")===0){
    send($id,"🔐 التواصل يتم داخل البوت بدون كشف أي بيانات شخصية");
    exit;
  }
}
