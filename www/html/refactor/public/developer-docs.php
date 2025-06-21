<?php
// Developer Documentation Page
declare(strict_types=1);

// Read the markdown file
$mdFile = '/home/robug/YFEvents/docs/DEVELOPER_SPEC.md';
$markdown = file_exists($mdFile) ? file_get_contents($mdFile) : '# Developer Documentation Not Found - File: ' . $mdFile;

// Simple markdown to HTML conversion
function convertMarkdownToHtml($markdown) {
    // Convert headers
    $html = preg_replace('/^### (.*?)$/m', '<h3>$1</h3>', $markdown);
    $html = preg_replace('/^## (.*?)$/m', '<h2>$1</h2>', $html);
    $html = preg_replace('/^# (.*?)$/m', '<h1>$1</h1>', $html);
    
    // Convert code blocks
    $html = preg_replace('/```(\w+)\n(.*?)\n```/s', '<pre><code class="language-$1">$2</code></pre>', $html);
    $html = preg_replace('/```\n(.*?)\n```/s', '<pre><code>$1</code></pre>', $html);
    
    // Convert inline code
    $html = preg_replace('/`([^`]+)`/', '<code>$1</code>', $html);
    
    // Convert bold
    $html = preg_replace('/\*\*(.*?)\*\*/', '<strong>$1</strong>', $html);
    
    // Convert italic
    $html = preg_replace('/\*(.*?)\*/', '<em>$1</em>', $html);
    
    // Convert links
    $html = preg_replace('/\[([^\]]+)\]\(([^)]+)\)/', '<a href="$2">$1</a>', $html);
    
    // Convert lists
    $html = preg_replace('/^\- (.*)$/m', '<li>$1</li>', $html);
    $html = preg_replace('/(<li>.*<\/li>)\n(?!<li>)/s', '<ul>$1</ul>', $html);
    
    // Convert numbered lists
    $html = preg_replace('/^\d+\. (.*)$/m', '<li>$1</li>', $html);
    
    // Convert paragraphs
    $html = preg_replace('/\n\n/', '</p><p>', $html);
    $html = '<p>' . $html . '</p>';
    
    // Clean up
    $html = preg_replace('/<p><h/', '<h', $html);
    $html = preg_replace('/<\/h(\d)><\/p>/', '</h$1>', $html);
    $html = preg_replace('/<p><pre>/', '<pre>', $html);
    $html = preg_replace('/<\/pre><\/p>/', '</pre>', $html);
    $html = preg_replace('/<p><ul>/', '<ul>', $html);
    $html = preg_replace('/<\/ul><\/p>/', '</ul>', $html);
    
    return $html;
}

$htmlContent = convertMarkdownToHtml($markdown);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>YFEvents Framework - Developer Documentation</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/themes/prism-tomorrow.min.css">
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            line-height: 1.6;
            color: #333;
            background-color: #f8f9fa;
        }
        
        .documentation-container {
            max-width: 900px;
            margin: 0 auto;
            padding: 2rem;
            background-color: white;
            box-shadow: 0 0 20px rgba(0,0,0,0.05);
            border-radius: 8px;
            margin-top: 2rem;
            margin-bottom: 2rem;
        }
        
        h1 {
            color: #2c3e50;
            border-bottom: 3px solid #3498db;
            padding-bottom: 1rem;
            margin-bottom: 2rem;
        }
        
        h2 {
            color: #34495e;
            margin-top: 2.5rem;
            margin-bottom: 1.5rem;
            border-bottom: 1px solid #eee;
            padding-bottom: 0.5rem;
        }
        
        h3 {
            color: #7f8c8d;
            margin-top: 2rem;
            margin-bottom: 1rem;
        }
        
        code {
            background-color: #f4f4f4;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: 'Consolas', 'Monaco', 'Courier New', monospace;
            font-size: 0.9em;
            color: #e74c3c;
        }
        
        pre {
            background-color: #2d2d2d;
            border-radius: 5px;
            padding: 1rem;
            overflow-x: auto;
            margin: 1rem 0;
        }
        
        pre code {
            background-color: transparent;
            color: #f8f8f2;
            padding: 0;
            font-size: 0.875rem;
            line-height: 1.5;
        }
        
        ul, ol {
            margin-bottom: 1rem;
        }
        
        li {
            margin-bottom: 0.5rem;
        }
        
        a {
            color: #3498db;
            text-decoration: none;
        }
        
        a:hover {
            color: #2980b9;
            text-decoration: underline;
        }
        
        strong {
            color: #2c3e50;
        }
        
        .table-of-contents {
            background-color: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 5px;
            padding: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .table-of-contents h2 {
            margin-top: 0;
            margin-bottom: 1rem;
            font-size: 1.25rem;
            border: none;
        }
        
        .table-of-contents ul {
            list-style: none;
            padding-left: 0;
        }
        
        .table-of-contents li {
            margin-bottom: 0.5rem;
        }
        
        .table-of-contents a {
            color: #495057;
        }
        
        .back-to-top {
            position: fixed;
            bottom: 20px;
            right: 20px;
            background-color: #3498db;
            color: white;
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            box-shadow: 0 2px 10px rgba(0,0,0,0.2);
            transition: background-color 0.3s;
        }
        
        .back-to-top:hover {
            background-color: #2980b9;
            color: white;
        }
        
        .download-btn {
            display: inline-block;
            margin: 1rem 0;
            padding: 0.75rem 1.5rem;
            background-color: #3498db;
            color: white;
            border-radius: 5px;
            text-decoration: none;
        }
        
        .download-btn:hover {
            background-color: #2980b9;
            color: white;
        }
        
        @media (max-width: 768px) {
            .documentation-container {
                padding: 1rem;
                margin-top: 1rem;
                margin-bottom: 1rem;
            }
            
            pre {
                padding: 0.5rem;
                font-size: 0.8rem;
            }
        }
    </style>
</head>
<body>
    <div class="documentation-container">
        <div class="text-end mb-3">
            <a href="../docs/DEVELOPER_SPEC.md" class="download-btn" download>
                Download Markdown
            </a>
        </div>
        
        <?php echo $htmlContent; ?>
        
        <hr class="mt-5">
        <div class="text-center text-muted">
            <p>YFEvents Framework Documentation</p>
            <p>
                <a href="/refactor/">Back to Home</a> |
                <a href="/refactor/admin/">Admin Panel</a> |
                <a href="https://github.com/yourusername/yfevents">GitHub</a>
            </p>
        </div>
    </div>
    
    <a href="#" class="back-to-top">↑</a>
    
    <script src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/prism.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/components/prism-php.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/components/prism-javascript.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/components/prism-sql.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/components/prism-bash.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/components/prism-json.min.js"></script>
    
    <script>
        // Back to top functionality
        const backToTop = document.querySelector('.back-to-top');
        
        window.addEventListener('scroll', () => {
            if (window.pageYOffset > 100) {
                backToTop.style.display = 'flex';
            } else {
                backToTop.style.display = 'none';
            }
        });
        
        backToTop.addEventListener('click', (e) => {
            e.preventDefault();
            window.scrollTo({ top: 0, behavior: 'smooth' });
        });
        
        // Add IDs to headers for anchor links
        document.querySelectorAll('h1, h2, h3').forEach(header => {
            const id = header.textContent.toLowerCase()
                .replace(/[^\w\s-]/g, '')
                .replace(/\s+/g, '-');
            header.id = id;
        });
    </script>
</body>
</html>