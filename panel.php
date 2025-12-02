<?php
// ملف: panel.php (اللوحة الرئيسية - مُحسن لـ PostgreSQL و Koyeb)

// 1. إعدادات التشخيص
ini_set('display_errors', 1);
error_reporting(E_ALL);

$pdo = null; 
$message = '';
$playlist_url = '';
$users = [];
$channels = []; 

// 2. بيانات الاتصال بقاعدة البيانات (Koyeb PostgreSQL)
$host = 'ep-mute-cell-a2fdpk4m.eu-central-1.pg.koyeb.app'; 
$db   = 'koyebdb'; 
$user = 'koyeb-adm';
$pass = 'npg_M8irRz9HKOxt'; // يجب التأكد من صحة كلمة المرور
$port = '5432';
$charset = 'utf8';

// PDO DSN لـ PostgreSQL
$dsn = "pgsql:host=$host;port=$port;dbname=$db;user=$user;password=$pass";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
];

// 3. محاولة الاتصال والتهيئة (إنشاء الجداول)
try {
     $pdo = new PDO($dsn, null, null, $options);
     
     if ($pdo) {
         // إنشاء جدول المستخدمين (users) إن لم يكن موجوداً
         $pdo->exec("CREATE TABLE IF NOT EXISTS users (
             username VARCHAR(100) NOT NULL PRIMARY KEY,
             password VARCHAR(255) NOT NULL,
             expiry_date DATE NOT NULL,
             max_connections INTEGER DEFAULT 1,
             active_connections INTEGER DEFAULT 0,
             last_connection TIMESTAMP NULL
         )");

         // إنشاء جدول القنوات (channels) إن لم يكن موجوداً
         $pdo->exec("CREATE TABLE IF NOT EXISTS channels (
             id SERIAL PRIMARY KEY,
             name VARCHAR(255) NOT NULL,
             url VARCHAR(512) NOT NULL,
             tvg_id VARCHAR(100),
             category VARCHAR(100)
         )");
         
         // جلب البيانات بعد إنشاء الجداول
         $users = $pdo->query("SELECT username, expiry_date, max_connections, active_connections, last_connection FROM users")->fetchAll();
         $channels = $pdo->query("SELECT id, name, url, tvg_id, category FROM channels")->fetchAll();
     }
     
} catch (\PDOException $e) {
     $message = "❌ فشل الاتصال بقاعدة البيانات (Koyeb): " . $e->getMessage();
}

// 4. معالجة عمليات الحذف
if ($pdo && $_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'delete') {
    if (isset($_POST['delete_user'])) {
        $username = trim($_POST['delete_user']);
        $stmt = $pdo->prepare("DELETE FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $message = "✅ تم حذف المشترك **" . htmlspecialchars($username) . "** بنجاح.";
    } elseif (isset($_POST['delete_channel'])) {
        $channel_id = $_POST['delete_channel'];
        $stmt = $pdo->prepare("DELETE FROM channels WHERE id = ?");
        $stmt->execute([$channel_id]);
        $message = "✅ تم حذف القناة بنجاح.";
    }
}

// 5. معالجة عمليات التعديل 
if ($pdo && $_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'update') {
    if (isset($_POST['update_user'])) {
        $username = trim($_POST['update_user']);
        $expiry_date = $_POST['expiry_date'];
        $max_connections = $_POST['max_connections'];
        
        $sql = "UPDATE users SET expiry_date = ?, max_connections = ? WHERE username = ?";
        
        if (!empty($_POST['password'])) {
            $new_api_key = bin2hex(random_bytes(20)); 
            $sql = "UPDATE users SET password = ?, expiry_date = ?, max_connections = ? WHERE username = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$new_api_key, $expiry_date, $max_connections, $username]);
        } else {
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$expiry_date, $max_connections, $username]);
        }
        $message = "✅ تم تعديل بيانات المشترك **" . htmlspecialchars($username) . "** بنجاح.";
        
    } elseif (isset($_POST['update_channel'])) {
        $id = $_POST['update_channel'];
        $name = trim($_POST['channel_name']);
        $url = trim($_POST['channel_url']);
        $tvg_id = trim($_POST['tvg_id']);
        $category = trim($_POST['category']);

        $sql = "UPDATE channels SET name = ?, url = ?, tvg_id = ?, category = ? WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$name, $url, $tvg_id, $category, $id]);
        $message = "✅ تم تعديل بيانات القناة **" . htmlspecialchars($name) . "** بنجاح.";
    }
}

