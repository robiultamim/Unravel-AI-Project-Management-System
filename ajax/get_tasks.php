<?php
// ajax/get_tasks.php
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

try {
    // Include database config
    require_once '../config/database.php';
    
    // Get database connection
    $database = new Database();
    $conn = $database->getConnection();
    
    $user_id = $_SESSION['user_id'];
    $user_role = $_SESSION['role'] ?? 'employee';
    
    // Build query based on user role
    if (in_array($user_role, ['admin', 'manager'])) {
        // Admins and managers see all tasks
        $query = "SELECT 
                    t.task_id,
                    t.title as task_name,
                    t.description,
                    t.status,
                    t.priority,
                    t.assigned_to,
                    t.due_date,
                    p.project_name,
                    CONCAT(u.first_name, ' ', u.last_name) as assignee_name,
                    t.estimated_hours,
                    t.actual_hours,
                    t.created_at
                FROM tasks t
                LEFT JOIN projects p ON t.project_id = p.project_id
                LEFT JOIN users u ON t.assigned_to = u.user_id
                WHERE t.parent_task_id IS NULL
                ORDER BY 
                    FIELD(t.priority, 'critical', 'high', 'medium', 'low'),
                    t.due_date ASC,
                    t.created_at DESC";
        
        $stmt = $conn->prepare($query);
        
    } else {
        // Team leaders and employees see assigned tasks
        $query = "SELECT 
                    t.task_id,
                    t.title as task_name,
                    t.description,
                    t.status,
                    t.priority,
                    t.assigned_to,
                    t.due_date,
                    p.project_name,
                    CONCAT(u.first_name, ' ', u.last_name) as assignee_name,
                    t.estimated_hours,
                    t.actual_hours,
                    t.created_at
                FROM tasks t
                LEFT JOIN projects p ON t.project_id = p.project_id
                LEFT JOIN users u ON t.assigned_to = u.user_id
                WHERE t.parent_task_id IS NULL
                AND (
                    t.assigned_to = :user_id 
                    OR EXISTS (
                        SELECT 1 FROM user_hierarchy uh 
                        WHERE uh.user_id = t.assigned_to 
                        AND uh.reports_to = :user_id
                    )
                    OR EXISTS (
                        SELECT 1 FROM project_members pm 
                        WHERE pm.project_id = t.project_id 
                        AND pm.user_id = :user_id
                    )
                )
                ORDER BY 
                    FIELD(t.priority, 'critical', 'high', 'medium', 'low'),
                    t.due_date ASC,
                    t.created_at DESC";
        
        $stmt = $conn->prepare($query);
        $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    }
    
    $stmt->execute();
    $tasks = [];
    
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        // Format date if exists
        $due_date = $row['due_date'] ? date('M d, Y', strtotime($row['due_date'])) : 'No due date';
        $created_date = date('M d', strtotime($row['created_at']));
        
        // Shorten description
        $description = $row['description'] ?? '';
        $description_short = strlen($description) > 100 
            ? substr($description, 0, 100) . '...' 
            : $description;
        
        $tasks[] = [
            'task_id' => $row['task_id'],
            'task_name' => $row['task_name'],
            'project_name' => $row['project_name'] ?? 'Unassigned',
            'status' => $row['status'],
            'priority' => $row['priority'],
            'assignee_name' => $row['assignee_name'] ?? 'Unassigned',
            'due_date' => $due_date,
            'created_date' => $created_date,
            'description' => $description,
            'description_short' => $description_short,
            'estimated_hours' => $row['estimated_hours'],
            'actual_hours' => $row['actual_hours']
        ];
    }
    
    echo json_encode([
        'success' => true,
        'tasks' => $tasks,
        'total_tasks' => count($tasks),
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    
} catch (PDOException $e) {
    echo json_encode([
        'success' => false, 
        'message' => 'Database error loading tasks',
        'debug' => $e->getMessage()
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false, 
        'message' => 'Error loading tasks',
        'debug' => $e->getMessage()
    ]);
}
?>