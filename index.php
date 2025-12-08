<?php
session_start();
require_once 'config.php'; 

// التحقق مما إذا كان المستخدم مسجل الدخول بالفعل
// يجب التأكد من أن التوجيه يذهب إلى admin.php (كما هو في كود admin.php)
if (isset($_SESSION['user_id'])) {
    header('Location: admin.php');
    exit();
}

$error_message = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error_message = 'الرجاء إدخال اسم المستخدم وكلمة المرور.';
    } else {
        try {
            // ************ التعديل 1: الاستعلام من جدول admins ************
            // لا نحتاج حقل expiry_date هنا لأنه غير موجود في جدول admins
            $stmt = $db->prepare("SELECT id, username, password FROM admins WHERE username = :username");
            $stmt->bindParam(':username', $username);
            $stmt->execute();
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user) {
                // ************ التعديل 2: استخدام password_verify للتحقق من كلمة المرور المهشّشة ************
                if (password_verify($password, $user['password'])) {

                    // إذا كان هناك حقل expiry_date في جدول آخر (للمشتركين)، يجب التحقق منه هناك.
                    // في حالة الأدمن، نفترض أنه لا ينتهي صلاحيته.

                    // نجاح تسجيل الدخول
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];

                    // توجيه لصفحة لوحة التحكم
                    header("Location: admin.php"); // تم تعديل التوجيه ليتوافق مع الحماية في admin.php
                    exit();

                } else {
                    $error_message = 'اسم المستخدم أو كلمة المرور غير صحيحة.';
                }
            } else {
                $error_message = 'اسم المستخدم أو كلمة المرور غير صحيحة.'; // إخفاء ما إذا كان اسم المستخدم موجودًا لأسباب أمنية
            }

        } catch (PDOException $e) {
            // يمكن إضافة $e->getMessage() للتصحيح، لكن يفضل عدم إظهارها للمستخدم
            $error_message = 'حدث خطأ في قاعدة البيانات. يرجى مراجعة المسؤول.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تسجيل دخول الأدمن</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.3/css/bootstrap.rtl.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        body {
            background-color: #f8f9fa;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }
        .login-container {
            width: 100%;
            max-width: 400px;
            padding: 15px;
        }
        .card {
            border-radius: 15px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }
    </style>
</head>
<body>

<div class="login-container">
    <div class="card">
        <div class="card-body p-4">
            <h3 class="card-title text-center mb-4"><i class="fas fa-user-lock me-2"></i>تسجيل دخول الأدمن</h3>

            <?php if ($error_message): ?>
                <div class="alert alert-danger text-center" role="alert">
                    <?php echo htmlspecialchars($error_message); ?>
                </div>
            <?php endif; ?>

            <form action="index.php" method="POST">
                <div class="mb-3">
                    <label for="username" class="form-label">اسم المستخدم (يوزر)</label>
                    <input type="text" class="form-control" id="username" name="username" required>
                </div>
                <div class="mb-4">
                    <label for="password" class="form-label">كلمة المرور (باسورد)</label>
                    <input type="password" class="form-control" id="password" name="password" required>
                </div>
                <button type="submit" class="btn btn-primary w-100">دخول</button>
            </form>
            <p class="text-center mt-3 text-muted">
                البيانات الافتراضية: **admin** / **admin123**
            </p>
        </div>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.3/js/bootstrap.bundle.min.js"></script>
</body>
</html>
