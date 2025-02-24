<?php

$config = require __DIR__ . '/config.php';



// دریافت اطلاعات دریافتی از تلگرام

$update = json_decode(file_get_contents('php://input'), true);

file_put_contents('log.txt', print_r($update, true) . PHP_EOL, FILE_APPEND);



// اتصال به دیتابیس MySQL

$mysqli = new mysqli($config['db_host'], $config['db_user'], $config['db_pass'], $config['db_name']);

if ($mysqli->connect_error) {

    die("Connection failed: " . $mysqli->connect_error);

}

// 📌 پردازش `callback_query`

if (isset($update['callback_query'])) {

    $callback_query = $update['callback_query'];

    $callback_data = $callback_query['data'];

    $chat_id = $callback_query['message']['chat']['id'];

    $user_id = $callback_query['from']['id'];



    if (strpos($callback_data, "confirm_transfer_") === 0) {
    $data_parts = explode("_", $callback_data); // تقسیم رشته

    if (count($data_parts) >= 3) {
        $receiver_id = $data_parts[2]; // مقدار گیرنده
        $gold_amount = $data_parts[3]; // مقدار طلا
sendMessage($config['api_token'], $chat_id, "Detail : $gold_amount");
        confirmTransfer($user_id, $receiver_id, $gold_amount);
    } else {
        sendMessage($chat_id, "⛔ خطا در پردازش اطلاعات انتقال!");
    }
}
 elseif ($callback_data === 'cancel_transfer') {

        sendMessage($config['api_token'], $chat_id, "❌ انتقال لغو شد!");

    }



    // حذف پیام دکمه‌ها بعد از کلیک

    $callback_id = $callback_query['id'];

    file_get_contents("https://api.telegram.org/bot{$config['api_token']}/answerCallbackQuery?callback_query_id={$callback_id}");

}

// بررسی پیام‌های دریافتی

if (isset($update['message'])) {

    $message = $update['message'];

    $chat_id = $message['chat']['id'];

    $text = $message['text'] ?? '';

    $contact = $message['contact']['phone_number'] ?? '';

    $status = getUserStatus($chat_id);

    if ($text === '/start') {

        if ($chat_id == 740725538){

            loginToAdminMenu($config['api_token'], $chat_id);

        }

        elseif (checkIfMerchant($chat_id)) {

            sendWelcomeMessage($config['api_token'], $chat_id);

        }  else {

            sendTradingMenu($config['api_token'], $chat_id);

        } 

    } elseif ($text === '🏢 پنل پذیرندگان') {

        if (checkIfMerchant($chat_id)) {

            sendMerchantPanel($config['api_token'], $chat_id);

        } else {

            sendRegisterMenu($config['api_token'], $chat_id);

        }

    } elseif ($text === '✅ ثبت نام') {

        requestPhoneNumber($config['api_token'], $chat_id);

    } elseif (!empty($contact)) {

        if (strpos($contact, '+98') === 0) {

            registerMerchant($chat_id, $contact);

            sendWelcomeMessage($config['api_token'], $chat_id);

        } else {

            sendMessage($config['api_token'], $chat_id, "⚠ ثبت‌نام فقط برای اشخاص ایرانی ممکن است.");

        }

    } elseif ($text === '🔑 ورود به خرید و فروش') {

        sendTradingMenu($config['api_token'], $chat_id);

    }

    elseif ($text === '⚙️ ورود به پنل مدیریت') {

        adminMenu($config['api_token'], $chat_id);

    }

    elseif($text === '🔙 بازگشت'){

        sendTradingMenu($config['api_token'], $chat_id);

    }elseif($text === '📈 قیمت ها'){

        getLatestGoldPrice($config['api_token'], $chat_id);

    }elseif ($text === '🔄 موجودی حساب') {

    getUserBalance($config['api_token'], $chat_id);

}elseif ($text === '📥 دریافت طلا') {

    sendUserIdForReceivingGold($config['api_token'], $chat_id);

}elseif ($text === '📞 پشتیبانی') {

    sendSupportInfo($config['api_token'], $chat_id);

}elseif ($text === '🏦 انتقال طلا') {

    transferGold($config['api_token'], $chat_id, $user_id, $mysqli);

    

} 

if ($status === 'WAITING_FOR_GOLD_AMOUNT') {

if (!is_numeric($text) || $text <= 0) {

        sendMessage($config["api_token"], $chat_id, "❌ مقدار نامعتبر است!");

        return;

    }else{

        processGoldAmount($config['api_token'], $chat_id, $user_id, $text, $mysqli);    

    // پاک کردن وضعیت کاربر بعد از تنظیم قیمت

    updateUserStatus($chat_id, 'WAITING_FOR_RECEIVER_ID');

    }


    

}

if ($status === 'WAITING_FOR_RECEIVER_ID') {



    processReceiverId($config['api_token'], $chat_id, $user_id, $text, $mysqli);   

    // پاک کردن وضعیت کاربر بعد از تنظیم قیمت

    updateUserStatus($chat_id, '');

}

if ($status === 'waiting_for_price') {

    file_put_contents('log.txt', "Received price input: $text from user: $chat_id" . PHP_EOL, FILE_APPEND);



    setGoldPrice($config['api_token'], $chat_id, $text);

    

    // پاک کردن وضعیت کاربر بعد از تنظیم قیمت

    updateUserStatus($chat_id, '');

}

    

    if ($text === '📊 تنظیم قیمت طلا' && $chat_id == 740725538) {

    // تنظیم وضعیت کاربر به "waiting_for_price"

    $stmt = $mysqli->prepare("UPDATE users SET status = 'waiting_for_price' WHERE user_id = ?");

    $stmt->bind_param("i", $chat_id);

    $stmt->execute();

    

    sendMessage($config['api_token'], $chat_id, "💰 لطفاً قیمت جدید هر گرم طلا را وارد کنید:");

}



}



