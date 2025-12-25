<?php
http_response_code(200);
echo "OK";

$TOKEN = getenv("BOT_TOKEN");
$ADMIN_ID = 123456789; // ← حط ID بتاعك
$COMMISSION = 0.10; // 10%

// ملفات البيانات
$FILES = [
    "products" => "products.json",
    "pending"  => "pending.json",
    "users"    => "users.json",
    "orders"   => "orders.json",
    "ratings"  => "ratings.json"
];

function load($f){
    if(!file_exists($f)) file_put_contents($f,"[]");
    return json_decode(file_get_contents($f), true);
}
function save($f,$d){
    file_put_contents($f, json_encode($d, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
}

function send($id,$text,$kb=null){
    global $TOKEN;
    $data = ["chat_id"=>$id,"text"=>$text,"parse_mode"=>"HTML"];
    if($kb) $data["reply_markup"] = json_encode($kb);
    $ch = curl_init("https://api.telegram.org/bot$TOKEN/sendMessage");
    curl_setopt($ch,CURLOPT_POST,true);
    curl_setopt($ch,CURLOPT_POSTFIELDS,$data);
    curl_setopt($ch,CURLOPT_RETURNTRANSFER,true);
    curl_exec($ch);
    curl_close($ch);
}

function sendPhotoMsg($id,$photo,$cap,$kb=null){
    global $TOKEN;
    $data=["chat_id"=>$id,"photo"=>$photo,"caption"=>$cap,"parse_mode"=>"HTML"];
    if($kb) $data["reply_markup"]=json_encode($kb);
    $ch=curl_init("https://api.telegram.org/bot$TOKEN/sendPhoto");
    curl_setopt($ch,CURLOPT_POST,true);
    curl_setopt($ch,CURLOPT_POSTFIELDS,$data);
    curl_setopt($ch,CURLOPT_RETURNTRANSFER,true);
    curl_exec($ch);
    curl_close($ch);
}

// ===================== UPDATE =====================
$update = json_decode(file_get_contents("php://input"), true);
if(!$update) exit;

// ===================== MESSAGE =====================
if(isset($update["message"])){

    $m = $update["message"];
    $id = $m["chat"]["id"];
    $text = $m["text"] ?? "";

    $users = load($FILES["users"]);
    if(!isset($users[$id])){
        $users[$id] = ["wallet"=>0,"verified"=>false];
        save($FILES["users"],$users);
    }

    // START
    if($text=="/start"){
        send($id,
"🛒 <b>بوت المتجر</b>

🧾 الأوامر:
/shop – تصفح
/add – إضافة منتج
/balance – محفظتي
/my – منتجاتي
");
        exit;
    }

    // BALANCE
    if($text=="/balance"){
        send($id,"💰 رصيدك: <b>".$users[$id]["wallet"]."</b>");
        exit;
    }

    // ADD PRODUCT
    if($text=="/add"){
        send($id,"📦 ابعت المنتج بالشكل:\n\nالاسم | السعر | الوصف");
        exit;
    }

    // ADD FORMAT
    if(substr_count($text,"|")==2){
        [$name,$price,$desc]=array_map("trim",explode("|",$text));
        $pending=load($FILES["pending"]);
        $pending[]= [
            "id"=>time(),
            "seller"=>$id,
            "name"=>$name,
            "price"=>$price,
            "desc"=>$desc,
            "photo"=>null
        ];
        save($FILES["pending"],$pending);
        send($id,"✅ المنتج بقى قيد المراجعة – ابعت صورة دلوقتي");
        exit;
    }

    // PHOTO
    if(isset($m["photo"])){
        $pending=load($FILES["pending"]);
        $last=array_key_last($pending);
        if($pending[$last]["seller"]==$id){
            $pending[$last]["photo"]=$m["photo"][0]["file_id"];
            save($FILES["pending"],$pending);
            send($id,"🖼 تم حفظ الصورة – في انتظار موافقة الأدمن");
        }
        exit;
    }

    // SHOP
    if($text=="/shop"){
        $products=load($FILES["products"]);
        if(!$products){ send($id,"📭 لا توجد منتجات"); exit; }

        foreach($products as $p){
            $kb=[
                "inline_keyboard"=>[
                    [["text"=>"💬 تواصل","callback_data"=>"chat_".$p["id"]],
                     ["text"=>"🛒 شراء","callback_data"=>"buy_".$p["id"]]]
                ]
            ];
            sendPhotoMsg(
                $id,
                $p["photo"],
                "📦 <b>{$p['name']}</b>\n💰 {$p['price']}\n📝 {$p['desc']}",
                $kb
            );
        }
        exit;
    }

    // ADMIN APPROVAL
    if($id==$ADMIN_ID && strpos($text,"/approve")===0){
        $pid=trim(explode(" ",$text)[1]);
        $pending=load($FILES["pending"]);
        foreach($pending as $k=>$p){
            if($p["id"]==$pid){
                $products=load($FILES["products"]);
                $products[]=$p;
                save($FILES["products"],$products);
                unset($pending[$k]);
                save($FILES["pending"],array_values($pending));
                send($p["seller"],"🎉 تم قبول منتجك ونشره");
            }
        }
        exit;
    }
}

// ===================== CALLBACK =====================
if(isset($update["callback_query"])){

    $cb=$update["callback_query"];
    $id=$cb["from"]["id"];
    $data=$cb["data"];

    // BUY
    if(strpos($data,"buy_")===0){
        $pid=str_replace("buy_","",$data);
        $products=load($FILES["products"]);
        $users=load($FILES["users"]);

        foreach($products as $p){
            if($p["id"]==$pid){
                $fee=$p["price"]*$COMMISSION;
                $sellerAmount=$p["price"]-$fee;
                $users[$p["seller"]]["wallet"] += $sellerAmount;
                $users[$ADMIN_ID]["wallet"] += $fee;
                save($FILES["users"],$users);
                send($id,"✅ تم الشراء – تواصل مع البائع");
            }
        }
        exit;
    }

    // CHAT
    if(strpos($data,"chat_")===0){
        send($id,"🔐 التواصل يتم عبر البوت فقط – بدون كشف الهوية");
        exit;
    }
}
