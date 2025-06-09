<?php
session_start();

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: /admin/login.php');
    exit;
}

require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../../config/database.php';

use YakimaFinds\Models\CalendarSourceModel;

// Get sources with statistics
$sourceModel = new CalendarSourceModel($db);
$sources = $sourceModel->getSources();

// Get scraping statistics
$stmt = $db->query("
    SELECT 
        COUNT(*) as total_events,
        COUNT(CASE WHEN status = 'approved' THEN 1 END) as approved_events,
        COUNT(DISTINCT source_id) as active_sources,
        MAX(created_at) as last_event_added
    FROM events 
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
");
$stats = $stmt->fetch(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Event Scraper Information - YFEvents</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            margin: 0;
            padding: 20px;
            background: #f8f9fa;
            line-height: 1.6;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        h1 {
            color: #2c3e50;
            border-bottom: 3px solid #3498db;
            padding-bottom: 15px;
            margin-bottom: 30px;
        }
        h2 {
            color: #34495e;
            margin-top: 40px;
            margin-bottom: 20px;
        }
        h3 {
            color: #2980b9;
            margin-top: 30px;
        }
        .back-link {
            display: inline-block;
            margin-bottom: 20px;
            color: #3498db;
            text-decoration: none;
            font-weight: 500;
        }
        .back-link:hover {
            text-decoration: underline;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin: 30px 0;
        }
        .stat-card {
            background: linear-gradient(135deg, #3498db, #2980b9);
            color: white;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
        }
        .stat-number {
            font-size: 2.5em;
            font-weight: bold;
            margin-bottom: 5px;
        }
        .stat-label {
            font-size: 0.9em;
            opacity: 0.9;
        }
        .process-section {
            background: #f8f9fa;
            padding: 25px;
            border-radius: 8px;
            margin: 20px 0;
            border-left: 4px solid #3498db;
        }
        .source-table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .source-table th,
        .source-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        .source-table th {
            background: #34495e;
            color: white;
            font-weight: 600;
        }
        .source-table tr:hover {
            background: #f5f5f5;
        }
        .source-type {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: bold;
            text-transform: uppercase;
        }
        .type-html { background: #e74c3c; color: white; }
        .type-ical { background: #27ae60; color: white; }
        .type-json { background: #f39c12; color: white; }
        .type-yakima_valley { background: #9b59b6; color: white; }
        .status-active { color: #27ae60; font-weight: bold; }
        .status-inactive { color: #e74c3c; }
        .url-cell {
            max-width: 300px;
            word-break: break-all;
            font-family: monospace;
            font-size: 12px;
        }
        .optimization-info {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 6px;
            padding: 15px;
            margin: 20px 0;
        }
        .feature-list {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin: 20px 0;
        }
        .feature-card {
            background: white;
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 20px;
        }
        .feature-icon {
            font-size: 2em;
            color: #3498db;
            margin-bottom: 10px;
        }
        code {
            background: #f4f4f4;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: 'Courier New', monospace;
        }
        .tech-stack {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin: 15px 0;
        }
        .tech-tag {
            background: #ecf0f1;
            color: #2c3e50;
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 12px;
            font-weight: 500;
        }
    </style>
</head>
<body>
    <div class="container">
        <a href="/admin/calendar/" class="back-link">
            <i class="fas fa-arrow-left"></i> Back to Advanced Admin
        </a>
        
        <h1><i class="fas fa-info-circle"></i> Event Scraper Information</h1>
        
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?= number_format($stats['total_events']) ?></div>
                <div class="stat-label">Events (Last 30 Days)</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= number_format($stats['approved_events']) ?></div>
                <div class="stat-label">Approved Events</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= count($sources) ?></div>
                <div class="stat-label">Total Sources</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= $stats['active_sources'] ?></div>
                <div class="stat-label">Active Sources</div>
            </div>
        </div>

        <h2><i class="fas fa-cogs"></i> How Our Event Scraping Works</h2>
        
        <div class="process-section">
            <h3>1. Multi-Format Support</h3>
            <p>Our scraping system handles multiple event data formats automatically:</p>
            <div class="tech-stack">
                <span class="tech-tag">HTML Parsing</span>
                <span class="tech-tag">iCal Feeds</span>
                <span class="tech-tag">JSON APIs</span>
                <span class="tech-tag">Custom Scrapers</span>
            </div>
        </div>

        <div class="process-section">
            <h3>2. Intelligent Time Parsing</h3>
            <p>When events don't specify exact times, our system applies intelligent defaults based on event types:</p>
            <ul>
                <li><strong>Farmers Markets</strong> → 9:00 AM</li>
                <li><strong>Happy Hour Events</strong> → 5:00 PM</li>
                <li><strong>Trivia & Bingo</strong> → 7:00 PM</li>
                <li><strong>Live Music & Shows</strong> → 8:00 PM</li>
                <li><strong>Wine Tastings</strong> → 12:00 PM</li>
            </ul>
        </div>

        <div class="process-section">
            <h3>3. Advanced HTML Processing</h3>
            <p>For HTML sources, we use multiple detection strategies:</p>
            <ul>
                <li><strong>Schema.org Markup</strong> - Structured event data</li>
                <li><strong>CSS Class Detection</strong> - Event-specific styling classes</li>
                <li><strong>XPath Selectors</strong> - Precise DOM element targeting</li>
                <li><strong>Pattern Recognition</strong> - Automatic content structure analysis</li>
            </ul>
        </div>

        <div class="optimization-info">
            <h3><i class="fas fa-brain"></i> Intelligent Optimization</h3>
            <p>Our intelligent scraper automatically optimizes new sources by:</p>
            <ul>
                <li>Testing multiple scraping strategies and selecting the best performing one</li>
                <li>Converting CSS selectors to XPath for improved reliability</li>
                <li>Applying machine learning patterns from successful scrapers</li>
                <li>Generating optimized configurations based on content analysis</li>
            </ul>
        </div>

        <h2><i class="fas fa-database"></i> Current Event Sources</h2>
        
        <table class="source-table">
            <thead>
                <tr>
                    <th>Source Name</th>
                    <th>Type</th>
                    <th>URL</th>
                    <th>Status</th>
                    <th>Events</th>
                    <th>Last Scraped</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($sources as $source): ?>
                <tr>
                    <td><strong><?= htmlspecialchars($source['name']) ?></strong></td>
                    <td>
                        <span class="source-type type-<?= $source['scrape_type'] ?>">
                            <?= strtoupper($source['scrape_type']) ?>
                        </span>
                    </td>
                    <td class="url-cell"><?= htmlspecialchars($source['url']) ?></td>
                    <td class="status-<?= $source['active'] ? 'active' : 'inactive' ?>">
                        <?= $source['active'] ? 'Active' : 'Inactive' ?>
                    </td>
                    <td><?= number_format($source['event_count'] ?? 0) ?></td>
                    <td>
                        <?php if ($source['last_successful_scrape']): ?>
                            <?= date('M j, Y', strtotime($source['last_successful_scrape'])) ?>
                        <?php else: ?>
                            <em>Never</em>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <h2><i class="fas fa-tools"></i> Technical Features</h2>
        
        <div class="feature-list">
            <div class="feature-card">
                <div class="feature-icon"><i class="fas fa-shield-alt"></i></div>
                <h3>Error Handling</h3>
                <p>Robust error handling with automatic retry logic and fallback strategies for unreliable sources.</p>
            </div>
            
            <div class="feature-card">
                <div class="feature-icon"><i class="fas fa-map-marker-alt"></i></div>
                <h3>Geocoding</h3>
                <p>Automatic location geocoding using Google Maps API for precise event mapping.</p>
            </div>
            
            <div class="feature-card">
                <div class="feature-icon"><i class="fas fa-clock"></i></div>
                <h3>Scheduling</h3>
                <p>Automated daily scraping via cron jobs with configurable frequency per source.</p>
            </div>
            
            <div class="feature-card">
                <div class="feature-icon"><i class="fas fa-filter"></i></div>
                <h3>Duplicate Detection</h3>
                <p>Intelligent duplicate filtering based on title, date, and location similarity.</p>
            </div>
            
            <div class="feature-card">
                <div class="feature-icon"><i class="fas fa-chart-line"></i></div>
                <h3>Performance Monitoring</h3>
                <p>Detailed logging and statistics tracking for scraping success rates and optimization.</p>
            </div>
            
            <div class="feature-card">
                <div class="feature-icon"><i class="fas fa-code"></i></div>
                <h3>CSS to XPath</h3>
                <p>Automatic conversion of CSS selectors to XPath for improved reliability and performance.</p>
            </div>
        </div>

        <h2><i class="fas fa-wrench"></i> Optimization Process</h2>
        
        <div class="process-section">
            <h3>Automatic Optimization Steps</h3>
            <ol>
                <li><strong>Content Analysis</strong> - Fetch and analyze the source page structure</li>
                <li><strong>Strategy Testing</strong> - Test multiple scraping approaches (Schema.org, classes, patterns)</li>
                <li><strong>Validation</strong> - Extract sample events and validate data quality</li>
                <li><strong>Scoring</strong> - Rate each strategy based on event count and data completeness</li>
                <li><strong>Configuration Generation</strong> - Create optimized scraper configuration</li>
                <li><strong>Implementation</strong> - Apply the best configuration and test results</li>
            </ol>
        </div>

        <div class="process-section">
            <h3>Success Metrics</h3>
            <p>We measure scraper effectiveness using:</p>
            <ul>
                <li><strong>Event Discovery Rate</strong> - Number of events found per scrape</li>
                <li><strong>Data Completeness</strong> - Percentage of events with title, date, and location</li>
                <li><strong>Time Accuracy</strong> - Events with specific times vs. default times</li>
                <li><strong>Error Rate</strong> - Frequency of scraping failures or issues</li>
                <li><strong>Duplicate Rate</strong> - Percentage of duplicate events filtered out</li>
            </ul>
        </div>

        <div class="optimization-info">
            <h3><i class="fas fa-lightbulb"></i> Best Practices</h3>
            <p>For optimal scraping results:</p>
            <ul>
                <li>Use sources with structured markup (Schema.org, JSON-LD) when possible</li>
                <li>Prefer iCal feeds for maximum reliability and data completeness</li>
                <li>Monitor source websites for structure changes that may affect scraping</li>
                <li>Use the intelligent optimization tool for new or problematic sources</li>
                <li>Regular testing ensures continued reliability of event discovery</li>
            </ul>
        </div>
    </div>
</body>
</html>