<?php

// 检查是否已安装
if (!file_exists('installed.lock') && basename($_SERVER['SCRIPT_NAME']) !== 'install.php') {
    header("Location: install.php");
    exit;
}

// 会话启动
session_start();

// 数据库配置 (用于用户认证)
define('DB_HOST', 'localhost');
define('DB_USER', '用户名');
define('DB_PASS', '密码');
define('DB_NAME', '数据库名');

// Cloudflare R2 配置 
define('R2_ENDPOINT', 'https://我是网址的一部分.r2.cloudflarestorage.com');   // 您的特定端点 (为 S3 客户端使用管辖权地特定的终结点：默认欧盟 (EU))
define('R2_ACCESS_KEY_ID', '访问密钥 ID(需替换)');  // 访问密钥 ID 
define('R2_SECRET_ACCESS_KEY', '机密访问密钥(需替换)');  // 机密访问密钥 
define('R2_BUCKET', 'images');  // 您的存储桶名称(需替换)
define('R2_PUBLIC_URL', '您的公开访问域名(需替换，不需要“/”)');   // 您的公开访问域名(需替换) 例如：http://images.images.com

// 用户认证配置
define('ADMIN_USERNAME', 'admin');
define('ADMIN_PASSWORD_HASH', password_hash('your_secure_password', PASSWORD_DEFAULT));

// 包含 AWS SDK
require 'vendor/autoload.php';

use Aws\S3\S3Client;
use Aws\Exception\AwsException;

function getS3Client() {
    return new S3Client([
        'version' => 'latest',
        'region' => 'auto',
        'endpoint' => R2_ENDPOINT,
        'credentials' => [
            'key' => R2_ACCESS_KEY_ID,
            'secret' => R2_SECRET_ACCESS_KEY,
        ],
        'use_path_style_endpoint' => true,
        'defaults_mode' => 'legacy', // 禁用默认配置加载
        'disable_default_config' => true // 明确禁用默认配置
    ]);
}

// 数据库连接
function getDBConnection() {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($conn->connect_error) {
        die("数据库连接失败: " . $conn->connect_error);
    }
    return $conn;
}

// 检查用户是否登录
function isLoggedIn() {
    return isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true;
}

// 重定向到登录页
function redirectToLogin() {
    header("Location: login.php");
    exit;
}
?>