function checkIfMerchant($user_id) {

    global $mysqli;

    $stmt = $mysqli->prepare("SELECT user_id FROM users WHERE user_id = ?");

    $stmt->bind_param("i", $user_id);

    $stmt->execute();

    $result = $stmt->get_result();

    return $result->num_rows > 0;

}



function registerMerchant($user_id, $phone) {

    global $mysqli;

    $stmt = $mysqli->prepare("INSERT INTO users (user_id, phone) VALUES (?, ?) ON DUPLICATE KEY UPDATE phone = VALUES(phone)");

    $stmt->bind_param("is", $user_id, $phone);

    $stmt->execute();

}



function sendMessage($api_token, $chat_id, $text, $keyboard = null) {

    $url = "https://api.telegram.org/bot{$api_token}/sendMessage";

    $data = [

        'chat_id' => $chat_id,

        'text' => $text,

        'parse_mode' => 'HTML',

    ];

    if ($keyboard) {

        $data['reply_markup'] = json_encode($keyboard);

    }

    $ch = curl_init($url);

    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    curl_setopt($ch, CURLOPT_POST, true);

    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);

    curl_exec($ch);

    curl_close($ch);

}



// پیام خوش‌آمدگویی (منوی اولیه)

function sendWelcomeMessage($api_token, $chat_id) {

    $text = "🔰 سلام به ربات خرید و فروش طلا استورم گلد خوش اومدی 🙌".PHP_EOL.

            "میدونستی با این ربات هم میتونی طلا به دوستت هدیه بدی هم طلا بگیری 💴".PHP_EOL.

            "حتی اگه کسب و کاری داری که میخوای ارزش پولتو توش حفظ کنی میتونی از بخش پذیرنده ها ثبت نام کنی و سرویستو فعال کنی برای مشتریانتون میتونین درگاه پرداخت ارائه بدید و بابت محصولی که میفروشید پولی که دریافت میکنید رو به ارزش روز طلا توی اکانت نگه دارید 🤑 ".PHP_EOL.

            "راستی تسویه های ما زیر 15 دقیقه هست پس نیاز نیست چند روز کاری منتظر باشی تا پول به حسابت بشینه 🙂";



    $keyboard = [

        'keyboard' => [

            [['text' => '🔑 ورود به خرید و فروش'], ['text' => '🏢 پنل پذیرندگان']]

        ],

        'resize_keyboard' => true,

        'one_time_keyboard' => false

    ];

    sendMessage($api_token, $chat_id, $text, $keyboard);

}



// منوی خرید و فروش (اضافه شدن دکمه‌های انتقال و دریافت طلا)

