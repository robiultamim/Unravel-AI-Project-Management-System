<?php
// ajax/get_projects.php
header('Content-Type: application/json');

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check authentication
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

try {
    require_once '../config/database.php';
    
    $database = new Database();
    $conn = $database->getConnection();
    
    $user_id = $_SESSION['user_id'];
    $user_role = $_SESSION['role'] ?? 'employee';
    
    // Build query based on user role
    if (in_array($user_role, ['admin', 'manager', 'team_leader'])) {
        $query = "SELECT p.*, 
                         CONCAT(u.first_name, ' ', u.last_name) as manager_name,
                         (SELECT COUNT(*) FROM tasks t WHERE t.project_id = p.project_id) as task_count,
                         (SELECT COUNT(*) FROM tasks t WHERE t.project_id = p.project_id AND t.status = 'done') as completed_tasks
                  FROM projects p
                  LEFT JOIN users u ON p.manager_id = u.user_id
                  WHERE p.status = 'active' 
                  AND (p.admin_id = :user_id OR p.manager_id = :user_id OR p.team_leader_id = :user_id)
                  ORDER BY p.project_name";
    } else {
        // Employees see projects they are members of
        $query = "SELECT DISTINCT p.*, 
                         CONCAT(u.first_name, ' ', u.last_name) as manager_name,
                         (SELECT COUNT(*) FROM tasks t WHERE t.project_id = p.project_id) as task_count,
                         (SELECT COUNT(*) FROM tasks t WHERE t.project_id = p.project_id AND t.status = 'done') as completed_tasks
                  FROM projects p
                  LEFT JOIN users u ON p.manager_id = u.user_id
                  INNER JOIN project_members pm ON p.project_id = pm.project_id
                  WHERE p.status = 'active' 
                  AND pm.user_id = :user_id
                  ORDER BY p.project_name";
    }
    
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $stmt->execute();
    
    $projects = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate progress percentage for each project
    foreach ($projects as &$project) {
        if ($project['task_count'] > 0) {
            $project['progress'] = round(($project['completed_tasks'] / $project['task_count']) * 100, 2);
        } else {
            $project['progress'] = 0;
        }
    }
    
    echo json_encode([
        'success' => true,
        'projects' => $projects,
        'total' => count($projects)
    ]);
    
} catch (PDOException $e) {
    echo json_encode([
        'success' => false, 
        'message' => 'Database error',
        'debug' => $e->getMessage()
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false, 
        'message' => 'Error loading projects',
        'debug' => $e->getMessage()
    ]);
}
?>
