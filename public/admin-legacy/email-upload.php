<?php
declare(strict_types=1);

session_start();

// Check admin authentication
if (!isset($_SESSION['admin_logged_in']) || !$_SESSION['admin_logged_in']) {
    header('Location: /admin/login.php');
    exit;
}

require_once dirname(__DIR__) . '/vendor/autoload.php';

$basePath = '';
$uploadDir = dirname(__DIR__) . '/storage/emails';
$pendingDir = $uploadDir . '/pending_events';

// Ensure directories exist
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}
if (!is_dir($pendingDir)) {
    mkdir($pendingDir, 0755, true);
}

$message = '';
$messageType = '';

// Handle file upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['email_file'])) {
    try {
        $file = $_FILES['email_file'];
        
        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('File upload failed');
        }
        
        $allowedTypes = ['text/plain', 'message/rfc822', 'application/octet-stream'];
        $allowedExtensions = ['eml', 'txt', 'msg'];
        
        $pathInfo = pathinfo($file['name']);
        $extension = strtolower($pathInfo['extension'] ?? '');
        
        if (!in_array($extension, $allowedExtensions)) {
            throw new Exception('Only .eml, .txt, or .msg files are allowed');
        }
        
        $fileName = 'email_' . time() . '_' . uniqid() . '.eml';
        $targetPath = $uploadDir . '/' . $fileName;
        
        if (move_uploaded_file($file['tmp_name'], $targetPath)) {
            $message = "Email file uploaded successfully: $fileName";
            $messageType = 'success';
        } else {
            throw new Exception('Failed to save uploaded file');
        }
        
    } catch (Exception $e) {
        $message = 'Upload error: ' . $e->getMessage();
        $messageType = 'error';
    }
}

// Handle email processing
if (isset($_POST['process_emails'])) {
    try {
        require_once dirname(__DIR__) . '/src/Infrastructure/Email/CurlEmailProcessor.php';
        $processor = new \YFEvents\Infrastructure\Email\CurlEmailProcessor([]);
        $results = $processor->processEmails();
        
        $message = "Processing complete!\n";
        $message .= "Processed: {$results['processed']} emails\n";
        $message .= "Errors: {$results['errors']}\n";
        if (!empty($results['messages'])) {
            $message .= "\nDetails:\n" . implode("\n", $results['messages']);
        }
        $messageType = $results['errors'] > 0 ? 'warning' : 'success';
        
    } catch (Exception $e) {
        $message = 'Processing error: ' . $e->getMessage();
        $messageType = 'error';
    }
}