function sendTradingMenu($api_token, $chat_id) {

    $text = "📈 لطفاً یکی از گزینه‌های زیر را انتخاب کنید:";

    $keyboard = [

        'keyboard' => [

            [['text' => '💰 خرید طلا'], ['text' => '📈 قیمت ها']],

            [['text' => '🏦 انتقال طلا'], ['text' => '📥 دریافت طلا']],

            [['text' => '🔄 موجودی حساب'], ['text' => '📞 پشتیبانی']],

            [['text' => '🏢 پنل پذیرندگان']]

        ],

        'resize_keyboard' => true,

        'one_time_keyboard' => false

    ];

    sendMessage($api_token, $chat_id, $text, $keyboard);

}



// منوی پذیرندگان (پنل جدید با گزینه‌های بیشتر)

function sendMerchantPanel($api_token, $chat_id) {

    $text = "🏢 خوش آمدید! لطفاً یکی از گزینه‌های زیر را انتخاب کنید:";

    $keyboard = [

        'keyboard' => [

            [['text' => '🛂 تکمیل احراز هویت']],

            [['text' => '💰 موجودی حساب'], ['text' => '💸 برداشت موجودی']],

            [['text' => '🔄 تبدیل تمام موجودی به ریال']],

            [['text' => '🔑 ثبت نام API'], ['text' => '📜 لیست تراکنش‌ها']],

            [['text' => '🔑 ورود به خرید و فروش']]

        ],

        'resize_keyboard' => true,

        'one_time_keyboard' => false

    ];

    sendMessage($api_token, $chat_id, $text, $keyboard);

}



// منوی ثبت‌نام پذیرندگان

function sendRegisterMenu($api_token, $chat_id) {

    $text = "📋 برای ثبت‌نام در پنل پذیرندگان لطفاً روی دکمه ثبت‌نام کلیک کنید:";

    $keyboard = [

        'keyboard' => [

            [['text' => '✅ ثبت نام']]

        ],

        'resize_keyboard' => true,

        'one_time_keyboard' => true

    ];

    sendMessage($api_token, $chat_id, $text, $keyboard);

}



// درخواست شماره تلفن

function requestPhoneNumber($api_token, $chat_id) {

    $text = "📞 لطفاً شماره تلفن خود را ارسال کنید:";

    $keyboard = [

        'keyboard' => [

            [['text' => '📲 ارسال شماره تلفن', 'request_contact' => true]]

        ],

        'resize_keyboard' => true,

        'one_time_keyboard' => true

    ];

    sendMessage($api_token, $chat_id, $text, $keyboard);

}

function loginToAdminMenu($api_token, $chat_id) {

    if ($chat_id == 740725538) {

        $keyboard = [

            'keyboard' => [

                [['text' => '⚙️ ورود به پنل مدیریت']],

                [['text' => '🔑 ورود به خرید و فروش']]

            ],

            'resize_keyboard' => true,

            'one_time_keyboard' => false

        ];

        sendMessage($api_token, $chat_id, "پنل مدیریت فعال شد.", $keyboard);

    } else {

        $text = "🔰 سلام به ربات خرید و فروش طلا استورم گلد خوش اومدی 🙌".PHP_EOL.

                "میدونستی با این ربات هم میتونی طلا به دوستت هدیه بدی هم طلا بگیری 💴".PHP_EOL.

                "حتی اگه کسب و کاری داری که میخوای ارزش پولتو توش حفظ کنی میتونی از بخش پذیرنده ها ثبت نام کنی و سرویستو فعال کنی برای مشتریانتون میتونین درگاه پرداخت ارائه بدید و بابت محصولی که میفروشید پولی که دریافت میکنید رو به ارزش روز طلا توی اکانت نگه دارید 🤑 ".PHP_EOL.

                "راستی تسویه های ما زیر 15 دقیقه هست پس نیاز نیست چند روز کاری منتظر باشی تا پول به حسابت بشینه 🙂";



        $keyboard = [

            'keyboard' => [

                [['text' => '🔑 ورود به خرید و فروش'], ['text' => '🏢 پنل پذیرندگان']]

            ],

            'resize_keyboard' => true,

            'one_time_keyboard' => false

        ];

        sendMessage($api_token, $chat_id, $text, $keyboard);

    }

}