// 6. معالجة نموذج إضافة المستخدم (يولد المفتاح السري)
if ($pdo && $_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_user'])) {
    $username = trim($_POST['username']);
    $api_key = bin2hex(random_bytes(20)); // توليد رمز API سري وطويل
    $expiry_date = $_POST['expiry_date'];
    $max_connections = $_POST['max_connections'];

    $sql = "INSERT INTO users (username, password, expiry_date, max_connections) VALUES (?, ?, ?, ?)"; 
    
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$username, $api_key, $expiry_date, $max_connections]);
        
        $message = "✅ تم إضافة المشترك بنجاح! (تم توليد API Key)";
        
        // توليد رابط API (يجب تعديل [اسم تطبيقك] بعد النشر على Koyeb)
        $generated_username = urlencode($username);
        $generated_api_key = urlencode($api_key);
        $koyeb_host = 'https://[اسم تطبيقك].koyeb.app'; 
        
        $playlist_url = "$koyeb_host/get.php?username=$generated_username&password=$generated_api_key&type=m3u_plus&output=m3u8"; 
        
    } catch (PDOException $e) {
        if ($e->getCode() == '23505') { // كود PostgreSQL لتكرار المفتاح الأساسي
            $message = "❌ خطأ: اسم المستخدم ($username) موجود بالفعل.";
        } else {
            $message = "❌ خطأ غير متوقع في الإضافة: " . $e->getMessage();
        }
    }
}

// 7. معالجة نموذج إضافة القناة 
if ($pdo && $_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_channel'])) {
    $channel_name = trim($_POST['channel_name']);
    $channel_url = trim($_POST['channel_url']);
    $tvg_id = trim($_POST['tvg_id']);
    $category = trim($_POST['category']);

    $sql = "INSERT INTO channels (name, url, tvg_id, category) VALUES (?, ?, ?, ?)";
    
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$channel_name, $channel_url, $tvg_id, $category]);
        $message = "✅ تم إضافة القناة: " . htmlspecialchars($channel_name) . " بنجاح!";
        
    } catch (PDOException $e) {
        $message = "❌ خطأ في إضافة القناة: " . $e->getMessage();
    }
}

