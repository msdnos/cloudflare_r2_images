<?php 
require 'config.php'; 
 
if (!isLoggedIn()) {
    redirectToLogin();
}
 
$s3 = getS3Client();
$error = '';
$success = '';
 
// 处理文件上传 
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_FILES['images']) || isset($_POST['dragUpload']))) {
    try {
        // 防止重复处理 
        static $uploadProcessed = false;
        if ($uploadProcessed) {
            throw new Exception('上传请求已被处理');
        }
        $uploadProcessed = true;
        
        $uploadedFiles = [];
        
        if (isset($_FILES['images'])) {
            // 传统表单上传处理 
            $uploadedFiles = $_FILES['images'];
            
            // 处理多文件上传 
            for ($i = 0; $i < count($uploadedFiles['name']); $i++) {
                if ($uploadedFiles['error'][$i] !== UPLOAD_ERR_OK) {
                    continue;
                }
                
                $fileTmpPath = $uploadedFiles['tmp_name'][$i];
                $fileName = basename($uploadedFiles['name'][$i]);
                $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
                
                // 生成唯一文件名 
                $newFileName = uniqid('images_', true) . '.' . $fileExtension;
                
                // 检查是否已存在同名文件 
                if (!$s3->doesObjectExist(R2_BUCKET, $newFileName)) {
                    $s3->putObject([
                        'Bucket' => R2_BUCKET,
                        'Key' => $newFileName,
                        'Body' => fopen($fileTmpPath, 'rb'),
                        'ACL' => 'public-read',
                        'ContentType' => mime_content_type($fileTmpPath)
                    ]);
                }
            }
        } elseif (isset($_POST['dragUpload'])) {
            // 拖动上传处理 - 改进版 
            $files = json_decode($_POST['dragUpload'], true);
            
            foreach ($files as $file) {
                $data = explode(',', $file['data']);
                $fileData = base64_decode($data[1]);
                $fileExtension = pathinfo($file['name'], PATHINFO_EXTENSION);
                $newFileName = uniqid('img_', true) . '.' . $fileExtension;
                
                // 检查是否已存在同名文件 
                if (!$s3->doesObjectExist(R2_BUCKET, $newFileName)) {
                    // 创建临时文件并上传 
                    $tempFilePath = sys_get_temp_dir() . '/' . $newFileName;
                    file_put_contents($tempFilePath, $fileData);
                    
                    $s3->putObject([
                        'Bucket' => R2_BUCKET,
                        'Key' => $newFileName,
                        'Body' => fopen($tempFilePath, 'rb'),
                        'ACL' => 'public-read',
                        'ContentType' => $file['type']
                    ]);
                    
                    // 上传完成后删除临时文件 
                    unlink($tempFilePath);
                }
            }
        }
        
        $success = '图片上传成功！';
    } catch (Exception $e) {
        $error = '上传失败: ' . $e->getMessage();
    }
}
 
// 处理删除请求 
if (isset($_GET['delete'])) {
    $key = $_GET['delete'];
    try {
        $s3->deleteObject([
            'Bucket' => R2_BUCKET,
            'Key' => $key 
        ]);
        $success = '图片删除成功！';
    } catch (AwsException $e) {
        $error = '删除失败: ' . $e->getMessage();
    }
}
 
// 批量删除 
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_selected'])) {
    $selected = $_POST['selected'] ?? [];
    if (!empty($selected)) {
        try {
            $objects = [];
            foreach ($selected as $key) {
                $objects[] = ['Key' => $key];
            }
            
            $s3->deleteObjects([
                'Bucket' => R2_BUCKET,
                'Delete' => [
                    'Objects' => $objects 
                ]
            ]);
            
            $success = '已成功删除选中的图片！';
        } catch (AwsException $e) {
            $error = '批量删除失败: ' . $e->getMessage();
        }
    } else {
        $error = '请至少选择一张图片删除';
    }
}
 
