<?php
// ajax/get_project_members.php
require_once '../config/database.php';
require_once '../config/session.php';

Session::redirectIfNotLoggedIn();

header('Content-Type: application/json');

$database = new Database();
$db = $database->getConnection();

$project_id = $_GET['project_id'] ?? null;

if (!$project_id) {
    echo json_encode(['success' => false, 'message' => 'Project ID is required']);
    exit;
}

try {
    // Get users who are members of the selected project with employee role
    $query = "SELECT DISTINCT u.user_id, u.first_name, u.last_name, u.email 
              FROM users u
              INNER JOIN project_members pm ON u.user_id = pm.user_id
              WHERE pm.project_id = :project_id 
              AND u.is_active = TRUE 
              AND u.role = 'employee'
              ORDER BY u.first_name, u.last_name";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':project_id', $project_id);
    $stmt->execute();
    
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'users' => $users]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
