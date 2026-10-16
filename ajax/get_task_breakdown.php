<?php
// ajax/get_task_breakdown.php
header('Content-Type: application/json');

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check authentication
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$task_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($task_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid task ID']);
    exit();
}

try {
    // Include database config
    require_once '../config/database.php';
    
    // Get database connection
    $database = new Database();
    $conn = $database->getConnection();
    
    // Get subtasks
    $query = "SELECT * FROM tasks 
              WHERE parent_task_id = :task_id 
              ORDER BY created_at ASC";
    
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':task_id', $task_id, PDO::PARAM_INT);
    $stmt->execute();
    
    $subtasks = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'subtasks' => $subtasks,
        'total_subtasks' => count($subtasks)
    ]);
    
} catch (PDOException $e) {
    echo json_encode([
        'success' => false, 
        'message' => 'Database error',
        'debug' => $e->getMessage()
    ]);
}
?>