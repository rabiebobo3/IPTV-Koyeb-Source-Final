
<?php

// ===========================================
// === 1. اعدادات اتصال قاعدة البيانات PostgreSQL (تم التعبئة بالكامل) ===
// ===========================================
$db_host = "ep-wild-grass-a2sf1rvf.eu-central-1.pg.koyeb.app"; // تم التحديث بالرابط الجديد
$db_port = "5432"; 
$db_name = "koyebdb"; // تم التحديث بالاسم الصحيح
$db_user = "koyeb-adm"; // تم التحديث بالاسم الصحيح
$db_pass = "npg_Q8tYigBLR1CW"; // *** تم التعبئة بكلمة المرور النهائية ***

// ===========================================
// === 2. اعدادات الرابط العام للتطبيق (Public URL) ===
// ===========================================
$base_url = "https://excited-gillie-rabiebobo3-11d98fb2.koyeb.app"; 

// ===========================================
// === 3. اعدادات اسم التطبيق والتشفير ===
// ===========================================
$app_name = "لوحة IPTV الخاصة بك"; // يمكنك تغيير هذا الاسم
$cipher_method = "AES-256-CBC"; 

// ===========================================
// === اتصال قاعدة البيانات (لا تعدل) ===
// ===========================================
try {
    $pdo = new PDO("pgsql:host=$db_host;port=$db_port;dbname=$db_name", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    http_response_code(500);
    die("Database connection error: " . $e->getMessage());
}

// ===========================================
// === (بقية الكود الموحد كما هو، لا يحتاج تعديل) ===
// ===========================================

function encrypt($data, $key, $method) {
    $ivlen = openssl_cipher_iv_length($method);
    $iv = openssl_random_pseudo_bytes($ivlen);
    $ciphertext = openssl_encrypt($data, $method, $key, 0, $iv);
    return base64_encode($iv . $ciphertext);
}

function decrypt($data, $key, $method) {
    $data = base64_decode($data);
    $ivlen = openssl_cipher_iv_length($method);
    $iv = substr($data, 0, $ivlen);
    $ciphertext = substr($data, $ivlen);
    return openssl_decrypt($ciphertext, $method, $key, 0, $iv);
}

if (isset($_GET['action'])) {
    $action = $_GET['action'];
    $key = hash('sha256', $db_pass); 

    if ($action === 'generate' && isset($_POST['user']) && isset($_POST['pass'])) {
        $data = json_encode(['user' => $_POST['user'], 'pass' => $_POST['pass']]);
        $encrypted_data = encrypt($data, $key, $cipher_method);
        $m3u_url = $base_url . "/?action=get&data=" . urlencode($encrypted_data);

        header('Content-Type: application/json');
        echo json_encode(['m3u_url' => $m3u_url]);

    } elseif ($action === 'get' && isset($_GET['data'])) {
        $decrypted_data = decrypt($_GET['data'], $key, $cipher_method);
        $credentials = json_decode($decrypted_data, true);

        if ($credentials && isset($credentials['user']) && isset($credentials['pass'])) {
            $user = $credentials['user'];
            $pass = $credentials['pass'];

            $stmt = $pdo->prepare("SELECT stream_url FROM users WHERE username = ? AND password = ?");
            $stmt->execute([$user, $pass]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($result) {
                header("Location: " . $result['stream_url']);
                exit();
            } else {
                http_response_code(401);
                die("Invalid credentials or no stream assigned.");
            }
        } else {
            http_response_code(400);
            die("Invalid data format or decryption failed.");
        }
    } else {
        http_response_code(400);
        die("Invalid action.");
    }
} else {
    // عرض واجهة الإدخال الأساسية
?>
<!DOCTYPE html>
<html lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($app_name) ?> - مولد M3U</title>
    <style>
        body { font-family: Arial, sans-serif; background-color: #f4f4f4; color: #333; display: flex; justify-content: center; align-items: center; min-height: 100vh; margin: 0; }
        .container { background-color: #fff; padding: 30px; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); width: 350px; max-width: 90%; text-align: center; }
        h2 { color: #007bff; margin-bottom: 20px; }
        input[type="text"], input[type="password"] { width: 90%; padding: 10px; margin-bottom: 15px; border: 1px solid #ccc; border-radius: 4px; }
        button { background-color: #28a745; color: white; padding: 10px 15px; border: none; border-radius: 4px; cursor: pointer; width: 100%; font-size: 16px; transition: background-color 0.3s; }
        button:hover { background-color: #218838; }
        #result-box { margin-top: 20px; padding: 10px; border: 1px solid #007bff; background-color: #e9f7ff; word-wrap: break-word; text-align: left; font-size: 14px; direction: ltr; }
    </style>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>
<body>
    <div class="container">
        <h2><?= htmlspecialchars($app_name) ?></h2>
        <form id="generatorForm">
            <input type="text" id="username" placeholder="اسم المستخدم" required>
            <input type="password" id="password" placeholder="كلمة المرور" required>
            <button type="submit">توليد رابط M3U</button>
        </form>
        <div id="result-box" style="display:none;"></div>
    </div>

    <script>
        $(document).ready(function() {
            $('#generatorForm').on('submit', function(e) {
                e.preventDefault();
                var user = $('#username').val();
                var pass = $('#password').val();

                $.ajax({
                    url: '<?= htmlspecialchars($base_url) ?>/?action=generate',
                    method: 'POST',
                    data: { user: user, pass: pass },
                    dataType: 'json',
                    success: function(response) {
                        $('#result-box').html('<strong>تم التوليد بنجاح:</strong><br>' + response.m3u_url).show();
                    },
                    error: function(xhr) {
                        var errorMessage = xhr.responseJSON ? xhr.responseJSON.error : 'فشل الاتصال أو خطأ في الخادم.';
                        $('#result-box').html('<strong>خطأ:</strong> ' + errorMessage).show();
                    }
                });
            });
        });
    </script>
</body>
</html>
<?php
}
?>
