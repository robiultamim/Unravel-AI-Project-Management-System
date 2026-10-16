<?php
// ajax/get_task_count.php
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

$status = isset($_GET['status']) ? trim($_GET['status']) : '';

$allowed_statuses = ['todo', 'in_progress', 'done', 'approved', 'blocked'];
if (!in_array($status, $allowed_statuses)) {
    echo json_encode(['success' => false, 'message' => 'Invalid status']);
    exit();
}

try {
    require_once '../config/database.php';
    
    $database = new Database();
    $conn = $database->getConnection();
    
    $user_id = $_SESSION['user_id'];
    $user_role = $_SESSION['role'] ?? 'employee';
    
    // Build query based on user role
    $query = "SELECT COUNT(*) as count FROM tasks t";
    
    if ($user_role === 'employee') {
        $query .= " WHERE t.assigned_to = :user_id AND t.status = :status";
    } elseif ($user_role === 'team_leader') {
        $query .= " WHERE t.status = :status AND (
                        t.assigned_to = :user_id 
                        OR EXISTS (
                            SELECT 1 FROM projects p 
                            WHERE p.project_id = t.project_id 
                            AND p.team_leader_id = :user_id
                        )
                    )";
    } elseif ($user_role === 'manager') {
        $query .= " WHERE t.status = :status AND EXISTS (
                        SELECT 1 FROM projects p 
                        WHERE p.project_id = t.project_id 
                        AND p.manager_id = :user_id
                    )";
    } elseif ($user_role === 'admin') {
        $query .= " WHERE t.status = :status AND EXISTS (
                        SELECT 1 FROM projects p 
                        WHERE p.project_id = t.project_id 
                        AND p.admin_id = :user_id
                    )";
    } else {
        $query .= " WHERE t.status = :status";
    }
    
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':status', $status);
    
    if (in_array($user_role, ['employee', 'team_leader', 'manager', 'admin'])) {
        $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    }
    
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'status' => $status,
        'count' => intval($result['count'])
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
        'message' => 'Error getting task count',
        'debug' => $e->getMessage()
    ]);
}
?>
