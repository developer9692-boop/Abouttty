<?php

// ================= CONFIG =================
define('BOT_TOKEN', '8669859381:AAEXATKTVgmuOHg1Jg9hQ84YBjq9L7Z6G28');
define('API_URL', 'https://api.telegram.org/bot' . BOT_TOKEN . '/');

define('OWNER_ID', 6808803040);
define('ADMIN_PASSWORD', 'Rimjhim');

$CHANNELS = ["@ERRORARMY1", "@SELLANYTHING4"];

// ================= SERVICES =================
$services = [
    "Followers" => ["name"=>"👤 Followers","base"=>100,"cost"=>10],
    "Likes" => ["name"=>"❤️ Likes","base"=>100,"cost"=>5],
    "Views" => ["name"=>"👁 Views","base"=>1000,"cost"=>2],
    "Shares" => ["name"=>"🔁 Shares","base"=>1000,"cost"=>5],
    "Comments" => ["name"=>"💬 Comments","base"=>100,"cost"=>3]
];

// ================= DATABASE =================
function db() {
    static $db;
    if (!$db) {
        $db = new SQLite3('bot.db');

        $db->exec("CREATE TABLE IF NOT EXISTS users (
            user_id INTEGER PRIMARY KEY,
            credits INTEGER DEFAULT 2,
            referral_id INTEGER,
            step TEXT,
            is_verified INTEGER DEFAULT 0
        )");

        $db->exec("CREATE TABLE IF NOT EXISTS orders (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER,
            service_name TEXT,
            quantity INTEGER,
            link TEXT,
            cost INTEGER,
            status TEXT
        )");

        $db->exec("CREATE TABLE IF NOT EXISTS redeem_codes (
            code TEXT PRIMARY KEY,
            reward_value INTEGER,
            max_uses INTEGER,
            current_uses INTEGER DEFAULT 0
        )");
    }
    return $db;
}

// ================= BOT =================
function bot($method, $data = []) {
    $ch = curl_init(API_URL . $method);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $data
    ]);
    $res = curl_exec($ch);
    curl_close($ch);
    return json_decode($res, true);
}

// ================= USER =================
function getUser($id) {
    $db = db();
    $u = $db->querySingle("SELECT * FROM users WHERE user_id=$id", true);
    if (!$u) {
        $db->exec("INSERT INTO users (user_id) VALUES ($id)");
        return getUser($id);
    }
    return $u;
}

function setStep($id, $step) {
    db()->exec("UPDATE users SET step='$step' WHERE user_id=$id");
}

// ================= VERIFY =================
function isJoined($user_id) {
    global $CHANNELS;
    foreach ($CHANNELS as $ch) {
        $res = bot('getChatMember', [
            'chat_id' => $ch,
            'user_id' => $user_id
        ]);
        if (!isset($res['result']['status']) || $res['result']['status'] == 'left') {
            return false;
        }
    }
    return true;
}

// ================= JOIN MENU =================
function joinMenu($chat_id) {
    bot('sendMessage', [
        'chat_id' => $chat_id,
        'text' => "✨ Welcome to Free Astro!\n\nJoin channels first.",
        'reply_markup' => json_encode([
            'inline_keyboard' => [
                [
                    ['text' => "Join Channel 1 🌐", 'url' => "https://t.me/ERRORARMY1"],
                    ['text' => "Join Channel 2 🌐", 'url' => "https://t.me/SELLANYTHING4"]
                ],
                [
                    ['text' => "Verify ✅", 'callback_data' => "verify"]
                ]
            ]
        ])
    ]);
}
// ================= MAIN MENU =================
function mainMenu($chat_id){
    global $user;

    $credits = $user['credits'];

    $text = "✨ <b>Welcome to Free Astro</b> ✨
━━━━━━━━━━━━━━━━━━

👤 <b>User ID:</b> <code>$chat_id</code>
💰 <b>Credits:</b> $credits

🚀 <b>Your Trusted Provider</b>

🌐 <b>Website:</b>
https://astrropulse.vercel.app/

━━━━━━━━━━━━━━━━━━
💎 <i>Earn • Order • Grow</i>";

    bot('sendMessage',[
        'chat_id' => $chat_id,
        'text' => $text,
        'parse_mode' => "HTML",
        'reply_markup' => json_encode([
            'keyboard' => [
                ["🛒 Order Now"],
                ["🎁 Redeem"],
                ["👨‍💻 Contact Owner"]
            ],
            'resize_keyboard' => true
        ])
    ]);
}
// ================= UPDATE =================
$update = json_decode(file_get_contents("php://input"), true);

