<?php
session_start();
$conn = new mysqli("localhost", "root", "", "news_system");
if ($conn->connect_error) {
    die("فشل الاتصال بقاعدة البيانات: " . $conn->connect_error);
}

$page = isset($_GET['page']) ? $_GET['page'] : 'login';

// تسجيل مستخدم جديد
if ($page == "register_action" && $_SERVER['REQUEST_METHOD'] == "POST") {
    $name = $_POST['name'];
    $email = $_POST['email'];
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $conn->query("INSERT INTO users (name, email, password) VALUES ('$name','$email','$password')");
    echo "تم التسجيل بنجاح! <a href='?page=login'>تسجيل الدخول</a>";
    exit;
}

// تسجيل الدخول
if ($page == "login_action" && $_SERVER['REQUEST_METHOD'] == "POST") {
    $email = $_POST['email'];
    $password = $_POST['password'];
    $res = $conn->query("SELECT * FROM users WHERE email='$email'");
    if ($res->num_rows > 0) {
        $user = $res->fetch_assoc();
        if (password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['name'];
            header("Location: ?page=dashboard");
            exit;
        }
    }
    echo "بيانات الدخول غير صحيحة! <a href='?page=login'>رجوع</a>";
    exit;
}

// تسجيل خروج
if ($page == "logout") {
    session_destroy();
    header("Location: ?page=login");
    exit;
}

// حذف خبر
if ($page == "delete_news" && isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $conn->query("UPDATE news SET deleted=1 WHERE id=$id");
    header("Location: ?page=view_news");
    exit;
}

// تحديث خبر
if ($page == "update_news_action" && $_SERVER['REQUEST_METHOD'] == "POST") {
    $id = $_POST['id'];
    $title = $_POST['title'];
    $category_id = $_POST['category_id'];
    $details = $_POST['details'];
    $conn->query("UPDATE news SET title='$title', category_id='$category_id', details='$details' WHERE id=$id");
    header("Location: ?page=view_news");
    exit;
}

// إضافة فئة
if ($page == "add_category_action" && $_SERVER['REQUEST_METHOD'] == "POST") {
    $name = $_POST['name'];
    $conn->query("INSERT INTO categories (name) VALUES ('$name')");
    header("Location: ?page=view_categories");
    exit;
}

// إضافة خبر
if ($page == "add_news_action" && $_SERVER['REQUEST_METHOD'] == "POST") {
    $title = $_POST['title'];
    $category_id = $_POST['category_id'];
    $details = $_POST['details'];
    $user_id = $_SESSION['user_id'];
    $image = "";
    if (!empty($_FILES['image']['name'])) {
        $image = "uploads/" . time() . "_" . $_FILES['image']['name'];
        move_uploaded_file($_FILES['image']['tmp_name'], $image);
    }
    $conn->query("INSERT INTO news (title, category_id, details, image, user_id) VALUES ('$title','$category_id','$details','$image','$user_id')");
    header("Location: ?page=view_news");
    exit;
}

