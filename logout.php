<?php
require 'config.php';

// 销毁会话
$_SESSION = array();
session_destroy();

// 重定向到登录页
header("Location: login.php");
exit;
?>