// ================= CALLBACK =================
if(isset($update['callback_query'])){
    $cb = $update['callback_query'];
    $user_id = $cb['from']['id'];
    $chat_id = $cb['message']['chat']['id'];

    if($cb['data']=="verify"){
        if(isJoined($user_id)){
            db()->exec("UPDATE users SET is_verified=1 WHERE user_id=$user_id");
            bot('sendMessage',['chat_id'=>$chat_id,'text'=>"✅ Verified"]);
            $user = getUser($user_id);
            mainMenu($chat_id);
        }else{
            bot('answerCallbackQuery',['callback_query_id'=>$cb['id'],'text'=>"Join channels first",'show_alert'=>true]);
        }
    }
    exit;
}

// ================= USER MESSAGE HANDLER =================

$msg      = $update['message'] ?? null;

// Basic message data
$text     = $msg['text'] ?? '';
$chat_id  = $msg['chat']['id'] ?? 0;
$user_id  = $msg['from']['id'] ?? 0;

// User data from database

$user = getUser($user_id); 

if(!$user['is_verified']){
    joinMenu($chat_id);
    exit;
}

// ================= START =================
if(strpos($text,"/start")===0){
    if(!$user['is_verified']){
        joinMenu($chat_id);
        exit;
    }
    mainMenu($chat_id);
}

// ================= BACK =================
if($text=="🔙 Back"){
    setStep($user_id,"");
    mainMenu($chat_id);
}

// ================= ORDER =================
if($text=="🛒 Order Now"){
    setStep($user_id,"service");

    bot('sendMessage',[
        'chat_id'=>$chat_id,
        'text'=>"Select Service",
        'reply_markup'=>json_encode([
            'keyboard'=>[
                ["Followers","Likes"],
                ["Views","Shares"],
                ["Comments"],
                ["🔙 Back"]
            ],
            'resize_keyboard'=>true
        ])
    ]);
}

// ================= SERVICE SELECT =================
if(isset($services[$text])){
    $s = $services[$text];

    setStep($user_id, "qty:$text");

    bot('sendMessage', [
        'chat_id' => $chat_id,
        'text' => "✨ Service Selected

📦 {$s['name']}
💰 {$s['cost']} Credits per {$s['base']}

Minimum: 100

Enter Quantity:",
        'reply_markup' => json_encode([
            'keyboard' => [["🔙 Back"]],
            'resize_keyboard' => true
        ])
    ]);
}

// ================= QUANTITY =================
if(strpos($user['step'], "qty:") === 0){
    $service = explode(":", $user['step'])[1];

    $qty = intval($text);

    if($qty < 100){
        bot('sendMessage',['chat_id'=>$chat_id,'text'=>"❌ Minimum is 100"]);
        exit;
    }

    $s = $services[$service];

   $units = ceil($qty / $s['base']);
   $cost = $units * $s['cost'];

    setStep($user_id, "confirm:$service:$qty:$cost");

    bot('sendMessage',[
        'chat_id'=>$chat_id,
        'text'=>"📊 Order Summary

Service: {$s['name']}
Qty: $qty
Cost: $cost Credits

Confirm?",
        'reply_markup'=>json_encode([
            'keyboard'=>[
                ["✅ Confirm Order"],
                ["🔙 Back"]
            ],
            'resize_keyboard'=>true
        ])
    ]);
}