// التأكد من تسجيل الدخول للداشبورد
if (!isset($_SESSION['user_id']) && !in_array($page, ['login','register','login_action','register_action'])) {
    header("Location: ?page=login");
    exit;
}
?>
<!DOCTYPE html>
<html lang="ar">
<head>
    <meta charset="UTF-8">
    <title>نظام إدارة الأخبار</title>
    <style>
        body { font-family: Tahoma; margin:20px; }
        nav a { margin: 10px; text-decoration: none; }
        table, td, th { border:1px solid #333; border-collapse: collapse; padding:5px; }
    </style>
</head>
<body>

<?php if ($page == "login"): ?>
    <h2>تسجيل الدخول</h2>
    <form method="post" action="?page=login_action">
        الإيميل: <input type="email" name="email"><br>
        كلمة المرور: <input type="password" name="password"><br>
        <button type="submit">دخول</button>
    </form>
    <a href="?page=register">إنشاء حساب جديد</a>

<?php elseif ($page == "register"): ?>
    <h2>إنشاء حساب جديد</h2>
    <form method="post" action="?page=register_action">
        الاسم: <input type="text" name="name"><br>
        الإيميل: <input type="email" name="email"><br>
        كلمة المرور: <input type="password" name="password"><br>
        <button type="submit">تسجيل</button>
    </form>
    <a href="?page=login">رجوع لتسجيل الدخول</a>

<?php elseif ($page == "dashboard"): ?>
    <h2>مرحباً <?= $_SESSION['user_name'] ?></h2>
    <nav>
        <a href="?page=add_category">➕ إضافة فئة</a>
        <a href="?page=view_categories">📂 عرض الفئات</a>
        <a href="?page=add_news">➕ إضافة خبر</a>
        <a href="?page=view_news">📰 عرض الأخبار</a>
        <a href="?page=deleted_news">🗑 عرض الأخبار المحذوفة</a>
        <a href="?page=logout">🚪 تسجيل الخروج</a>
    </nav>

<?php elseif ($page == "add_category"): ?>
    <h2>إضافة فئة</h2>
    <form method="post" action="?page=add_category_action">
        اسم الفئة: <input type="text" name="name"><br>
        <button type="submit">حفظ</button>
    </form>

<?php elseif ($page == "view_categories"): ?>
    <h2>الفئات</h2>
    <table>
        <tr><th>ID</th><th>الاسم</th></tr>
        <?php
        $res = $conn->query("SELECT * FROM categories");
        while ($row = $res->fetch_assoc()) {
            echo "<tr><td>{$row['id']}</td><td>{$row['name']}</td></tr>";
        }
        ?>
    </table>

<?php elseif ($page == "add_news"): ?>
    <h2>إضافة خبر</h2>
    <form method="post" enctype="multipart/form-data" action="?page=add_news_action">
        العنوان: <input type="text" name="title"><br>
        الفئة:
        <select name="category_id">
            <?php
            $res = $conn->query("SELECT * FROM categories");
            while ($row = $res->fetch_assoc()) {
                echo "<option value='{$row['id']}'>{$row['name']}</option>";
            }
            ?>
        </select><br>
        التفاصيل: <textarea name="details"></textarea><br>
        صورة: <input type="file" name="image"><br>
        <button type="submit">حفظ</button>
    </form>

<?php elseif ($page == "view_news"): ?>
    <h2>جميع الأخبار</h2>
    <table>
        <tr><th>ID</th><th>العنوان</th><th>الفئة</th><th>المستخدم</th><th>صورة</th><th>إجراءات</th></tr>
        <?php
        $res = $conn->query("SELECT n.*, c.name as cname, u.name as uname FROM news n 
            JOIN categories c ON n.category_id=c.id 
            JOIN users u ON n.user_id=u.id 
            WHERE n.deleted=0");
        while ($row = $res->fetch_assoc()) {
            echo "<tr>
                <td>{$row['id']}</td>
                <td>{$row['title']}</td>
                <td>{$row['cname']}</td>
                <td>{$row['uname']}</td>
                <td>".($row['image']?"<img src='{$row['image']}' width='100'>":"")."</td>
                <td>
                    <a href='?page=edit_news&id={$row['id']}'>✏ تعديل</a> | 
                    <a href='?page=delete_news&id={$row['id']}'>🗑 حذف</a>
                </td>
            </tr>";
        }
        ?>
    </table>

<?php elseif ($page == "edit_news" && isset($_GET['id'])): 
    $id = intval($_GET['id']);
    $res = $conn->query("SELECT * FROM news WHERE id=$id");
    $news = $res->fetch_assoc();
    ?>
    <h2>تعديل خبر</h2>
    <form method="post" action="?page=update_news_action">
        <input type="hidden" name="id" value="<?= $news['id'] ?>">
        العنوان: <input type="text" name="title" value="<?= $news['title'] ?>"><br>
        الفئة:
        <select name="category_id">
            <?php
            $res = $conn->query("SELECT * FROM categories");
            while ($row = $res->fetch_assoc()) {
                $sel = $row['id']==$news['category_id']?"selected":"";
                echo "<option value='{$row['id']}' $sel>{$row['name']}</option>";
            }
            ?>
        </select><br>
        التفاصيل: <textarea name="details"><?= $news['details'] ?></textarea><br>
        <button type="submit">تحديث</button>
    </form>

<?php elseif ($page == "deleted_news"): ?>
    <h2>الأخبار المحذوفة</h2>
    <table>
        <tr><th>ID</th><th>العنوان</th><th>الفئة</th><th>المستخدم</th></tr>
        <?php
        $res = $conn->query("SELECT n.*, c.name as cname, u.name as uname FROM news n 
            JOIN categories c ON n.category_id=c.id 
            JOIN users u ON n.user_id=u.id 
            WHERE n.deleted=1");
        while ($row = $res->fetch_assoc()) {
            echo "<tr>
                <td>{$row['id']}</td>
                <td>{$row['title']}</td>
                <td>{$row['cname']}</td>
                <td>{$row['uname']}</td>
            </tr>";
        }
        ?>
    </table>
<?php endif; ?>

</body>
</html>
