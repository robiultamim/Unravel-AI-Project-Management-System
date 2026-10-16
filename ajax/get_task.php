<?php
// ajax/get_task.php
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
    
    // Get task details
    $query = "SELECT 
                t.*, 
                p.project_name,
                p.manager_id,
                CONCAT(u.first_name, ' ', u.last_name) as assignee_name,
                CONCAT(ub.first_name, ' ', ub.last_name) as assigned_by_name
            FROM tasks t
            LEFT JOIN projects p ON t.project_id = p.project_id
            LEFT JOIN users u ON t.assigned_to = u.user_id
            LEFT JOIN users ub ON t.assigned_by = ub.user_id
            WHERE t.task_id = :task_id";
    
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':task_id', $task_id, PDO::PARAM_INT);
    $stmt->execute();
    
    if ($stmt->rowCount() === 0) {
        echo json_encode(['success' => false, 'message' => 'Task not found']);
        exit();
    }
    
    $task = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get required skills for this task
    $skills_query = "SELECT ts.skill_id, s.skill_name, ts.required_level
                     FROM task_skills ts
                     JOIN skills s ON ts.skill_id = s.skill_id
                     WHERE ts.task_id = :task_id";
    
    $skills_stmt = $conn->prepare($skills_query);
    $skills_stmt->bindParam(':task_id', $task_id, PDO::PARAM_INT);
    $skills_stmt->execute();
    $required_skills = $skills_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $task['required_skills'] = $required_skills;
    
    echo json_encode(['success' => true, 'task' => $task]);
    
} catch (PDOException $e) {
    echo json_encode([
        'success' => false, 
        'message' => 'Database error',
        'debug' => $e->getMessage()
    ]);
}
?>