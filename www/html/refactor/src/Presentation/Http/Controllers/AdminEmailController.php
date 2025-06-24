<?php

declare(strict_types=1);

namespace YFEvents\Presentation\Http\Controllers;

use YFEvents\Infrastructure\Services\EmailEventProcessor;
use YFEvents\Infrastructure\Services\EmailService;
use YFEvents\Infrastructure\Container\ContainerInterface;
use YFEvents\Infrastructure\Config\ConfigInterface;
use Exception;

/**
 * Admin controller for email management
 */
class AdminEmailController extends BaseController
{
    private EmailEventProcessor $emailEventProcessor;
    private EmailService $emailService;

    public function __construct(ContainerInterface $container, ConfigInterface $config)
    {
        parent::__construct($container, $config);
        $this->emailEventProcessor = $container->resolve(EmailEventProcessor::class);
        $this->emailService = $container->resolve(EmailService::class);
    }

    /**
     * Show email events management page
     */
    public function showEmailEvents(): void
    {
        if (!$this->requireAdmin()) {
            return;
        }

        $basePath = dirname($_SERVER['SCRIPT_NAME']);
        if ($basePath === '/') {
            $basePath = '';
        }

        header('Content-Type: text/html; charset=utf-8');
        echo $this->renderEmailEventsPage($basePath);
    }

    /**
     * Show email configuration page
     */
    public function showEmailConfig(): void
    {
        if (!$this->requireAdmin()) {
            return;
        }

        $basePath = dirname($_SERVER['SCRIPT_NAME']);
        if ($basePath === '/') {
            $basePath = '';
        }

        header('Content-Type: text/html; charset=utf-8');
        echo $this->renderEmailConfigPage($basePath);
    }

