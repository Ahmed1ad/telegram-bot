/* ================= DASHBOARD ================= */
if ($_SERVER["REQUEST_METHOD"] === "GET") {

  if(!isset($_GET["admin"]) || $_GET["admin"] !== $DASH_SECRET){
    exit("Access Denied");
  }

  $users  = load($F["users"]);
  $orders = load($F["orders"]);
  $topups = load($F["topups"]);

  /* ===== ACTIONS ===== */

  // قبول شحن
  if(isset($_GET["accept_topup"])){
    $tid = $_GET["accept_topup"];
    foreach($topups as &$t){
      if($t["id"] == $tid && $t["status"]=="pending"){
        $users[$t["user"]]["wallet"] += $t["amount"];
        $t["status"] = "accepted";
        send($t["user"],"✅ تم شحن رصيدك {$t['amount']}");
        logEvent("TOPUP ACCEPTED $tid");
      }
    }
    save($F["users"],$users);
    save($F["topups"],$topups);
    header("Location: ?admin=".$_GET["admin"]);
    exit;
  }

  // رفض شحن
  if(isset($_GET["reject_topup"])){
    $tid = $_GET["reject_topup"];
    foreach($topups as &$t){
      if($t["id"] == $tid){
        $t["status"] = "rejected";
        send($t["user"],"❌ تم رفض طلب الشحن");
        logEvent("TOPUP REJECTED $tid");
      }
    }
    save($F["topups"],$topups);
    header("Location: ?admin=".$_GET["admin"]);
    exit;
  }

  // إضافة رصيد يدوي
  if(isset($_GET["add_balance"])){
    $uid = $_GET["add_balance"];
    $amt = intval($_GET["amount"]);
    $users[$uid]["wallet"] += $amt;
    save($F["users"],$users);
    send($uid,"➕ تم إضافة $amt إلى رصيدك");
    header("Location: ?admin=".$_GET["admin"]);
    exit;
  }

  // خصم رصيد
  if(isset($_GET["remove_balance"])){
    $uid = $_GET["remove_balance"];
    $amt = intval($_GET["amount"]);
    $users[$uid]["wallet"] -= $amt;
    if($users[$uid]["wallet"] < 0) $users[$uid]["wallet"] = 0;
    save($F["users"],$users);
    send($uid,"➖ تم خصم $amt من رصيدك");
    header("Location: ?admin=".$_GET["admin"]);
    exit;
  }

  /* ===== UI ===== */
  echo "<h1>📊 Admin Dashboard</h1>";

  echo "<h2>💳 طلبات شحن الرصيد</h2>";
  foreach($topups as $t){
    if($t["status"]=="pending"){
      echo "
      <div>
        User: {$t['user']} |
        Amount: {$t['amount']}
        <a href='?admin={$_GET['admin']}&accept_topup={$t['id']}'>✅ قبول</a>
        <a href='?admin={$_GET['admin']}&reject_topup={$t['id']}'>❌ رفض</a>
      </div>";
    }
  }

  echo "<h2>👤 المستخدمين</h2>";
  foreach($users as $uid=>$u){
    echo "
    <div>
      {$u['name']} (ID:$uid) |
      Balance: {$u['wallet']}
      <a href='?admin={$_GET['admin']}&add_balance=$uid&amount=100'>➕ 100</a>
      <a href='?admin={$_GET['admin']}&remove_balance=$uid&amount=50'>➖ 50</a>
    </div>";
  }

  exit;
     }
