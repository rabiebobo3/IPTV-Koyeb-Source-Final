<?php
// ملف: get.php (API لتوليد قائمة التشغيل M3U)

// 1. إعدادات التشخيص
ini_set('display_errors', 1);
error_reporting(E_ALL);

// 2. بيانات الاتصال بقاعدة البيانات (Koyeb PostgreSQL)
$host = 'ep-mute-cell-a2fdpk4m.eu-central-1.pg.koyeb.app'; 
$db   = 'koyebdb'; 
$user = 'koyeb-adm';
$pass = 'npg_M8irRz9HKOxt'; 
$port = '5432';

// PDO DSN لـ PostgreSQL
$dsn = "pgsql:host=$host;port=$port;dbname=$db;user=$user;password=$pass";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
];

$pdo = null;
try {
     $pdo = new PDO($dsn, null, null, $options);
} catch (\PDOException $e) {
     http_response_code(500);
     die("Internal Server Error: Database Connection Failed.");
}

// 3. التحقق من الطلب
$username = $_GET['username'] ?? '';
$password = $_GET['password'] ?? ''; // المفتاح السري (API Key)
$type = $_GET['type'] ?? '';
$output = $_GET['output'] ?? '';

if (empty($username) || empty($password)) {
    http_response_code(401);
    die("#AUTH FAILED: Username or API Key missing.");
}

// 4. التحقق من المشترك وحالة الاتصال
try {
    // 4.1 جلب بيانات المستخدم
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? AND password = ?");
    $stmt->execute([$username, $password]);
    $user = $stmt->fetch();

    if (!$user) {
        http_response_code(401);
        die("#AUTH FAILED: Invalid credentials.");
    }
    
    // 4.2 التحقق من انتهاء الصلاحية
    $expiry_date = new DateTime($user['expiry_date']);
    $today = new DateTime();
    if ($today > $expiry_date) {
        http_response_code(403);
        die("#AUTH FAILED: Subscription expired on {$user['expiry_date']}.");
    }

    // 4.3 التحقق من حد الاتصالات
    $current_connections = (int)$user['active_connections'];
    $max_connections = (int)$user['max_connections'];
    
    // ملاحظة: التحقق الفعلي من الاتصالات المتزامنة يتطلب منطقاً أكثر تعقيداً (مثل جلسات أو سجلات مفصلة)، هنا نستخدم حقلاً بسيطاً.
    if ($current_connections >= $max_connections) {
        http_response_code(403);
        die("#AUTH FAILED: Connection limit reached ({$current_connections}/{$max_connections}).");
    }
    
    // 4.4 تحديث حالة الاتصال (زيادة الاتصال الحالي وتحديث آخر اتصال)
    $pdo->prepare("UPDATE users SET active_connections = active_connections + 1, last_connection = NOW() WHERE username = ?")
        ->execute([$username]);

    // 5. توليد قائمة التشغيل (M3U)
    
    // 5.1 جلب القنوات
    $channels = $pdo->query("SELECT * FROM channels")->fetchAll();

    // 5.2 إعداد الرأس
    header('Content-Type: application/vnd.apple.mpegurl');
    header('Content-Disposition: attachment; filename="playlist.m3u"');
    echo "#EXTM3U\r\n";
    
    // 5.3 تكرار القنوات
    foreach ($channels as $channel) {
        // تنسيق EXTM3U
        echo "#EXTINF:-1 tvg-id=\"{$channel['tvg_id']}\" group-title=\"{$channel['category']}\",{$channel['name']}\r\n";
        // رابط البث
        echo "{$channel['url']}\r\n";
    }

    // 6. إنهاء الاتصال وتقليل عدد المتصلين (تنفيذ غير مباشر)
    // ملاحظة: يجب أن تتم عملية تقليل عدد المتصلين عبر نظام مراقبة خارجي أو عند انتهاء الجلسة، 
    // لكن للمحاكاة والتجربة، سنقوم بتخفيضه بعد 5 ثوانٍ (هذا ليس حلاً حقيقياً)
    register_shutdown_function(function() use ($pdo, $username) {
        sleep(5); // انتظار 5 ثوانٍ قبل التخفيض (للتجربة فقط)
        $pdo->prepare("UPDATE users SET active_connections = active_connections - 1 WHERE username = ? AND active_connections > 0")
            ->execute([$username]);
    });

} catch (PDOException $e) {
    http_response_code(500);
    die("Internal Server Error: " . $e->getMessage());
}
?>