    /**
     * Process email events from IMAP
     */
    public function processEmails(): void
    {
        if (!$this->requireAdmin()) {
            return;
        }

        try {
            $result = $this->emailEventProcessor->processInbox();

            $this->successResponse([
                'message' => 'Email processing completed',
                'processed' => $result['processed'],
                'created_events' => $result['created_events'],
                'errors' => $result['errors']
            ]);

        } catch (Exception $e) {
            $this->errorResponse('Failed to process emails: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get email processing history
     */
    public function getEmailHistory(): void
    {
        if (!$this->requireAdmin()) {
            return;
        }

        try {
            $input = $this->getInput();
            $limit = min(100, max(10, (int)($input['limit'] ?? 50)));
            $offset = max(0, (int)($input['offset'] ?? 0));

            $history = $this->emailEventProcessor->getProcessingHistory($limit, $offset);

            $this->successResponse([
                'history' => $history,
                'limit' => $limit,
                'offset' => $offset
            ]);

        } catch (Exception $e) {
            $this->errorResponse('Failed to load email history: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get email configuration
     */
    public function getEmailConfig(): void
    {
        if (!$this->requireAdmin()) {
            return;
        }

        try {
            $config = $this->emailEventProcessor->getConfiguration();

            // Remove sensitive data
            if (isset($config['imap_password'])) {
                $config['imap_password'] = !empty($config['imap_password']) ? '********' : '';
            }

            $this->successResponse([
                'config' => $config
            ]);

        } catch (Exception $e) {
            $this->errorResponse('Failed to load email config: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Update email configuration
     */
    public function updateEmailConfig(): void
    {
        if (!$this->requireAdmin()) {
            return;
        }

        try {
            $input = $this->getInput();

            // Don't update password if it's masked
            if (isset($input['imap_password']) && $input['imap_password'] === '********') {
                unset($input['imap_password']);
            }

            $result = $this->emailEventProcessor->updateConfiguration($input);

            if ($result['success']) {
                $this->successResponse([
                    'message' => 'Email configuration updated successfully'
                ]);
            } else {
                $this->errorResponse($result['error'] ?? 'Failed to update configuration');
            }

        } catch (Exception $e) {
            $this->errorResponse('Failed to update email config: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Test email connection
     */
    public function testConnection(): void
    {
        if (!$this->requireAdmin()) {
            return;
        }

        try {
            $result = $this->emailEventProcessor->testConnection();

            if ($result['success']) {
                $this->successResponse([
                    'message' => 'Connection successful',
                    'mailbox_info' => $result['mailbox_info']
                ]);
            } else {
                $this->errorResponse($result['error'] ?? 'Connection failed');
            }

        } catch (Exception $e) {
            $this->errorResponse('Failed to test connection: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Upload and process email file
     */
    public function uploadEmail(): void
    {
        if (!$this->requireAdmin()) {
            return;
        }

        try {
            if (!isset($_FILES['email_file'])) {
                $this->errorResponse('No file uploaded');
                return;
            }

            $file = $_FILES['email_file'];
            
            if ($file['error'] !== UPLOAD_ERR_OK) {
                $this->errorResponse('Upload failed: ' . $this->getUploadError($file['error']));
                return;
            }

            $content = file_get_contents($file['tmp_name']);
            $result = $this->emailEventProcessor->processEmailContent($content);

            if ($result['success']) {
                $this->successResponse([
                    'message' => 'Email processed successfully',
                    'event' => $result['event']
                ]);
            } else {
                $this->errorResponse($result['error'] ?? 'Failed to process email');
            }

        } catch (Exception $e) {
            $this->errorResponse('Failed to process uploaded email: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get upload error message
     */
    private function getUploadError(int $code): string
    {
        return match ($code) {
            UPLOAD_ERR_INI_SIZE => 'File exceeds upload_max_filesize',
            UPLOAD_ERR_FORM_SIZE => 'File exceeds MAX_FILE_SIZE',
            UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
            UPLOAD_ERR_NO_FILE => 'No file was uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
            UPLOAD_ERR_EXTENSION => 'File upload stopped by extension',
            default => 'Unknown upload error'
        };
    }

    /**
     * Render email events page
     */
    private function renderEmailEventsPage(string $basePath): string
    {
        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Events - YFEvents Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .admin-header {
            background: linear-gradient(135deg, #dc3545 0%, #6f42c1 100%);
            color: white;
            padding: 2rem 0;
            margin-bottom: 2rem;
        }
        .email-card {
            background: white;
            border-radius: 8px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .status-processed {
            color: #198754;
        }
        .status-error {
            color: #dc3545;
        }
        .status-duplicate {
            color: #ffc107;
        }
    </style>
</head>
<body>
    <div class="admin-header">
        <div class="container">
            <div class="d-flex justify-content-between align-items-center">
                <h1 class="h3 mb-0">Email Event Processing</h1>
                <a href="{$basePath}/admin/dashboard" class="btn btn-light">
                    <i class="bi bi-arrow-left"></i> Back to Dashboard
                </a>
            </div>
        </div>
    </div>

    <div class="container">
        <!-- Controls -->
        <div class="card mb-4">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-md-6">
                        <h5 class="mb-0">Process Facebook Event Emails</h5>
                        <p class="text-muted mb-0">Extract events from Facebook notification emails</p>
                    </div>
                    <div class="col-md-6 text-end">
                        <button class="btn btn-primary" onclick="processEmails()">
                            <i class="bi bi-envelope-open"></i> Process Inbox
                        </button>
                        <a href="{$basePath}/admin/email-config" class="btn btn-outline-secondary">
                            <i class="bi bi-gear"></i> Configure
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Processing Status -->
        <div id="processing-status" class="alert alert-info" style="display: none;">
            <i class="bi bi-hourglass-split"></i> Processing emails...
        </div>

        <!-- Results -->
        <div id="processing-results" style="display: none;">
            <div class="alert alert-success">
                <h6 class="alert-heading">Processing Complete</h6>
                <div id="results-summary"></div>
            </div>
        </div>

        <!-- Email History -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Processing History</h5>
            </div>
            <div class="card-body">
                <div id="email-history">
                    <div class="text-center p-3">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const basePath = '{$basePath}';

        document.addEventListener('DOMContentLoaded', () => {
            loadEmailHistory();
        });

        async function processEmails() {
            const statusDiv = document.getElementById('processing-status');
            const resultsDiv = document.getElementById('processing-results');
            
            statusDiv.style.display = 'block';
            resultsDiv.style.display = 'none';

            try {
                const response = await fetch(`\${basePath}/admin/email/process`, {
                    method: 'POST'
                });

                const data = await response.json();

                statusDiv.style.display = 'none';

                if (data.success) {
                    const summary = document.getElementById('results-summary');
                    summary.innerHTML = `
                        <p class="mb-1">Processed: \${data.processed} emails</p>
                        <p class="mb-1">Created Events: \${data.created_events}</p>
                        <p class="mb-0">Errors: \${data.errors.length}</p>
                    `;
                    resultsDiv.style.display = 'block';
                    
                    // Reload history
                    loadEmailHistory();
                } else {
                    alert('Error: ' + (data.error || 'Failed to process emails'));
                }
            } catch (error) {
                statusDiv.style.display = 'none';
                alert('Network error: ' + error.message);
            }
        }

        async function loadEmailHistory() {
            try {
                const response = await fetch(`\${basePath}/admin/email/history`);
                const data = await response.json();

                if (data.success) {
                    renderEmailHistory(data.history);
                } else {
                    document.getElementById('email-history').innerHTML = 
                        '<div class="alert alert-danger">Failed to load history</div>';
                }
            } catch (error) {
                document.getElementById('email-history').innerHTML = 
                    '<div class="alert alert-danger">Network error loading history</div>';
            }
        }

        function renderEmailHistory(history) {
            const container = document.getElementById('email-history');
            
            if (history.length === 0) {
                container.innerHTML = '<p class="text-muted">No processing history yet</p>';
                return;
            }

            container.innerHTML = history.map(item => `
                <div class="email-card">
                    <div class="row align-items-center">
                        <div class="col-md-8">
                            <h6 class="mb-1">\${escapeHtml(item.subject)}</h6>
                            <p class="mb-0 text-muted">From: \${escapeHtml(item.from_email)}</p>
                            <small class="text-muted">Processed: \${new Date(item.processed_at).toLocaleString()}</small>
                        </div>
                        <div class="col-md-4 text-end">
                            <span class="badge bg-\${getStatusColor(item.status)} status-\${item.status}">
                                \${item.status}
                            </span>
                            \${item.event_id ? `<br><small>Event #\${item.event_id}</small>` : ''}
                        </div>
                    </div>
                    \${item.error ? `<div class="mt-2 text-danger"><small>Error: \${escapeHtml(item.error)}</small></div>` : ''}
                </div>
            `).join('');
        }

        function getStatusColor(status) {
            return {
                'processed': 'success',
                'error': 'danger',
                'duplicate': 'warning',
                'ignored': 'secondary'
            }[status] || 'secondary';
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    </script>
</body>
</html>
HTML;
    }

    /**
     * Render email configuration page
     */
    private function renderEmailConfigPage(string $basePath): string
    {
        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Configuration - YFEvents Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .admin-header {
            background: linear-gradient(135deg, #dc3545 0%, #6f42c1 100%);
            color: white;
            padding: 2rem 0;
            margin-bottom: 2rem;
        }
        .config-section {
            background: white;
            border-radius: 8px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
    </style>
</head>
<body>
    <div class="admin-header">
        <div class="container">
            <div class="d-flex justify-content-between align-items-center">
                <h1 class="h3 mb-0">Email Configuration</h1>
                <a href="{$basePath}/admin/email-events" class="btn btn-light">
                    <i class="bi bi-arrow-left"></i> Back to Email Events
                </a>
            </div>
        </div>
    </div>

    <div class="container">
        <form id="email-config-form">
            <!-- IMAP Configuration -->
            <div class="config-section">
                <h4 class="mb-4">IMAP Configuration</h4>
                
                <div class="row">
                    <div class="col-md-8">
                        <div class="mb-3">
                            <label for="imap_server" class="form-label">IMAP Server</label>
                            <input type="text" class="form-control" id="imap_server" name="imap_server" required>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="mb-3">
                            <label for="imap_port" class="form-label">Port</label>
                            <input type="number" class="form-control" id="imap_port" name="imap_port" value="993" required>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="imap_username" class="form-label">Username</label>
                            <input type="text" class="form-control" id="imap_username" name="imap_username" required>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="imap_password" class="form-label">Password</label>
                            <input type="password" class="form-control" id="imap_password" name="imap_password">
                            <small class="text-muted">Leave blank to keep existing password</small>
                        </div>
                    </div>
                </div>

                <div class="mb-3">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="imap_ssl" name="imap_ssl" checked>
                        <label class="form-check-label" for="imap_ssl">
                            Use SSL/TLS
                        </label>
                    </div>
                </div>

                <div class="mb-3">
                    <label for="imap_folder" class="form-label">Folder to Monitor</label>
                    <input type="text" class="form-control" id="imap_folder" name="imap_folder" value="INBOX">
                </div>
            </div>

            <!-- Processing Options -->
            <div class="config-section">
                <h4 class="mb-4">Processing Options</h4>
                
                <div class="mb-3">
                    <label for="sender_filter" class="form-label">Sender Email Filter</label>
                    <input type="text" class="form-control" id="sender_filter" name="sender_filter" 
                           placeholder="e.g., notification@facebookmail.com">
                    <small class="text-muted">Only process emails from this sender (leave blank for all)</small>
                </div>

                <div class="mb-3">
                    <label for="subject_filter" class="form-label">Subject Filter</label>
                    <input type="text" class="form-control" id="subject_filter" name="subject_filter"
                           placeholder="e.g., invited you to">
                    <small class="text-muted">Only process emails containing this text in subject</small>
                </div>

                <div class="mb-3">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="auto_approve" name="auto_approve">
                        <label class="form-check-label" for="auto_approve">
                            Auto-approve extracted events
                        </label>
                    </div>
                </div>

                <div class="mb-3">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="mark_as_read" name="mark_as_read" checked>
                        <label class="form-check-label" for="mark_as_read">
                            Mark processed emails as read
                        </label>
                    </div>
                </div>
            </div>

            <div class="text-end">
                <button type="button" class="btn btn-secondary" onclick="testConnection()">
                    <i class="bi bi-plug"></i> Test Connection
                </button>
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-save"></i> Save Configuration
                </button>
            </div>
        </form>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const basePath = '{$basePath}';

        document.addEventListener('DOMContentLoaded', () => {
            loadConfiguration();
            document.getElementById('email-config-form').addEventListener('submit', saveConfiguration);
        });

        async function loadConfiguration() {
            try {
                const response = await fetch(`\${basePath}/admin/email/config`);
                const data = await response.json();

                if (data.success) {
                    const form = document.getElementById('email-config-form');
                    Object.keys(data.config).forEach(key => {
                        const field = form.elements[key];
                        if (field) {
                            if (field.type === 'checkbox') {
                                field.checked = !!data.config[key];
                            } else {
                                field.value = data.config[key] || '';
                            }
                        }
                    });
                }
            } catch (error) {
                console.error('Failed to load configuration:', error);
            }
        }

        async function saveConfiguration(e) {
            e.preventDefault();
            
            const formData = new FormData(e.target);
            const config = {};
            
            for (const [key, value] of formData.entries()) {
                config[key] = value;
            }
            
            // Handle checkboxes
            ['imap_ssl', 'auto_approve', 'mark_as_read'].forEach(key => {
                config[key] = e.target.elements[key].checked;
            });

            try {
                const response = await fetch(`\${basePath}/admin/email/config`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(config)
                });

                const data = await response.json();

                if (data.success) {
                    alert('Configuration saved successfully');
                } else {
                    alert('Error: ' + (data.error || 'Failed to save configuration'));
                }
            } catch (error) {
                alert('Network error: ' + error.message);
            }
        }

        async function testConnection() {
            try {
                const response = await fetch(`\${basePath}/admin/email/test-connection`, {
                    method: 'POST'
                });

                const data = await response.json();

                if (data.success) {
                    alert('Connection successful!\\n\\nMailbox: ' + 
                          (data.mailbox_info ? JSON.stringify(data.mailbox_info, null, 2) : 'Connected'));
                } else {
                    alert('Connection failed: ' + (data.error || 'Unknown error'));
                }
            } catch (error) {
                alert('Network error: ' + error.message);
            }
        }
    </script>
</body>
</html>
HTML;
    }
}