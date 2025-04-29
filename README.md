# cloudflare_r2_images
# CloudFlare R2对象存储图片管理系统

这里更新可能慢，最新版请往


#### 一、环境要求：

1、php 8.x+(建议8.2以上，因为AWS SDK 要求高)

2、php需要安装fileinfo扩展

3、需要用 Composer 安装 AWS SDK：

ssh 进入网站目录：cd /www/wwwroot/images_我是网站目录_com

    composer require aws/aws-sdk-php

![](https://images.0880.top/images_681042c8631408.48435121.png)

#### 二、Cloudflare R2 的API数据填入config.php文件

    // Cloudflare R2 配置 
    define('R2_ENDPOINT', 'https://我是网址的一部分.r2.cloudflarestorage.com');   // 您的特定端点 (为 S3 客户端使用管辖权地特定的终结点：默认欧盟 (EU))
    define('R2_ACCESS_KEY_ID', '访问密钥 ID(需替换)');  // 访问密钥 ID 
    define('R2_SECRET_ACCESS_KEY', '机密访问密钥(需替换)');  // 机密访问密钥 
    define('R2_BUCKET', 'images');  // 您的存储桶名称(需替换)
    define('R2_PUBLIC_URL', '您的公开访问域名(需替换，不需要“/”)');   // 您的公开访问域名(需替换) 例如：http://images.images.com

![](https://images.0880.top/images_681042b3373455.68192302.png)
![](https://github.com/msdnos/cloudflare_r2_images/blob/main/demo_images/demo002.png)
#### 三、整体预览：

![](https://images.0880.top/images_68103e6e174be3.26838445.png)