// إعادة جلب البيانات بعد كل عملية
if ($pdo) {
    $users = $pdo->query("SELECT username, expiry_date, max_connections, active_connections, last_connection FROM users")->fetchAll();
    $channels = $pdo->query("SELECT id, name, url, tvg_id, category FROM channels")->fetchAll();
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>لوحة التحكم M3U المتقدمة</title>
    <style>
        body { font-family: Tahoma, sans-serif; margin: 20px; }
        .success { color: green; background-color: #e6ffe6; border: 1px solid green; padding: 10px; margin-bottom: 20px; }
        .error { color: red; background-color: #ffe6e6; border: 1px solid red; padding: 10px; margin-bottom: 20px; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; font-size: 14px; }
        th, td { border: 1px solid #ccc; padding: 8px; text-align: right; }
        th { background-color: #f2f2f2; }
        input[type="text"], input[type="password"], input[type="date"], select { padding: 8px; margin-top: 5px; margin-bottom: 15px; box-sizing: border-box; width: 100%; }
        button { background-color: #4CAF50; color: white; padding: 8px 12px; border: none; cursor: pointer; margin-left: 5px; }
        .delete-btn { background-color: #f44336; }
        .edit-btn { background-color: #007bff; }
        .action-btns button { width: auto; }
        .form-group { margin-bottom: 15px; }
    </style>
    <script>
        function openEditUser(username, expiry, max_conn) {
            document.getElementById('edit_user_username').value = username;
            document.getElementById('edit_user_expiry').value = expiry;
            document.getElementById('edit_user_max_conn').value = max_conn;
            document.getElementById('user_modal').style.display = 'block';
        }
        function closeUserModal() {
            document.getElementById('user_modal').style.display = 'none';
        }

        function openEditChannel(id, name, url, tvg_id, category) {
            document.getElementById('edit_channel_id').value = id;
            document.getElementById('edit_channel_name').value = name;
            document.getElementById('edit_channel_url').value = url;
            document.getElementById('edit_channel_tvg_id').value = tvg_id;
            document.getElementById('edit_channel_category').value = category;
            document.getElementById('channel_modal').style.display = 'block';
        }
        function closeChannelModal() {
            document.getElementById('channel_modal').style.display = 'none';
        }
    </script>

</head>
<body>
    <h1>لوحة التحكم M3U المتقدمة</h1>

    <?php if (!empty($message)): ?>
        <p class="<?php echo (strpos($message, '✅') !== false) ? 'success' : 'error'; ?>">
            <?php echo $message; ?>
        </p>
    <?php endif; ?>

    <?php if (!empty($playlist_url)): ?>
        <div class="success">
            **السيرفر الخاص بك (إسم المستخدم):** <strong><?php echo htmlspecialchars($username); ?></strong>
            <br>
            **رابط السيرفر/قائمة التشغيل (Playlist URL):**<br>
            <a href="<?php echo htmlspecialchars($playlist_url); ?>"><?php echo htmlspecialchars($playlist_url); ?></a>
            <p style="font-size:12px; color:#555;">*ملاحظة: **يجب تغيير `[اسم تطبيقك].koyeb.app` في الرابط بعد النشر**، الرابط أعلاه يستخدم المفتاح السري (API Key) الذي تم توليده تلقائياً.*</p>
        </div>
    <?php endif; ?>

    <hr>

    <h2>✅ 1. التحكم في المشتركين (إضافة/تعديل/حذف)</h2>
    <h3>إضافة مشترك جديد</h3>
    <section>
        <form method="POST">
            <div class="form-group"><label for="username">إسم المستخدم (إسم السيرفر):</label><input type="text" name="username" required></div>
            <div class="form-group"><label for="password">كلمة المرور (لتجاوز المشاكل، لن تُستخدم):</label><input type="password" name="password" value="12345" disabled></div>
            <div class="form-group"><label for="expiry_date">تاريخ إنتهاء الإشتراك:</label><input type="date" name="expiry_date" required></div>
            <div class="form-group"><label for="max_connections">الحد الأقصى من المتصلين:</label>
                <select name="max_connections"><option value="1">1</option><option value="2">2</option><option value="3">3</option></select>
            </div>
            <button type="submit" name="add_user">إضافة المشترك والسيرفر</button>
        </form>
    </section>
    
    <hr>

    <section>
        <h2>القنوات المتوفرة في السيرفر (<?php echo count($channels); ?> قناة)</h2>
        <?php if ($pdo && !empty($channels)): ?>
            <table>
                <thead>
                    <tr><th>إسم القناة</th><th>الفئة/المجموعة</th><th>رابط البث</th><th>التحكم</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($channels as $channel): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($channel['name']); ?></td>
                        <td><?php echo htmlspecialchars($channel['category']); ?></td>
                        <td><?php echo htmlspecialchars($channel['url']); ?></td>
                        <td class="action-btns">
                            <button class="edit-btn" onclick="openEditChannel('<?php echo $channel['id']; ?>', '<?php echo htmlspecialchars($channel['name'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($channel['url'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($channel['tvg_id'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($channel['category'], ENT_QUOTES); ?>')">تعديل</button>
                            <form method="POST" style="display:inline;" onsubmit="return confirm('هل أنت متأكد من حذف القناة؟');">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="delete_channel" value="<?php echo $channel['id']; ?>">
                                <button type="submit" class="delete-btn">حذف</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php elseif ($pdo): ?>
            <p>لا توجد قنوات مضافة إلى السيرفر حتى الآن.</p>
        <?php endif; ?>
    </section>
    
    <hr>
    
    <section>
        <h2>قائمة المشتركين (للمراقبة)</h2>
        <?php if ($pdo && !empty($users)): ?>
            <table>
                <thead>
                    <tr>
                        <th>إسم المستخدم</th>
                        <th>تاريخ الإنتهاء</th>
                        <th>حد الاتصالات</th>
                        <th>متصل حالياً</th>
                        <th>آخر اتصال (مراقبة)</th>
                        <th>التحكم</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($user['username']); ?></td>
                        <td><?php echo htmlspecialchars($user['expiry_date']); ?></td>
                        <td><?php echo htmlspecialchars($user['max_connections']); ?></td>
                        <td><?php echo htmlspecialchars($user['active_connections']); ?></td>
                        <td><?php echo $user['last_connection'] ?? 'لم يتصل بعد'; ?></td>
                        <td class="action-btns">
                            <button class="edit-btn" onclick="openEditUser('<?php echo $user['username']; ?>', '<?php echo $user['expiry_date']; ?>', '<?php echo $user['max_connections']; ?>')">تعديل</button>
                            <form method="POST" style="display:inline;" onsubmit="return confirm('هل أنت متأكد من حذف المشترك؟');">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="delete_user" value="<?php echo $user['username']; ?>">
                                <button type="submit" class="delete-btn">حذف</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php elseif ($pdo): ?>
            <p>لا توجد مشتركين مضافة حتى الآن.</p>
        <?php endif; ?>
    </section>

    <div id="user_modal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:100;">
        <div style="background:#fff; margin:10% auto; padding:20px; width:80%; max-width:400px; border-radius:5px;">
            <h3>تعديل بيانات المشترك</h3>
            <form method="POST">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="update_user" id="edit_user_username">
                
                <label for="edit_user_expiry">تاريخ إنتهاء الإشتراك:</label>
                <input type="date" name="expiry_date" id="edit_user_expiry" required>
                
                <label for="edit_user_max_conn">الحد الأقصى للاتصالات:</label>
                <select name="max_connections" id="edit_user_max_conn">
                    <option value="1">1</option><option value="2">2</option><option value="3">3</option>
                </select>
                
                <label for="edit_password">لتوليد مفتاح سري جديد (اكتب أي شيء):</label>
                <input type="password" name="password" id="edit_password">
                
                <button type="submit" class="edit-btn">حفظ التعديلات</button>
                <button type="button" class="delete-btn" onclick="closeUserModal()">إلغاء</button>
            </form>
        </div>
    </div>
    
    <div id="channel_modal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:100;">
        <div style="background:#fff; margin:10% auto; padding:20px; width:80%; max-width:400px; border-radius:5px;">
            <h3>تعديل بيانات القناة</h3>
            <form method="POST">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="update_channel" id="edit_channel_id">
                
                <label for="edit_channel_name">إسم القناة:</label>
                <input type="text" name="channel_name" id="edit_channel_name" required>
                
                <label for="edit_channel_url">رابط البث (URL):</label>
                <input type="text" name="channel_url" id="edit_channel_url" required>

                <label for="edit_channel_tvg_id">TVG ID (اختياري):</label>
                <input type="text" name="tvg_id" id="edit_channel_tvg_id">
                
                <label for="edit_channel_category">الفئة/المجموعة:</label>
                <input type="text" name="category" id="edit_channel_category" required>
                
                <button type="submit" class="edit-btn">حفظ التعديلات</button>
                <button type="button" class="delete-btn" onclick="closeChannelModal()">إلغاء</button>
            </form>
        </div>
    </div>
    </body>
</html>
