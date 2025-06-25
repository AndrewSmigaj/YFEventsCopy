<?php

declare(strict_types=1);

namespace YFEvents\Presentation\Http\Controllers;

use YFEvents\Infrastructure\Container\ContainerInterface;
use YFEvents\Infrastructure\Config\ConfigInterface;

/**
 * API Documentation Controller
 */
class ApiDocController extends BaseController
{
    public function __construct(ContainerInterface $container, ConfigInterface $config)
    {
        parent::__construct($container, $config);
    }

    /**
     * Show API documentation
     */
    public function showDocumentation(): void
    {
        $basePath = dirname($_SERVER['SCRIPT_NAME']);
        if ($basePath === '/') {
            $basePath = '';
        }

        header('Content-Type: text/html; charset=utf-8');
        echo $this->renderDocumentationPage($basePath);
    }

    private function renderDocumentationPage(string $basePath): string
    {
        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>YFEvents API Documentation</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f5f5f5;
            color: #333;
            line-height: 1.6;
        }
        
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px 0;
            text-align: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .header h1 {
            font-size: 2.5rem;
            margin-bottom: 10px;
        }
        
        .header p {
            font-size: 1.2rem;
            opacity: 0.9;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 40px 20px;
        }
        
        .section {
            background: white;
            border-radius: 10px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        .section h2 {
            color: #667eea;
            margin-bottom: 20px;
            font-size: 1.8rem;
            border-bottom: 2px solid #eee;
            padding-bottom: 10px;
        }
        
        .section h3 {
            color: #764ba2;
            margin: 20px 0 10px 0;
            font-size: 1.3rem;
        }
        
        .endpoint {
            background: #f8f9fa;
            border-left: 4px solid #667eea;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
        }
        
        .endpoint-header {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 10px;
        }
        
        .method {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 4px;
            font-weight: bold;
            font-size: 0.9rem;
            color: white;
        }
        
        .method.get { background: #61affe; }
        .method.post { background: #49cc90; }
        .method.put { background: #fca130; }
        .method.delete { background: #f93e3e; }
        
        .endpoint-path {
            font-family: 'Courier New', monospace;
            font-size: 1.1rem;
            color: #333;
        }
        
        .endpoint-description {
            color: #666;
            margin-bottom: 10px;
        }
        
        .params {
            margin-top: 15px;
        }
        
        .params h4 {
            font-size: 1rem;
            margin-bottom: 5px;
            color: #555;
        }
        
        .param-list {
            list-style: none;
            padding-left: 0;
        }
        
        .param-list li {
            padding: 5px 0;
            border-bottom: 1px solid #eee;
        }
        
        .param-name {
            font-family: 'Courier New', monospace;
            color: #764ba2;
            font-weight: bold;
        }
        
        .param-type {
            color: #666;
            font-size: 0.9rem;
            margin-left: 10px;
        }
        
        .param-description {
            color: #888;
            font-size: 0.9rem;
            margin-left: 20px;
        }
        
        .example {
            background: #2d2d2d;
            color: #f8f8f2;
            padding: 15px;
            border-radius: 5px;
            overflow-x: auto;
            margin-top: 10px;
        }
        
        .example code {
            font-family: 'Courier New', monospace;
            font-size: 0.9rem;
        }
        
        .badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 3px;
            font-size: 0.8rem;
            font-weight: bold;
            margin-left: 10px;
        }
        
        .badge.auth { background: #ff6b6b; color: white; }
        .badge.public { background: #51cf66; color: white; }
        
        .nav-link {
            display: inline-block;
            margin: 10px 20px 10px 0;
            color: #667eea;
            text-decoration: none;
            font-weight: 500;
        }
        
        .nav-link:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>YFEvents API Documentation</h1>
        <p>RESTful API for events, shops, and local business directory</p>
    </div>
    
    <div class="container">
        <div class="section">
            <a href="{$basePath}/" class="nav-link">← Back to Home</a>
            <a href="{$basePath}/api/health" class="nav-link">Test API Health</a>
        </div>

        <div class="section">
            <h2>Overview</h2>
            <p>The YFEvents API provides programmatic access to event and business data in the Yakima Valley. All API endpoints return JSON responses and use standard HTTP response codes.</p>
            
            <h3>Base URL</h3>
            <div class="example">
                <code>{$basePath}/api</code>
            </div>
            
            <h3>Response Format</h3>
            <p>All successful responses follow this format:</p>
            <div class="example">
                <code>{
    "success": true,
    "message": "Success message",
    "data": {
        // Response data here
    }
}</code>
            </div>
        </div>

        <div class="section">
            <h2>Events API</h2>
            <p>Access event information including upcoming events, featured events, and event details.</p>
            
            <div class="endpoint">
                <div class="endpoint-header">
                    <span class="method get">GET</span>
                    <span class="endpoint-path">/api/events</span>
                    <span class="badge public">Public</span>
                </div>
                <div class="endpoint-description">Search and list events with filters</div>
                <div class="params">
                    <h4>Query Parameters:</h4>
                    <ul class="param-list">
                        <li>
                            <span class="param-name">search</span>
                            <span class="param-type">string</span>
                            <div class="param-description">Search events by title or description</div>
                        </li>
                        <li>
                            <span class="param-name">category</span>
                            <span class="param-type">string</span>
                            <div class="param-description">Filter by category (music, arts, sports, etc.)</div>
                        </li>
                        <li>
                            <span class="param-name">date_from</span>
                            <span class="param-type">date</span>
                            <div class="param-description">Filter events starting from this date (YYYY-MM-DD)</div>
                        </li>
                        <li>
                            <span class="param-name">date_to</span>
                            <span class="param-type">date</span>
                            <div class="param-description">Filter events up to this date (YYYY-MM-DD)</div>
                        </li>
                        <li>
                            <span class="param-name">featured</span>
                            <span class="param-type">boolean</span>
                            <div class="param-description">Show only featured events (1 or 0)</div>
                        </li>
                        <li>
                            <span class="param-name">limit</span>
                            <span class="param-type">integer</span>
                            <div class="param-description">Number of results per page (default: 20)</div>
                        </li>
                        <li>
                            <span class="param-name">offset</span>
                            <span class="param-type">integer</span>
                            <div class="param-description">Pagination offset (default: 0)</div>
                        </li>
                    </ul>
                </div>
                <div class="example">
                    <code>GET {$basePath}/api/events?search=concert&category=music&limit=10</code>
                </div>
            </div>
            
            <div class="endpoint">
                <div class="endpoint-header">
                    <span class="method get">GET</span>
                    <span class="endpoint-path">/api/events/{id}</span>
                    <span class="badge public">Public</span>
                </div>
                <div class="endpoint-description">Get details for a specific event</div>
            </div>
            
            <div class="endpoint">
                <div class="endpoint-header">
                    <span class="method get">GET</span>
                    <span class="endpoint-path">/api/events/featured</span>
                    <span class="badge public">Public</span>
                </div>
                <div class="endpoint-description">Get featured events</div>
            </div>
            
            <div class="endpoint">
                <div class="endpoint-header">
                    <span class="method get">GET</span>
                    <span class="endpoint-path">/api/events/upcoming</span>
                    <span class="badge public">Public</span>
                </div>
                <div class="endpoint-description">Get upcoming events sorted by date</div>
            </div>
            
            <div class="endpoint">
                <div class="endpoint-header">
                    <span class="method post">POST</span>
                    <span class="endpoint-path">/api/events/submit</span>
                    <span class="badge public">Public</span>
                </div>
                <div class="endpoint-description">Submit a new event for approval</div>
                <div class="params">
                    <h4>Request Body:</h4>
                    <ul class="param-list">
                        <li>
                            <span class="param-name">title</span>
                            <span class="param-type">string</span>
                            <span style="color: red;">*</span>
                            <div class="param-description">Event title</div>
                        </li>
                        <li>
                            <span class="param-name">description</span>
                            <span class="param-type">string</span>
                            <span style="color: red;">*</span>
                            <div class="param-description">Event description</div>
                        </li>
                        <li>
                            <span class="param-name">start_date</span>
                            <span class="param-type">datetime</span>
                            <span style="color: red;">*</span>
                            <div class="param-description">Event start date and time</div>
                        </li>
                        <li>
                            <span class="param-name">location</span>
                            <span class="param-type">string</span>
                            <span style="color: red;">*</span>
                            <div class="param-description">Event location name</div>
                        </li>
                        <li>
                            <span class="param-name">address</span>
                            <span class="param-type">string</span>
                            <div class="param-description">Full address for geocoding</div>
                        </li>
                    </ul>
                </div>
            </div>
        </div>

        <div class="section">
            <h2>Shops API</h2>
            <p>Access local business directory information including shop details and locations.</p>
            
            <div class="endpoint">
                <div class="endpoint-header">
                    <span class="method get">GET</span>
                    <span class="endpoint-path">/api/shops</span>
                    <span class="badge public">Public</span>
                </div>
                <div class="endpoint-description">List shops with search and filters</div>
                <div class="params">
                    <h4>Query Parameters:</h4>
                    <ul class="param-list">
                        <li>
                            <span class="param-name">search</span>
                            <span class="param-type">string</span>
                            <div class="param-description">Search shops by name or description</div>
                        </li>
                        <li>
                            <span class="param-name">category</span>
                            <span class="param-type">string</span>
                            <div class="param-description">Filter by business category</div>
                        </li>
                        <li>
                            <span class="param-name">amenities</span>
                            <span class="param-type">string</span>
                            <div class="param-description">Filter by amenities (wifi, parking, etc.)</div>
                        </li>
                        <li>
                            <span class="param-name">featured</span>
                            <span class="param-type">boolean</span>
                            <div class="param-description">Show only featured shops</div>
                        </li>
                        <li>
                            <span class="param-name">verified</span>
                            <span class="param-type">boolean</span>
                            <div class="param-description">Show only verified businesses</div>
                        </li>
                    </ul>
                </div>
            </div>
            
            <div class="endpoint">
                <div class="endpoint-header">
                    <span class="method get">GET</span>
                    <span class="endpoint-path">/api/shops/{id}</span>
                    <span class="badge public">Public</span>
                </div>
                <div class="endpoint-description">Get details for a specific shop</div>
            </div>
            
            <div class="endpoint">
                <div class="endpoint-header">
                    <span class="method get">GET</span>
                    <span class="endpoint-path">/api/shops/map</span>
                    <span class="badge public">Public</span>
                </div>
                <div class="endpoint-description">Get shops formatted for map display</div>
            </div>
            
            <div class="endpoint">
                <div class="endpoint-header">
                    <span class="method get">GET</span>
                    <span class="endpoint-path">/api/shops/featured</span>
                    <span class="badge public">Public</span>
                </div>
                <div class="endpoint-description">Get featured shops</div>
            </div>
            
            <div class="endpoint">
                <div class="endpoint-header">
                    <span class="method post">POST</span>
                    <span class="endpoint-path">/api/shops/submit</span>
                    <span class="badge public">Public</span>
                </div>
                <div class="endpoint-description">Submit a new shop for approval</div>
            </div>
        </div>

        <div class="section">
            <h2>System API</h2>
            
            <div class="endpoint">
                <div class="endpoint-header">
                    <span class="method get">GET</span>
                    <span class="endpoint-path">/api/health</span>
                    <span class="badge public">Public</span>
                </div>
                <div class="endpoint-description">Check API health and system status</div>
                <div class="example">
                    <code>{
    "success": true,
    "message": "API is healthy",
    "data": {
        "status": "healthy",
        "version": "2.0",
        "timestamp": "2025-06-25T10:30:00Z"
    }
}</code>
                </div>
            </div>
        </div>

        <div class="section">
            <h2>Error Responses</h2>
            <p>The API uses standard HTTP response codes to indicate success or failure:</p>
            
            <ul>
                <li><strong>200 OK</strong> - Request successful</li>
                <li><strong>400 Bad Request</strong> - Invalid parameters</li>
                <li><strong>401 Unauthorized</strong> - Authentication required</li>
                <li><strong>404 Not Found</strong> - Resource not found</li>
                <li><strong>405 Method Not Allowed</strong> - Invalid HTTP method</li>
                <li><strong>500 Internal Server Error</strong> - Server error</li>
            </ul>
            
            <p>Error responses follow this format:</p>
            <div class="example">
                <code>{
    "success": false,
    "message": "Error description",
    "data": null
}</code>
            </div>
        </div>

        <div class="section">
            <h2>Rate Limiting</h2>
            <p>The API currently does not enforce rate limits, but this may change in the future. Please use the API responsibly and cache responses when appropriate.</p>
        </div>

        <div class="section">
            <h2>Support</h2>
            <p>For API support or to report issues, please contact the YFEvents development team.</p>
        </div>
    </div>
</body>
</html>
HTML;
    }
}