// 获取存储桶中的文件列表并按上传时间降序排序 
try {
    $objects = $s3->listObjectsV2(['Bucket' => R2_BUCKET]);
    $images = $objects->get('Contents') ?: [];
    
    // 按最后修改时间排序，新上传的排前面 
    usort($images, function($a, $b) {
        return strtotime($b['LastModified']) - strtotime($a['LastModified']);
    });
} catch (AwsException $e) {
    $error = '获取图片列表失败: ' . $e->getMessage();
    $images = [];
}
?>
 
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CloudFlare R2对象存储图片管理系统</title>
    <link rel="icon" href="logo.svg" type="image/svg+xml">
    <link href="https://cdn.9930.top:9930/twitter-bootstrap/5.3.0/css/bootstrap.min.css"  rel="stylesheet">
    <link href="https://cdn.9930.top:9930/bootstrap-icons/1.10.0/font/bootstrap-icons.css"  rel="stylesheet">
    <style>
        .drop-area {
            border: 3px dashed #ccc;
            border-radius: 8px;
            padding: 20px;
            text-align: center;
            transition: all 0.3s;
            margin-bottom: 20px;
        }
        .drop-area.highlight  {
            border-color: #0d6efd;
            background-color: rgba(13, 110, 253, 0.05);
        }
        .upload-icon {
            font-size: 3rem;
            color: #6c757d;
            margin-bottom: 1rem;
        }
        .preview-container {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 20px;
        }
        .preview-item {
            position: relative;
            width: 120px;
            height: 120px;
            border: 1px solid #ddd;
            border-radius: 4px;
            overflow: hidden;
        }
        .preview-item img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .preview-item .remove-btn {
            position: absolute;
            top: 5px;
            right: 5px;
            width: 25px;
            height: 25px;
            background-color: rgba(255, 0, 0, 0.7);
            border: none;
            border-radius: 50%;
            color: white;
            font-size: 12px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .hidden {
            display: none;
        }
        .btn-upload {
            position: relative;
            overflow: hidden;
        }
        .btn-upload input[type="file"] {
            position: absolute;
            top: 0;
            right: 0;
            min-width: 100%;
            min-height: 100%;
            font-size: 100px;
            text-align: right;
            filter: alpha(opacity=0);
            opacity: 0;
            outline: none;
            background: white;
            cursor: inherit;
            display: block;
        }
        .select-all-container {
            margin-bottom: 15px;
        }
        /* 新增底部样式 */
        footer {
            background-color: #343a40;
            color: white;
            padding: 20px 0;
            margin-top: 30px;
        }
        .footer-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .footer-links a {
            color: rgba(255, 255, 255, 0.75);
            margin-right: 15px;
            text-decoration: none;
        }
        .footer-links a:hover {
            color: white;
            text-decoration: underline;
        }
        .footer-copyright {
            color: rgba(255, 255, 255, 0.5);
            font-size: 0.9rem;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="admin.php"><i class="bi bi-images"></i> CloudFlare R2对象存储图片管理系统</a>
            <div class="navbar-nav">
                <a class="nav-link" href="logout.php"><i class="bi bi-arrow-down-right-square"></i> 退出登录</a>
            </div>
        </div>
    </nav>
 
    <div class="container mt-4">
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
 
        <div class="card mb-4">
            <div class="card-header">
                <h5><i class="bi bi-arrow-up-circle"></i> 上传图片</h5>
            </div>
            <div class="card-body">
                <div id="dropArea" class="drop-area">
                    <div class="upload-icon">
                        <i class="bi bi-cloud-arrow-up"></i>
                    </div>
                    <h5>拖动图片到此处上传</h5>
                    <p class="text-muted">或</p>
                    <div class="btn btn-primary btn-upload"><i class="bi bi-folder-plus"></i>
                        选择文件 
                        <input type="file" id="fileInput" multiple accept="image/*">
                    </div>
                </div>
                
                <div id="previewContainer" class="preview-container"></div>
                
                <form id="uploadForm" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="dragUpload" id="dragUploadInput">
                    <input type="file" id="hiddenFileInput" name="images[]" multiple accept="image/*" class="hidden">
                    <button type="submit" id="uploadBtn" class="btn btn-primary hidden">开始上传</button>
                </form>
                
                <div id="progressContainer" class="mt-3 hidden">
                    <div class="progress">
                        <div id="progressBar" class="progress-bar" role="progressbar" style="width: 0%"></div>
                    </div>
                    <div id="progressText" class="text-center mt-2">准备上传...</div>
                </div>
            </div>
        </div>
 
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5><i class="bi bi-images"></i> 图片列表</h5>
                <?php if (!empty($images)): ?>
                    <button type="button" class="btn btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#deleteModal">
                        <i class="bi bi-trash"></i> 批量删除 
                    </button>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <?php if (empty($images)): ?>
                    <div class="text-center py-4">
                        <i class="bi bi-images fs-1 text-muted"></i>
                        <p class="mt-2">暂无图片</p>
                    </div>
                <?php else: ?>
                    <div class="select-all-container">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="selectAllCheckbox">
                            <label class="form-check-label" for="selectAllCheckbox">
                                全选/取消全选 
                            </label>
                        </div>
                    </div>
                    <form id="imagesForm" method="POST">
                        <div class="row row-cols-2 row-cols-md-3 row-cols-lg-4 g-4">
                            <?php foreach ($images as $image): ?>
                                <div class="col">
                                    <div class="card h-100">
                                        <div class="position-absolute top-0 start-0 p-2">
                                            <input type="checkbox" class="form-check-input image-checkbox" name="selected[]" value="<?php echo htmlspecialchars($image['Key']); ?>">
                                        </div>
                                        <img src="<?php echo R2_PUBLIC_URL . '/' . htmlspecialchars($image['Key']); ?>" class="card-img-top img-thumbnail" alt="图片" style="height: 200px; object-fit: contain;">
                                        <div class="card-body text-center">
                                            <small class="text-muted d-block"><?php echo htmlspecialchars($image['Key']); ?></small>
                                            <small class="text-muted d-block"><?php echo round($image['Size'] / 1024, 2); ?> KB</small>
                                            <small class="text-muted d-block"><?php echo date('Y-m-d H:i:s', strtotime($image['LastModified'])); ?></small>
                                        </div>
                                        <div class="card-footer bg-transparent">
                                            <a href="<?php echo R2_PUBLIC_URL . '/' . htmlspecialchars($image['Key']); ?>" target="_blank" class="btn btn-sm btn-outline-primary w-100 mb-2">
                                                <i class="bi bi-eye"></i> 查看 
                                            </a>
                                            <a href="admin.php?delete=<?php  echo htmlspecialchars($image['Key']); ?>" class="btn btn-sm btn-outline-danger w-100" onclick="return confirm('确定要删除这张图片吗？')">
                                                <i class="bi bi-trash"></i> 删除 
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
 
    <!-- 新增底部footer -->
    <footer>
        <div class="container">
            <div class="footer-content">
                <div class="footer-links">
                    <a href="https://github.com/msdnos/cloudflare_r2_images">Github</a>
                    <a href="https://github.com/msdnos/cloudflare_r2_images/blob/main/README.md">使用帮助</a>
                    <a href="https://www.wogaosuni.com">最新版本</a>
                    <a href="https://2220.top">教坊司</a>
                </div>
                <div class="footer-copyright">
                    &copy; <?php echo date('Y'); ?> CloudFlare R2对象存储图片管理系统 v0.9 版权所有 
                </div>
            </div>
        </div>
    </footer>
 
    <!-- 批量删除确认模态框 -->
    <div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="deleteModalLabel">确认删除</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    确定要删除选中的图片吗？此操作不可撤销。
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">取消</button>
                    <button type="submit" form="imagesForm" name="delete_selected" class="btn btn-danger">确认删除</button>
                </div>
            </div>
        </div>
    </div>
 
    <script src="https://cdn.9930.top:9930/twitter-bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script>
        (function() {
            const dropArea = document.getElementById('dropArea'); 
            const fileInput = document.getElementById('fileInput'); 
            const hiddenFileInput = document.getElementById('hiddenFileInput'); 
            const uploadBtn = document.getElementById('uploadBtn'); 
            const uploadForm = document.getElementById('uploadForm'); 
            const previewContainer = document.getElementById('previewContainer'); 
            const dragUploadInput = document.getElementById('dragUploadInput'); 
            const progressContainer = document.getElementById('progressContainer'); 
            const progressBar = document.getElementById('progressBar'); 
            const progressText = document.getElementById('progressText'); 
            const selectAllCheckbox = document.getElementById('selectAllCheckbox'); 
            const imageCheckboxes = document.querySelectorAll('.image-checkbox'); 
            
            let filesToUpload = [];
            let isUploading = false;
            
            // 全选/取消全选功能 
            if (selectAllCheckbox) {
                selectAllCheckbox.addEventListener('change',  function() {
                    const checkboxes = document.querySelectorAll('.image-checkbox'); 
                    checkboxes.forEach(checkbox  => {
                        checkbox.checked  = selectAllCheckbox.checked; 
                    });
                });
            }
            
            // 当单个复选框状态改变时，检查是否需要更新全选复选框状态 
            if (imageCheckboxes) {
                imageCheckboxes.forEach(checkbox  => {
                    checkbox.addEventListener('change',  function() {
                        const allChecked = [...document.querySelectorAll('.image-checkbox')].every(cb  => cb.checked); 
                        selectAllCheckbox.checked  = allChecked;
                    });
                });
            }
            
            // 防止默认拖放行为 
            ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
                dropArea.addEventListener(eventName,  preventDefaults, false);
                document.body.addEventListener(eventName,  preventDefaults, false);
            });
            
            function preventDefaults(e) {
                e.preventDefault(); 
                e.stopPropagation(); 
            }
            
            // 高亮显示拖放区域 
            ['dragenter', 'dragover'].forEach(eventName => {
                dropArea.addEventListener(eventName,  highlight, false);
            });
            
            ['dragleave', 'drop'].forEach(eventName => {
                dropArea.addEventListener(eventName,  unhighlight, false);
            });
            
            function highlight() {
                dropArea.classList.add('highlight'); 
            }
            
            function unhighlight() {
                dropArea.classList.remove('highlight'); 
            }
            
            // 处理拖放文件 
            dropArea.addEventListener('drop',  handleDrop, false);
            
            function handleDrop(e) {
                const dt = e.dataTransfer; 
                const files = dt.files; 
                
                if (files.length)  {
                    handleFiles(files);
                }
            }
            
            // 处理文件选择 
            fileInput.addEventListener('change',  function() {
                if (this.files.length)  {
                    handleFiles(this.files); 
                }
            });
            
            // 处理文件 
            function handleFiles(files) {
                if (isUploading) return;
                
                filesToUpload = [];
                previewContainer.innerHTML  = '';
                
                for (let i = 0; i < files.length;  i++) {
                    const file = files[i];
                    
                    if (!file.type.match('image.*'))  {
                        continue;
                    }
                    
                    filesToUpload.push(file); 
                    
                    const reader = new FileReader();
                    
                    reader.onload  = function(e) {
                        const previewItem = document.createElement('div'); 
                        previewItem.className  = 'preview-item';
                        
                        const img = document.createElement('img'); 
                        img.src  = e.target.result; 
                        img.alt  = file.name; 
                        
                        const removeBtn = document.createElement('button'); 
                        removeBtn.className  = 'remove-btn';
                        removeBtn.innerHTML  = '×';
                        removeBtn.addEventListener('click',  function() {
                            previewItem.remove(); 
                            filesToUpload = filesToUpload.filter(f  => f !== file);
                            
                            if (filesToUpload.length  === 0) {
                                uploadBtn.classList.add('hidden'); 
                            }
                        });
                        
                        previewItem.appendChild(img); 
                        previewItem.appendChild(removeBtn); 
                        previewContainer.appendChild(previewItem); 
                    };
                    
                    reader.readAsDataURL(file); 
                }
                
                if (filesToUpload.length  > 0) {
                    uploadBtn.classList.remove('hidden'); 
                } else {
                    alert('请选择有效的图片文件');
                }
            }
            
            // 处理上传 
            uploadForm.addEventListener('submit',  function(e) {
                e.preventDefault(); 
                
                if (isUploading) return;
                if (filesToUpload.length  === 0) {
                    alert('请先选择要上传的文件');
                    return;
                }
                
                isUploading = true;
                uploadBtn.disabled  = true;
                progressContainer.classList.remove('hidden'); 
                progressBar.style.width  = '0%';
                progressText.textContent  = '准备上传...';
                
                // 判断是哪种上传方式 
                if (fileInput.files  && fileInput.files.length  > 0 && fileInput.files.length  === filesToUpload.length)  {
                    // 传统表单上传方式 
                    hiddenFileInput.files  = fileInput.files; 
                    
                    const xhr = new XMLHttpRequest();
                    
                    xhr.upload.onprogress  = function(e) {
                        if (e.lengthComputable)  {
                            const percentComplete = Math.round((e.loaded  / e.total)  * 100);
                            progressBar.style.width  = percentComplete + '%';
                            progressText.textContent  = `上传中: ${percentComplete}%`;
                        }
                    };
                    
                    xhr.onload  = function() {
                        isUploading = false;
                        uploadBtn.disabled  = false;
                        
                        if (xhr.status  === 200) {
                            progressBar.style.width  = '100%';
                            progressText.textContent  = '上传完成！';
                            setTimeout(() => {
                                window.location.reload(); 
                            }, 1000);
                        } else {
                            progressText.textContent  = '上传失败: ' + xhr.statusText; 
                        }
                    };
                    
                    xhr.onerror  = function() {
                        isUploading = false;
                        uploadBtn.disabled  = false;
                        progressText.textContent  = '上传出错，请重试';
                    };
                    
                    xhr.open('POST',  '', true);
                    xhr.send(new  FormData(uploadForm));
                } else {
                    // 拖动上传方式 - 改进版 
                    const formData = new FormData();
                    const totalFiles = filesToUpload.length; 
                    let processedFiles = 0;
                    
                    // 使用Promise链式调用确保顺序处理 
                    const uploadPromises = [];
                    
                    filesToUpload.forEach(file  => {
                        uploadPromises.push( 
                            new Promise((resolve) => {
                                const reader = new FileReader();
                                reader.onload  = function(e) {
                                    const fileName = file.name; 
                                    const fileType = file.type; 
                                    const fileData = e.target.result; 
                                    
                                    // 创建一个隐藏的file input 
                                    const tempInput = document.createElement('input'); 
                                    tempInput.type  = 'file';
                                    tempInput.name  = 'images[]';
                                    
                                    // 创建一个新的Blob对象 
                                    const blob = new Blob([file], { type: fileType });
                                    
                                    // 创建一个DataTransfer对象来设置files 
                                    const dataTransfer = new DataTransfer();
                                    dataTransfer.items.add(new  File([blob], fileName, { type: fileType }));
                                    tempInput.files  = dataTransfer.files; 
                                    
                                    // 添加到FormData 
                                    formData.append('images[]',  tempInput.files[0]); 
                                    
                                    processedFiles++;
                                    const prepPercent = Math.round((processedFiles  / totalFiles) * 50);
                                    progressBar.style.width  = prepPercent + '%';
                                    progressText.textContent  = `准备文件中: ${prepPercent}%`;
                                    
                                    resolve();
                                };
                                reader.readAsArrayBuffer(file); 
                            })
                        );
                    });
                    
                    // 所有文件准备完成后开始上传 
                    Promise.all(uploadPromises).then(()  => {
                        const xhr = new XMLHttpRequest();
                        
                        xhr.upload.onprogress  = function(e) {
                            if (e.lengthComputable)  {
                                const uploadPercent = Math.round((e.loaded  / e.total)  * 50) + 50;
                                progressBar.style.width  = uploadPercent + '%';
                                progressText.textContent  = `上传中: ${uploadPercent}%`;
                            }
                        };
                        
                        xhr.onload  = function() {
                            isUploading = false;
                            uploadBtn.disabled  = false;
                            
                            if (xhr.status  === 200) {
                                progressBar.style.width  = '100%';
                                progressText.textContent  = '上传完成！';
                                setTimeout(() => {
                                    window.location.reload(); 
                                }, 1000);
                            } else {
                                progressText.textContent  = '上传失败: ' + xhr.statusText; 
                            }
                        };
                        
                        xhr.onerror  = function() {
                            isUploading = false;
                            uploadBtn.disabled  = false;
                            progressText.textContent  = '上传出错，请重试';
                        };
                        
                        xhr.open('POST',  '', true);
                        xhr.send(formData); 
                    });
                }
            });
        })();
    </script>
   <script>
        function adjustFooter() {
            const body = document.body; 
            const html = document.documentElement; 
            const footer = document.querySelector('footer'); 
 
            // 检查页面内容高度是否小于视口高度 
            if (body.scrollHeight  <= window.innerHeight)  {
                footer.style.position  = 'fixed';
                footer.style.bottom  = '0';
                footer.style.width  = '100%';
            } else {
                footer.style.position  = 'static';
            }
        }
 
        // 初始化时调用 
        adjustFooter();
 
        // 监听窗口大小变化 
        window.addEventListener('resize',  adjustFooter);
 
        // 监听内容变化（例如动态加载内容）
        const observer = new MutationObserver(adjustFooter);
        observer.observe(document.body,  { childList: true, subtree: true });
    </script>
</body>
</html>