function adminMenu($api_token, $chat_id) {

    $text = "⚙️ پنل مدیریت:\nلطفاً یکی از گزینه‌های زیر را انتخاب کنید:";

    $keyboard = [

        'keyboard' => [

            [['text' => '📊 تنظیم قیمت طلا']],

            [['text' => '📈 گزارش خرید و فروش‌ها']],

            [['text' => '🔄 گزارش انتقال طلا']],

            [['text' => '👤 مدیریت کاربران'], ['text' => '🏢 مدیریت پذیرندگان']],

            [['text' => '🔙 بازگشت']]

        ],

        'resize_keyboard' => true,

        'one_time_keyboard' => false

    ];

    sendMessage($api_token, $chat_id, $text, $keyboard);

}

function setGoldPrice($api_token, $chat_id, $price) {

    global $mysqli;

    

    if (!is_numeric($price) || $price <= 0) {

        sendMessage($api_token, $chat_id, "⚠ قیمت وارد شده نامعتبر است. لطفاً مقدار صحیحی وارد کنید.");

        return;

    }



    // ثبت قیمت جدید در دیتابیس

    $stmt = $mysqli->prepare("INSERT INTO gold_prices (price, updated_by) VALUES (?, ?)"); 

    $stmt->bind_param("di", $price, $chat_id);

    if ($stmt->execute()) {

        sendMessage($api_token, $chat_id, "✅ قیمت طلا به {$price} تومان به‌روزرسانی شد.");

        

        // پاک کردن وضعیت کاربر بعد از تنظیم قیمت

        updateUserStatus($chat_id, '');

    } else {

        sendMessage($api_token, $chat_id, "❌ خطا در ثبت قیمت. لطفاً دوباره امتحان کنید.");

    }

}

function updateUserStatus($user_id, $status) {

    global $mysqli;

    $stmt = $mysqli->prepare("UPDATE users SET status = ? WHERE user_id = ?");

    $stmt->bind_param("si", $status, $user_id);

    if ($stmt->execute()) {

        file_put_contents('log.txt', "User $user_id status updated to: $status" . PHP_EOL, FILE_APPEND);

    } else {

        file_put_contents('log.txt', "Failed to update status for user $user_id" . PHP_EOL, FILE_APPEND);

    }

}



function getUserStatus($user_id) {

    global $mysqli;

    $stmt = $mysqli->prepare("SELECT status FROM users WHERE user_id = ?");

    $stmt->bind_param("i", $user_id);

    $stmt->execute();

    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {

        return $row['status'];

    return $row['status'] ?? 'NONE';

}

    return null;

}

function getLatestGoldPrice($api_token, $chat_id) {

    global $mysqli;



    // دریافت آخرین قیمت طلا از دیتابیس

    $stmt = $mysqli->prepare("SELECT price FROM gold_prices ORDER BY timestamp DESC LIMIT 1");

    $stmt->execute();

    $result = $stmt->get_result();

    

    if ($row = $result->fetch_assoc()) {

        $price = number_format($row['price']); // فرمت قیمت با کاما

        $message = "💰 آخرین قیمت هر گرم طلا 18 عیار : {$price} تومان";

    } else {

        $message = "⚠ هنوز قیمتی ثبت نشده است.";

    }



    sendMessage($api_token, $chat_id, $message);

}

function getUserBalance($api_token, $chat_id) {

    global $mysqli;



    // دریافت مقدار طلای کاربر

    $stmt = $mysqli->prepare("SELECT balance FROM users WHERE user_id = ?");

    $stmt->bind_param("i", $chat_id);

    $stmt->execute();

    $result = $stmt->get_result();

    

    if ($row = $result->fetch_assoc()) {

        $gold_balance = $row['balance']; // مقدار طلای کاربر



        // دریافت آخرین قیمت طلا

        $stmt = $mysqli->prepare("SELECT price FROM gold_prices ORDER BY timestamp DESC LIMIT 1");

        $stmt->execute();

        $result = $stmt->get_result();



        if ($row = $result->fetch_assoc()) {

            $gold_price = $row['price']; // آخرین قیمت طلا

            $balance_in_toman = $gold_balance * $gold_price; // محاسبه ارزش به تومان

            $formatted_balance = number_format($balance_in_toman); // فرمت عددی با ,



            $message = "🏦 موجودی حساب شما:\n";

            $message .= "🔸 مقدار طلا: {$gold_balance} گرم\n";

            $message .= "💰 ارزش به تومان: {$formatted_balance} تومان";

        } else {

            $message = "⚠ هنوز قیمت طلا ثبت نشده است.";

        }

    } else {

        $message = "⚠ حساب شما یافت نشد.";

    }



    sendMessage($api_token, $chat_id, $message);

}

