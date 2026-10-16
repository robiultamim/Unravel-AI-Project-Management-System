<?php
// ajax/get_user_details.php
require_once '../config/database.php';
require_once '../config/session.php';

Session::redirectIfNotLoggedIn();

header('Content-Type: application/json');

if (!isset($_GET['id'])) {
    echo json_encode(['error' => 'User ID required']);
    exit();
}

$database = new Database();
$db = $database->getConnection();
$user_id = $_GET['id'];

$query = "SELECT * FROM users WHERE user_id = ?";
$stmt = $db->prepare($query);
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    echo json_encode(['error' => 'User not found']);
    exit();
}

// Get user's projects
$projects_query = "SELECT p.project_name, pm.role_in_project 
                  FROM project_members pm
                  JOIN projects p ON pm.project_id = p.project_id
                  WHERE pm.user_id = ?
                  ORDER BY p.created_at DESC";
$projects_stmt = $db->prepare($projects_query);
$projects_stmt->execute([$user_id]);
$user['projects'] = $projects_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get user's performance stats
$stats_query = "SELECT 
                (SELECT COUNT(*) FROM tasks WHERE assigned_to = ?) as total_tasks,
                (SELECT COUNT(*) FROM tasks WHERE assigned_to = ? AND status IN ('done', 'approved')) as completed_tasks,
                (SELECT COUNT(*) FROM tasks WHERE assigned_to = ? AND status = 'in_progress') as in_progress_tasks";
$stats_stmt = $db->prepare($stats_query);
$stats_stmt->execute([$user_id, $user_id, $user_id]);
$user['stats'] = $stats_stmt->fetch(PDO::FETCH_ASSOC);

echo json_encode($user);
?>