// Get file lists
$uploadedFiles = glob($uploadDir . '/*.eml') ?: [];
$processedFiles = glob($uploadDir . '/processed/*.eml') ?: [];
$pendingEvents = glob($pendingDir . '/*.json') ?: [];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Upload & Processing - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="<?= $basePath ?>/css/admin-theme.css">
    <style>
        .upload-section {
            background: white;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }
        .file-list {
            max-height: 300px;
            overflow-y: auto;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 15px;
        }
        .file-item {
            padding: 8px;
            border-bottom: 1px solid #e9ecef;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .file-item:last-child {
            border-bottom: none;
        }
        .drag-drop-area {
            border: 2px dashed #dee2e6;
            border-radius: 10px;
            padding: 40px;
            text-align: center;
            background: #f8f9fa;
            margin-bottom: 20px;
            transition: all 0.3s ease;
        }
        .drag-drop-area.dragover {
            border-color: #007bff;
            background: #e3f2fd;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 25px;
        }
        .stat-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            text-align: center;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }
        .stat-number {
            font-size: 2rem;
            font-weight: bold;
            color: #2c3e50;
        }
        .stat-label {
            color: #6c757d;
            margin-top: 5px;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar Navigation -->
            <nav class="col-md-2 d-md-block bg-light sidebar">
                <div class="sidebar-sticky pt-3">
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link" href="<?= $basePath ?>/admin/dashboard">Dashboard</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="<?= $basePath ?>/admin/events.php">Events</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="<?= $basePath ?>/admin/email-events.php">Email Events</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="<?= $basePath ?>/admin/email-config.php">Email Config</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="<?= $basePath ?>/admin/email-upload.php">Email Upload</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="<?= $basePath ?>/admin/shops.php">Shops</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="<?= $basePath ?>/admin/theme.php">Theme</a>
                        </li>
                    </ul>
                </div>
            </nav>

            <!-- Main Content -->
            <main class="col-md-10 ml-sm-auto col-lg-10 px-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1>📧 Email Upload & Processing</h1>
                </div>

                <?php if ($message): ?>
                    <div class="alert alert-<?= $messageType === 'success' ? 'success' : ($messageType === 'warning' ? 'warning' : 'danger') ?> alert-dismissible fade show">
                        <pre style="margin: 0; white-space: pre-wrap;"><?= htmlspecialchars($message) ?></pre>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Statistics -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-number"><?= count($uploadedFiles) ?></div>
                        <div class="stat-label">Pending Processing</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?= count($processedFiles) ?></div>
                        <div class="stat-label">Processed Files</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?= count($pendingEvents) ?></div>
                        <div class="stat-label">Extracted Events</div>
                    </div>
                </div>

                <!-- File Upload -->
                <div class="upload-section">
                    <h3>📤 Upload Email Files</h3>
                    <p class="text-muted">Upload .eml email files to extract Facebook event information</p>
                    
                    <form method="post" enctype="multipart/form-data" id="uploadForm">
                        <div class="drag-drop-area" id="dragDropArea">
                            <div class="mb-3">
                                <i class="fas fa-cloud-upload-alt fa-3x text-muted mb-3"></i>
                                <h5>Drag & Drop Email Files Here</h5>
                                <p class="text-muted">or click to browse</p>
                                <input type="file" class="form-control" name="email_file" accept=".eml,.txt,.msg" id="fileInput" style="display: none;">
                                <button type="button" class="btn btn-outline-primary" onclick="document.getElementById('fileInput').click()">
                                    Choose File
                                </button>
                            </div>
                        </div>
                        
                        <div class="text-end">
                            <button type="submit" class="btn btn-primary">
                                📧 Upload Email
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Process Emails -->
                <?php if (count($uploadedFiles) > 0): ?>
                <div class="upload-section">
                    <h3>⚡ Process Uploaded Emails</h3>
                    <p class="text-muted">Extract event information from uploaded email files</p>
                    
                    <form method="post">
                        <button type="submit" name="process_emails" class="btn btn-success btn-lg">
                            🔄 Process All Emails (<?= count($uploadedFiles) ?> files)
                        </button>
                    </form>
                </div>
                <?php endif; ?>

                <!-- File Lists -->
                <div class="row">
                    <!-- Pending Files -->
                    <div class="col-md-4">
                        <div class="upload-section">
                            <h4>📋 Pending Files (<?= count($uploadedFiles) ?>)</h4>
                            <div class="file-list">
                                <?php if (empty($uploadedFiles)): ?>
                                    <div class="text-muted">No files pending processing</div>
                                <?php else: ?>
                                    <?php foreach ($uploadedFiles as $file): ?>
                                        <div class="file-item">
                                            <span><?= basename($file) ?></span>
                                            <small class="text-muted"><?= number_format(filesize($file) / 1024, 1) ?> KB</small>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Extracted Events -->
                    <div class="col-md-4">
                        <div class="upload-section">
                            <h4>🎉 Extracted Events (<?= count($pendingEvents) ?>)</h4>
                            <div class="file-list">
                                <?php if (empty($pendingEvents)): ?>
                                    <div class="text-muted">No events extracted yet</div>
                                <?php else: ?>
                                    <?php foreach ($pendingEvents as $file): ?>
                                        <?php
                                        $data = json_decode(file_get_contents($file), true);
                                        $eventTitle = $data['event_data']['title'] ?? 'Unknown Event';
                                        ?>
                                        <div class="file-item">
                                            <div>
                                                <div><?= htmlspecialchars($eventTitle) ?></div>
                                                <small class="text-muted"><?= $data['extracted_at'] ?? '' ?></small>
                                            </div>
                                            <span class="badge bg-warning">Pending Review</span>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Processed Files -->
                    <div class="col-md-4">
                        <div class="upload-section">
                            <h4>✅ Processed Files (<?= count($processedFiles) ?>)</h4>
                            <div class="file-list">
                                <?php if (empty($processedFiles)): ?>
                                    <div class="text-muted">No files processed yet</div>
                                <?php else: ?>
                                    <?php foreach (array_slice($processedFiles, -10) as $file): ?>
                                        <div class="file-item">
                                            <span><?= basename($file) ?></span>
                                            <small class="text-muted">✓</small>
                                        </div>
                                    <?php endforeach; ?>
                                    <?php if (count($processedFiles) > 10): ?>
                                        <div class="text-muted text-center">... and <?= count($processedFiles) - 10 ?> more</div>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Instructions -->
                <div class="upload-section">
                    <h3>📋 How to Get Email Files</h3>
                    <div class="row">
                        <div class="col-md-6">
                            <h5>📧 Gmail:</h5>
                            <ol>
                                <li>Open the Facebook event email</li>
                                <li>Click the three dots menu (⋮)</li>
                                <li>Select "Download message"</li>
                                <li>Save as .eml file</li>
                                <li>Upload here</li>
                            </ol>
                        </div>
                        <div class="col-md-6">
                            <h5>📨 Outlook:</h5>
                            <ol>
                                <li>Open the email</li>
                                <li>File → Save As</li>
                                <li>Choose "Outlook Message Format (.msg)"</li>
                                <li>Upload the .msg file here</li>
                            </ol>
                        </div>
                    </div>
                    
                    <div class="alert alert-info mt-3">
                        <strong>💡 Pro Tip:</strong> You can also forward Facebook event emails as attachments to yourself, 
                        then download and upload the attached .eml files.
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Drag and drop functionality
        const dragDropArea = document.getElementById('dragDropArea');
        const fileInput = document.getElementById('fileInput');

        dragDropArea.addEventListener('dragover', (e) => {
            e.preventDefault();
            dragDropArea.classList.add('dragover');
        });

        dragDropArea.addEventListener('dragleave', () => {
            dragDropArea.classList.remove('dragover');
        });

        dragDropArea.addEventListener('drop', (e) => {
            e.preventDefault();
            dragDropArea.classList.remove('dragover');
            
            const files = e.dataTransfer.files;
            if (files.length > 0) {
                fileInput.files = files;
                // Auto-submit form
                document.getElementById('uploadForm').submit();
            }
        });

        dragDropArea.addEventListener('click', () => {
            fileInput.click();
        });

        fileInput.addEventListener('change', () => {
            if (fileInput.files.length > 0) {
                // Auto-submit form when file is selected
                document.getElementById('uploadForm').submit();
            }
        });
    </script>
</body>
</html>