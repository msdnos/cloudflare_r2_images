<?php
require 'config.php';

// 调试信息
error_reporting(E_ALL);
ini_set('display_errors', 1);

// 如果用户已经登录，重定向到管理页面
if (isLoggedIn()) {
    header("Location: admin.php");
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    try {
        $conn = getDBConnection();
        $stmt = $conn->prepare("SELECT id, username, password_hash FROM users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            if (password_verify($password, $user['password_hash'])) {
                $_SESSION['loggedin'] = true;
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                header("Location: admin.php");
                exit;
            }
        }
        
        $error = '用户名或密码错误';
        $stmt->close();
        $conn->close();
    } catch (Exception $e) {
        $error = '系统错误: ' . $e->getMessage();
    }
}

// 调试输出
echo "<!-- 当前会话: " . print_r($_SESSION, true) . " -->";
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>登录 - 图片管理</title>
    <link rel="icon" href="logo.svg" type="image/svg+xml">
    <link href="https://cdn.bootcdn.net/ajax/libs/twitter-bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.bootcdn.net/ajax/libs/bootstrap-icons/1.10.0/font/bootstrap-icons.css"  rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-6 col-lg-4">
                <div class="card shadow">
                    <div class="card-body p-4">
                        <h2 class="text-center mb-4"><i class="bi bi-images"></i> CloudFlare R2<br>对象存储图片管理系统</h2>
                        <?php if ($error): ?>
                            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                        <?php endif; ?>
                        <form method="POST">
                            <div class="mb-3">
                                <label for="username" class="form-label"><i class="bi bi-person-circle"></i> 登录名称</label>
                                <input type="text" class="form-control" id="username" name="username" required>
                            </div>
                            <div class="mb-3">
                                <label for="password" class="form-label"><i class="bi bi-key-fill"></i> 登录密码</label>
                                <input type="password" class="form-control" id="password" name="password" required>
                            </div>
                            <button type="submit" class="btn btn-primary w-100">登录</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.9930.top:9930/twitter-bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
</body>
</html>