// ================= CONFIRM =================
if($text=="✅ Confirm Order" && strpos($user['step'],"confirm:")===0){
    list(,$service,$qty,$cost)=explode(":",$user['step']);

    if($user['credits'] < $cost){
        bot('sendMessage',['chat_id'=>$chat_id,'text'=>"❌ Not enough credits"]);
        exit;
    }

    db()->exec("UPDATE users SET credits=credits-$cost WHERE user_id=$user_id");

    setStep($user_id,"link:$service:$qty:$cost");

    bot('sendMessage',['chat_id'=>$chat_id,'text'=>"Send Link"]);
}

// ================= PLACE ORDER =================
if (strpos($user['step'], "link:") === 0) {

    list(, $service, $qty, $cost) = explode(":", $user['step']);

    // ✅ Validate link
    if (!filter_var($text, FILTER_VALIDATE_URL)) {
        bot('sendMessage', [
            'chat_id' => $chat_id,
            'text' => "❌ Send valid link"
        ]);
        exit;
    }

    // ✅ Secure input
    $link = SQLite3::escapeString($text);

    // Save order
    db()->exec("INSERT INTO orders (user_id, service_name, quantity, link, cost, status)
    VALUES ($user_id, '$service', $qty, '$link', $cost, 'Pending')");

    $oid = db()->lastInsertRowID();

    // OWNER MESSAGE
    $ownerText = "🚀 <b>New Order Received</b>
━━━━━━━━━━━━━━━━━━

🆔 <b>Order ID:</b> <code>$oid</code>
👤 <b>User ID:</b> <code>$user_id</code>

📦 <b>Service:</b> $service
🔢 <b>Quantity:</b> $qty
🔗 <b>Link:</b>
<code>$link</code>

💰 <b>Cost:</b> $cost
📊 <b>Status:</b> Pending

━━━━━━━━━━━━━━━━━━
⚡ Use: <code>/done $oid</code>";

    bot('sendMessage', [
        'chat_id' => OWNER_ID,
        'text' => $ownerText,
        'parse_mode' => "HTML"
    ]);

    // USER MESSAGE
    $userText = "✅ <b>Order Placed Successfully</b>

🆔 Order ID: <code>$oid</code>
📦 Service: $service
🔢 Quantity: $qty
💰 Cost: $cost

⏳ Status: Pending";

    bot('sendMessage', [
        'chat_id' => $chat_id,
        'text' => $userText,
        'parse_mode' => "HTML"
    ]);

    setStep($user_id, "");
}

// ================= REDEEM =================

// Step 1: Click button
if($text=="🎁 Redeem"){
    setStep($user_id,"redeem");

    bot('sendMessage',[
        'chat_id'=>$chat_id,
        'text'=>"🎟 <b>Redeem Code</b>

Enter your code below:",
        'parse_mode'=>"HTML",
        'reply_markup'=>json_encode([
            'keyboard'=>[
                ["🔙 Back"]
            ],
            'resize_keyboard'=>true
        ])
    ]);
    exit;
}


// Step 2: Process code
if($user['step']=="redeem"){

    // 🔒 Secure input
    $code = SQLite3::escapeString(trim($text));

    // Check code
    $c = db()->querySingle("SELECT * FROM redeem_codes WHERE code='$code'", true);

    if($c){

        if($c['current_uses'] >= $c['max_uses']){
            bot('sendMessage',[
                'chat_id'=>$chat_id,
                'text'=>"❌ Code Expired"
            ]);
        } else {

            // ✅ Add credits
            db()->exec("UPDATE users SET credits = credits + {$c['reward_value']} WHERE user_id = $user_id");

            // ✅ Update usage
            db()->exec("UPDATE redeem_codes SET current_uses = current_uses + 1 WHERE code = '$code'");

            bot('sendMessage',[
                'chat_id'=>$chat_id,
                'text'=>"✅ <b>Redeemed Successfully!</b>

💰 Credits Added: {$c['reward_value']}

Use /start to refresh balance",
                'parse_mode'=>"HTML"
            ]);
        }

    } else {
        bot('sendMessage',[
            'chat_id'=>$chat_id,
            'text'=>"❌ Invalid Code"
        ]);
    }

    // reset step
    setStep($user_id,"");
} 

// ================= CONTACT =================
if($text=="👨‍💻 Contact Owner"){
    setStep($user_id,"contact");
    bot('sendMessage',['chat_id'=>$chat_id,'text'=>"Send Message"]);
}

if($user['step']=="contact"){

    setStep($user_id,""); // fix spam issue

    bot('forwardMessage',[
        'chat_id'=>OWNER_ID,
        'from_chat_id'=>$chat_id,
        'message_id'=>$msg['message_id']
    ]);

    bot('sendMessage',[
        'chat_id'=>$chat_id,
        'text'=>"✅ Sent"
    ]);
}

// ================= ADMIN LOGIN =================
if($text=="/admin" && $user_id==OWNER_ID){
    setStep($user_id,"admin_pass");
    bot('sendMessage',[
        'chat_id'=>$chat_id,
        'text'=>"🔐 Enter Admin Password:"
    ]);
    exit;
}

if($user['step']=="admin_pass"){
    if($text==ADMIN_PASSWORD){
        setStep($user_id,"admin");

        bot('sendMessage',[
            'chat_id'=>$chat_id,
            'text'=>"👑 Admin Panel",
            'reply_markup'=>json_encode([
                'keyboard'=>[
                    ["📊 Stats","👥 Users"],
                    ["📦 Orders","🎟 Create Code"],
                    ["📢 Broadcast"]
                ],
                'resize_keyboard'=>true
            ])
        ]);
    } else {
        bot('sendMessage',[
            'chat_id'=>$chat_id,
            'text'=>"❌ Wrong Password"
        ]);
    }
    exit;
}


// ================= ADMIN FEATURES =================

// 📊 Stats
if($text=="📊 Stats" && $user['step']=="admin"){
    $u=db()->querySingle("SELECT COUNT(*) FROM users");
    $o=db()->querySingle("SELECT COUNT(*) FROM orders");

    bot('sendMessage',[
        'chat_id'=>$chat_id,
        'text'=>"📊 Bot Stats

👥 Total Users: $u
📦 Total Orders: $o"
    ]);
    exit;
}


// 👥 Users
if($text=="👥 Users" && $user['step']=="admin"){
    $res=db()->query("SELECT user_id,credits FROM users ORDER BY user_id DESC LIMIT 30");

    $msg="👥 Users List:

";
    while($r=$res->fetchArray()){
        $msg.="🆔 {$r['user_id']} | 💰 {$r['credits']}\n";
    }

    bot('sendMessage',[
        'chat_id'=>$chat_id,
        'text'=>$msg
    ]);
    exit;
}


// 📦 Orders
if($text=="📦 Orders" && $user['step']=="admin"){
    $res=db()->query("SELECT * FROM orders ORDER BY id DESC LIMIT 10");

    $msg="📦 Recent Orders:

";
    while($o=$res->fetchArray()){
        $msg.="🆔 #{$o['id']}
👤 {$o['user_id']}
📦 {$o['service_name']}
🔢 {$o['quantity']}
📊 {$o['status']}

";
    }

    bot('sendMessage',[
        'chat_id'=>$chat_id,
        'text'=>$msg
    ]);
    exit;
}


// 🎟 CREATE CODE (STEP 1)
if($text=="🎟 Create Code" && $user['step']=="admin"){
    setStep($user_id,"code_name");

    bot('sendMessage',[
        'chat_id'=>$chat_id,
        'text'=>"🎟 Send Code Name:"
    ]);
    exit;
}

// STEP 2
if($user['step']=="code_name"){
    setStep($user_id,"code_value:$text");

    bot('sendMessage',[
        'chat_id'=>$chat_id,
        'text'=>"💰 Enter Credit Value:"
    ]);
    exit;
}

// STEP 3
if(strpos($user['step'],"code_value:")===0){
    $code=explode(":",$user['step'])[1];

    if(!is_numeric($text)){
        bot('sendMessage',['chat_id'=>$chat_id,'text'=>"❌ Enter valid number"]);
        exit;
    }

    setStep($user_id,"code_limit:$code:$text");

    bot('sendMessage',[
        'chat_id'=>$chat_id,
        'text'=>"🔢 Enter Max Uses:"
    ]);
    exit;
}

// STEP 4 FINAL
if(strpos($user['step'],"code_limit:")===0){
    list(,$code,$value)=explode(":",$user['step']);

    if(!is_numeric($text)){
        bot('sendMessage',['chat_id'=>$chat_id,'text'=>"❌ Enter valid number"]);
        exit;
    }

    db()->exec("INSERT INTO redeem_codes (code,reward_value,max_uses) VALUES ('$code',$value,$text)");

    bot('sendMessage',[
        'chat_id'=>$chat_id,
        'text'=>"✅ Code Created Successfully!"
    ]);

    setStep($user_id,"admin");
    exit;
}


// 📢 BROADCAST
if($text=="📢 Broadcast" && $user['step']=="admin"){
    setStep($user_id,"broadcast");

    bot('sendMessage',[
        'chat_id'=>$chat_id,
        'text'=>"📢 Send Message to Broadcast:"
    ]);
    exit;
}

if($user['step']=="broadcast"){

    // RESET STEP FIRST (important fix)
    setStep($user_id,"admin");

    $res=db()->query("SELECT user_id FROM users");

    while($u=$res->fetchArray()){
    bot('sendMessage',[
        'chat_id'=>$u['user_id'],
        'text'=>$text
    ]);
    usleep(50000); // anti flood
}

    bot('sendMessage',[
        'chat_id'=>$chat_id,
        'text'=>"✅ Broadcast Sent to All Users"
    ]);

    exit;
}


// ================= DONE COMMAND =================
if (strpos($text, "/done") === 0 && $user_id == OWNER_ID) {

    $id = explode(" ", $text)[1] ?? null;

    if (!$id) {
        bot('sendMessage', [
            'chat_id' => $chat_id,
            'text' => "❌ Use: /done order_id"
        ]);
        exit;
    }

    $o = db()->querySingle("SELECT * FROM orders WHERE id=$id", true);

    if ($o) {

        // Update status
        db()->exec("UPDATE orders SET status='Completed' WHERE id=$id");

        // USER NOTIFICATION (with full details)
        $userMsg = "🎉 <b>Your Order Completed!</b>
━━━━━━━━━━━━━━━━━━

🆔 <b>Order ID:</b> <code>$id</code>
📦 <b>Service:</b> {$o['service_name']}
🔢 <b>Quantity:</b> {$o['quantity']}
🔗 <b>Link:</b>
<code>{$o['link']}</code>

✅ <b>Status:</b> Completed

━━━━━━━━━━━━━━━━━━
💎 Thank you for using our service!";

        bot('sendMessage', [
            'chat_id' => $o['user_id'],
            'text' => $userMsg,
            'parse_mode' => "HTML"
        ]);

        // OWNER CONFIRMATION
        bot('sendMessage', [
            'chat_id' => $chat_id,
            'text' => "✅ Order #$id marked as Completed"
        ]);

    } else {
        bot('sendMessage', [
            'chat_id' => $chat_id,
            'text' => "❌ Order not found"
        ]);
    }

    exit;
}
?>