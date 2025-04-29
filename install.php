<?php
require 'config.php';

// 如果已经安装过，重定向到登录页
if (file_exists('installed.lock')) {
    header("Location: login.php");
    exit;
}

$error = '';
$success = '';

// 处理安装表单提交
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $dbHost = $_POST['db_host'] ?? 'localhost';
    $dbName = $_POST['db_name'] ?? 'r2_image_manager';
    $dbUser = $_POST['db_user'] ?? '';
    $dbPass = $_POST['db_pass'] ?? '';
    $adminUser = $_POST['admin_user'] ?? 'admin';
    $adminPass = $_POST['admin_pass'] ?? '';
    
    try {
        // 测试数据库连接
        $conn = new mysqli($dbHost, $dbUser, $dbPass);
        if ($conn->connect_error) {
            throw new Exception("数据库连接失败: " . $conn->connect_error);
        }
        
        // 创建数据库(如果不存在)
        $conn->query("CREATE DATABASE IF NOT EXISTS `$dbName` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $conn->select_db($dbName);
        
        // 创建用户表
        $conn->query("
            CREATE TABLE IF NOT EXISTS `users` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `username` VARCHAR(50) NOT NULL UNIQUE,
                `password_hash` VARCHAR(255) NOT NULL,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        
        // 创建图片记录表(可选)
        $conn->query("
            CREATE TABLE IF NOT EXISTS `images` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `object_key` VARCHAR(255) NOT NULL UNIQUE,
                `original_name` VARCHAR(255) NOT NULL,
                `size` INT NOT NULL,
                `uploaded_by` INT,
                `uploaded_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (`uploaded_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        
        // 添加管理员用户
        $passwordHash = password_hash($adminPass, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("INSERT INTO `users` (`username`, `password_hash`) VALUES (?, ?)");
        $stmt->bind_param("ss", $adminUser, $passwordHash);
        $stmt->execute();
        
        // 更新配置文件
        $configContent = file_get_contents('config.php');
        $configContent = preg_replace(
            "/define\('DB_HOST', '.*?'\);/",
            "define('DB_HOST', '".addslashes($dbHost)."');",
            $configContent
        );
        $configContent = preg_replace(
            "/define\('DB_USER', '.*?'\);/",
            "define('DB_USER', '".addslashes($dbUser)."');",
            $configContent
        );
        $configContent = preg_replace(
            "/define\('DB_PASS', '.*?'\);/",
            "define('DB_PASS', '".addslashes($dbPass)."');",
            $configContent
        );
        $configContent = preg_replace(
            "/define\('DB_NAME', '.*?'\);/",
            "define('DB_NAME', '".addslashes($dbName)."');",
            $configContent
        );
        $configContent = preg_replace(
            "/define\('ADMIN_USERNAME', '.*?'\);/",
            "define('ADMIN_USERNAME', '".addslashes($adminUser)."');",
            $configContent
        );
        $configContent = preg_replace(
            "/define\('ADMIN_PASSWORD_HASH', '.*?'\);/",
            "define('ADMIN_PASSWORD_HASH', '".addslashes($passwordHash)."');",
            $configContent
        );
        
        file_put_contents('config.php', $configContent);
        
        // 创建安装锁定文件
        file_put_contents('installed.lock', 'This file indicates that the installation is complete.');
        
        $success = '系统安装成功！<a href="login.php">点击这里登录</a>';
    } catch (Exception $e) {
        $error = '安装失败: ' . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>系统安装向导</title>
    <link href="https://cdn.bootcdn.net/ajax/libs/twitter-bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .install-container {
            max-width: 800px;
            margin: 50px auto;
            padding: 30px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
        }
        .step-indicator {
            display: flex;
            margin-bottom: 30px;
        }
        .step {
            flex: 1;
            text-align: center;
            padding: 10px;
            position: relative;
            color: #6c757d;
        }
        .step.active {
            color: #0d6efd;
            font-weight: bold;
        }
        .step.completed {
            color: #198754;
        }
        .step:not(:last-child):after {
            content: '';
            position: absolute;
            top: 50%;
            right: 0;
            width: 100%;
            height: 2px;
            background: #dee2e6;
            z-index: 1;
        }
        .step.completed:not(:last-child):after {
            background: #198754;
        }
        .step-number {
            display: inline-block;
            width: 30px;
            height: 30px;
            line-height: 30px;
            border-radius: 50%;
            background: #dee2e6;
            color: white;
            margin-bottom: 5px;
        }
        .step.active .step-number {
            background: #0d6efd;
        }
        .step.completed .step-number {
            background: #198754;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="install-container">
            <h2 class="text-center mb-4">图片管理系统安装向导</h2>
            
            <div class="step-indicator">
                <div class="step active">
                    <div class="step-number">1</div>
                    <div>数据库配置</div>
                </div>
                <div class="step">
                    <div class="step-number">2</div>
                    <div>管理员设置</div>
                </div>
                <div class="step">
                    <div class="step-number">3</div>
                    <div>完成安装</div>
                </div>
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php else: ?>
                <form method="POST">
                    <h4 class="mb-3">数据库配置</h4>
                    <div class="mb-3">
                        <label for="db_host" class="form-label">数据库主机</label>
                        <input type="text" class="form-control" id="db_host" name="db_host" value="localhost" required>
                    </div>
                    <div class="mb-3">
                        <label for="db_name" class="form-label">数据库名称</label>
                        <input type="text" class="form-control" id="db_name" name="db_name" value="r2_image_manager" required>
                    </div>
                    <div class="mb-3">
                        <label for="db_user" class="form-label">数据库用户名</label>
                        <input type="text" class="form-control" id="db_user" name="db_user" required>
                    </div>
                    <div class="mb-3">
                        <label for="db_pass" class="form-label">数据库密码</label>
                        <input type="password" class="form-control" id="db_pass" name="db_pass">
                    </div>
                    
                    <hr class="my-4">
                    
                    <h4 class="mb-3">管理员账户</h4>
                    <div class="mb-3">
                        <label for="admin_user" class="form-label">管理员用户名</label>
                        <input type="text" class="form-control" id="admin_user" name="admin_user" value="admin" required>
                    </div>
                    <div class="mb-3">
                        <label for="admin_pass" class="form-label">管理员密码</label>
                        <input type="password" class="form-control" id="admin_pass" name="admin_pass" required>
                        <div class="form-text">请使用强密码，至少8个字符，包含字母和数字</div>
                    </div>
                    <div class="mb-3">
                        <label for="confirm_pass" class="form-label">确认密码</label>
                        <input type="password" class="form-control" id="confirm_pass" name="confirm_pass" required>
                    </div>
                    
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary btn-lg">开始安装</button>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.bootcdn.net/ajax/libs/twitter-bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script>
        // 密码确认验证
        document.querySelector('form').addEventListener('submit', function(e) {
            const password = document.getElementById('admin_pass').value;
            const confirmPass = document.getElementById('confirm_pass').value;
            
            if (password !== confirmPass) {
                alert('两次输入的密码不一致！');
                e.preventDefault();
                return false;
            }
            
            if (password.length < 8) {
                alert('密码长度至少需要8个字符！');
                e.preventDefault();
                return false;
            }
            
            return true;
        });
    </script>
</body>
</html>
