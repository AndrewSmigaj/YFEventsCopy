<?php
// www/html/ajax/forum/topics.php
header('Content-Type: application/json');
require_once '../../../config/database.php';
require_once '../../../src/Models/ForumTopic.php';

use YFEvents\Models\ForumTopic;

$categoryId = $_GET['category_id'] ?? null;
$limit = (int)($_GET['limit'] ?? 20);
$offset = (int)($_GET['offset'] ?? 0);

if (!$categoryId) {
    echo json_encode(['success' => false, 'message' => 'Missing category ID']);
    exit;
}

$topicModel = new ForumTopic($pdo);
$topics = $topicModel->getTopicsByCategory($categoryId, $limit, $offset);

echo json_encode([
    'success' => true,
    'topics' => $topics
]);
?>