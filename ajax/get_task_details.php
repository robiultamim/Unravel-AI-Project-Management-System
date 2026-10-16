<?php
// ajax/get_task_details.php
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
    
    // Get comprehensive task details
    $query = "SELECT 
                t.*,
                p.project_name,
                CONCAT(au.first_name, ' ', au.last_name) as assignee_name,
                CONCAT(ab.first_name, ' ', ab.last_name) as assigned_by_name,
                CONCAT(um.first_name, ' ', um.last_name) as manager_name,
                GROUP_CONCAT(DISTINCT s.skill_name SEPARATOR ', ') as required_skills,
                COUNT(DISTINCT st.task_id) as subtask_count,
                COUNT(DISTINCT wl.log_id) as worklog_count,
                COALESCE(SUM(wl.hours_worked), 0) as total_hours_logged
            FROM tasks t
            LEFT JOIN projects p ON t.project_id = p.project_id
            LEFT JOIN users au ON t.assigned_to = au.user_id
            LEFT JOIN users ab ON t.assigned_by = ab.user_id
            LEFT JOIN users um ON p.manager_id = um.user_id
            LEFT JOIN task_skills ts ON t.task_id = ts.task_id
            LEFT JOIN skills s ON ts.skill_id = s.skill_id
            LEFT JOIN tasks st ON t.task_id = st.parent_task_id
            LEFT JOIN work_logs wl ON t.task_id = wl.task_id
            WHERE t.task_id = :task_id
            GROUP BY t.task_id";
    
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':task_id', $task_id, PDO::PARAM_INT);
    $stmt->execute();
    
    if ($stmt->rowCount() === 0) {
        echo json_encode(['success' => false, 'message' => 'Task not found']);
        exit();
    }
    
    $task = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get work logs for this task
    $logs_query = "SELECT wl.*, CONCAT(u.first_name, ' ', u.last_name) as user_name
                   FROM work_logs wl
                   JOIN users u ON wl.user_id = u.user_id
                   WHERE wl.task_id = :task_id
                   ORDER BY wl.log_date DESC";
    
    $logs_stmt = $conn->prepare($logs_query);
    $logs_stmt->bindParam(':task_id', $task_id, PDO::PARAM_INT);
    $logs_stmt->execute();
    $work_logs = $logs_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $task['work_logs'] = $work_logs;
    
    // Format dates
    if ($task['due_date']) {
        $task['due_date_formatted'] = date('F j, Y', strtotime($task['due_date']));
    }
    if ($task['completed_at']) {
        $task['completed_at_formatted'] = date('F j, Y', strtotime($task['completed_at']));
    }
    
    echo json_encode(['success' => true, 'task' => $task]);
    
} catch (PDOException $e) {
    echo json_encode([
        'success' => false, 
        'message' => 'Database error',
        'debug' => $e->getMessage()
    ]);
}
?>