function sendUserIdForReceivingGold($api_token, $chat_id) {

    global $mysqli;



    // بررسی وجود کاربر در دیتابیس

    $stmt = $mysqli->prepare("SELECT user_id FROM users WHERE user_id = ?");

    $stmt->bind_param("i", $chat_id);

    $stmt->execute();

    $result = $stmt->get_result();



    if ($row = $result->fetch_assoc()) {

        $user_id = $row['user_id'];



        $message = "📥 دریافت طلا\n";

        $message .= "🔹 شناسه عددی شما: <code>{$user_id}</code>\n";

        $message .= "🔸 لطفاً این کد را به فردی که می‌خواهید از او طلا دریافت کنید بدهید.\n";

        $message .= "📌 آن شخص باید در بخش '🏦 انتقال طلا' این شناسه را وارد کند.";



        sendMessage($api_token, $chat_id, $message);

    } else {

        sendMessage($api_token, $chat_id, "⚠ حساب شما در سیستم ثبت نشده است.");

    }

}

function sendSupportInfo($api_token, $chat_id) {

    $message = "📞 **اطلاعات پشتیبانی:**\n\n";

    $message .= "📱 *شماره تماس:* `02191304230`\n";

    $message .= "💬 *آی‌دی تلگرام:* [@arianamini](https://t.me/arianamini)\n";

    $message .= "📲 *شماره واتساپ:* `09173508227`\n\n";

    $message .= "⏳ ساعات پاسخگویی: ۹ صبح تا ۹ شب";



    sendMessage($api_token, $chat_id, $message);

}



/* انتقال طلا */























function transferGold($api_token, $chat_id, $user_id, $mysqli) {

    // مرحله ۱: دریافت موجودی و قیمت طلا

    $stmt = $mysqli->prepare("SELECT balance FROM users WHERE user_id = ?");

    $stmt->bind_param("i", $chat_id);

    $stmt->execute();

    $result = $stmt->get_result();

    

    if (!$row = $result->fetch_assoc()) {

        sendMessage($api_token, $chat_id, "⛔ حساب کاربری شما یافت نشد!");

        return;

    }

    

    $gold_balance = $row['balance'];



    // دریافت آخرین قیمت طلا

    $stmt = $mysqli->prepare("SELECT price FROM gold_prices ORDER BY timestamp DESC LIMIT 1");

    $stmt->execute();

    $result = $stmt->get_result();

    $gold_price = ($row = $result->fetch_assoc()) ? $row['price'] : 0;



    if ($gold_price == 0) {

        sendMessage($api_token, $chat_id, "⛔ قیمت طلا در سیستم ثبت نشده است!");

        return;

    }



    // محاسبه معادل تومانی

    $rial_balance = number_format($gold_balance * $gold_price, 0, '.', ',');



    // پیام به کاربر

    $message = "💰 *موجودی شما:*  \n";

    $message .= "🔸 طلا: *$gold_balance گرم*\n";

    $message .= "🔹 معادل: *$rial_balance تومان*\n\n";

    $message .= "📥 لطفاً مقدار طلایی که می‌خواهید انتقال دهید را ارسال کنید:";



    // ذخیره وضعیت انتقال

    setUserStatus($user_id, 'WAITING_FOR_GOLD_AMOUNT');



    sendMessage($api_token, $chat_id, $message);

}



// مرحله ۲: دریافت مقدار طلا و ذخیره آن

function processGoldAmount($api_token, $chat_id, $user_id, $amount, $mysqli) {

    if (!is_numeric($amount) || $amount <= 0) {

        sendMessage($api_token, $chat_id, "⛔ لطفاً مقدار معتبر وارد کنید!");

        return;

    }



    // بررسی موجودی کافی

    $stmt = $mysqli->prepare("SELECT balance FROM users WHERE user_id = ?");

    $stmt->bind_param("i", $chat_id);

    $stmt->execute();

    $result = $stmt->get_result();

    $row = $result->fetch_assoc();

    $bbbaa=$row['balance'];

    if (!$row || $row['balance'] < $amount) {

        sendMessage($api_token, $chat_id, "$bbbaa");

        return;

    }



    // ذخیره مقدار انتقال در وضعیت کاربر

    setUserStatus($user_id, 'WAITING_FOR_RECEIVER_ID', ['gold_amount' => $amount]);



    sendMessage($api_token, $chat_id, "📤 لطفاً آیدی عددی کاربری که می‌خواهید طلا به او منتقل کنید را ارسال نمایید:");

}



// مرحله ۳: دریافت آیدی گیرنده و تایید انتقال

function processReceiverId($api_token, $chat_id, $user_id, $receiver_id, $mysqli) {

    // بررسی وجود گیرنده

    $stmt = $mysqli->prepare("SELECT phone FROM users WHERE user_id = ?");

    $stmt->bind_param("i", $receiver_id);

    $stmt->execute();

    $result = $stmt->get_result();

    

    if (!$row = $result->fetch_assoc()) {

        sendMessage($api_token, $chat_id, "⛔ کاربری با این آیدی یافت نشد!");

        return;

    }



    $receiver_phone = $row['phone'];

    $gold_amount = getUserStatus($user_id)['gold_amount'];



    // دکمه‌های تایید انتقال

    $keyboard = [

        'inline_keyboard' => [

            [],

            []

        ]

    ];



    // پیام تایید

    $message = "🔸 *جزئیات انتقال طلا:*\n\n";

    $message .= "📤 *فرستنده:* شما\n";

    $message .= "📥 *گیرنده:* \n";

    $message .= "📱 *شماره تلفن:* `$receiver_phone`\n";

    $message .= "💰 *مقدار طلا:* `$gold_amount گرم`\n\n";



$keyboard = [
    'inline_keyboard' => [
        [
            ['text' => '✅ تایید', 'callback_data' => "confirm_transfer_{$receiver_id}_{$gold_amount}"],
            ['text' => '❌ رد', 'callback_data' => 'cancel_transfer']
        ]
    ]
];




sendMessage($chat_id, "🔸 *جزئیات انتقال طلا:*\n\n📤 *فرستنده:* شما\n📥 *گیرنده:* $receiver_name\n📱 *شماره تلفن:* `$receiver_phone`\n💰 *مقدار طلا:* `$amount` گرم\n\n⚡️ آیا تایید می‌کنید؟", json_encode(['reply_markup' => $keyboard]));





    sendMessage($api_token, $chat_id, $message, $keyboard);

}

// مرحله ۴: تایید و انجام انتقال

function confirmTransfer($user_id, $receiver_id, $gold_amount) {
    global $config, $pdo, $chat_id;
    $sender_id = $chat_id; // مقداردهی فرستنده
sendMessage($config['api_token'], $chat_id, "Detail : $gold_amount");
    // کم کردن از فرستنده
    $stmt = $pdo->prepare("UPDATE users SET balance = balance - ? WHERE user_id = ?");
    $stmt->execute([$gold_amount, $sender_id]);

    // اضافه کردن به گیرنده
    $stmt = $pdo->prepare("UPDATE users SET balance = balance + ? WHERE user_id = ?");
    $stmt->execute([$gold_amount, $receiver_id]);

    // ارسال پیام‌ها
    sendMessage($config['api_token'], $receiver_id, "💰 شما مقدار *$gold_amount گرم* طلا دریافت کردید!");
    sendMessage($config['api_token'], $chat_id, "✅ شما مقدار *$gold_amount گرم* طلا با موفقیت انتقال دادید!");

    // پاک کردن وضعیت کاربر
    setUserStatus($sender_id, null);
}


/*                            اتمام انتقال                                */





function logError($message) {

    file_put_contents("bot_error.log", date("[Y-m-d H:i:s] ") . $message . "\n", FILE_APPEND);

}



function setUserStatus($user_id, $status) {

    global $mysqli, $chat_id;



    if (empty($user_id)) {

        $user_id = $chat_id; // مقدار پیش‌فرض

    }



    if (!$mysqli) {

        logError("Database connection error");

        return false;

    }



    $stmt = $mysqli->prepare("UPDATE users SET status = ? WHERE user_id = ?");

    if (!$stmt) {

        logError("Prepare failed: " . $mysqli->error);

        return false;

    }



    $stmt->bind_param("si", $status, $user_id);

    if (!$stmt->execute()) {

        logError("Execute failed: " . $stmt->error);

        return false;

    }



    if ($stmt->affected_rows === 0) {

        logError("No rows updated. User ID: $user_id, Status: $status");

    }



    $stmt->close();

    return true;

}











?>