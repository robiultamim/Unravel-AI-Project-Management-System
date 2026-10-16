<?php
// ajax/assign_task.php
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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

$task_id = isset($_POST['task_id']) ? intval($_POST['task_id']) : 0;
$user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;

if ($task_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid task ID']);
    exit();
}

if ($user_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid user ID']);
    exit();
}

try {
    require_once '../config/database.php';
    
    $database = new Database();
    $conn = $database->getConnection();
    
    $assigner_id = $_SESSION['user_id'];
    $user_role = $_SESSION['role'] ?? 'employee';
    
    // Check if user has permission to assign tasks
    if (!in_array($user_role, ['admin', 'manager', 'team_leader'])) {
        echo json_encode(['success' => false, 'message' => 'You do not have permission to assign tasks']);
        exit();
    }
    
    // Check if task exists
    $check_query = "SELECT t.*, p.project_name FROM tasks t 
                    LEFT JOIN projects p ON t.project_id = p.project_id 
                    WHERE t.task_id = :task_id";
    $check_stmt = $conn->prepare($check_query);
    $check_stmt->bindParam(':task_id', $task_id, PDO::PARAM_INT);
    $check_stmt->execute();
    
    if ($check_stmt->rowCount() === 0) {
        echo json_encode(['success' => false, 'message' => 'Task not found']);
        exit();
    }
    
    $task = $check_stmt->fetch(PDO::FETCH_ASSOC);
    
    // Check if user exists and is active
    $user_query = "SELECT user_id, first_name, last_name, email, is_active 
                   FROM users WHERE user_id = :user_id";
    $user_stmt = $conn->prepare($user_query);
    $user_stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $user_stmt->execute();
    
    if ($user_stmt->rowCount() === 0) {
        echo json_encode(['success' => false, 'message' => 'User not found']);
        exit();
    }
    
    $user = $user_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user['is_active']) {
        echo json_encode(['success' => false, 'message' => 'Cannot assign task to inactive user']);
        exit();
    }
    
    // Update task assignment
    $update_query = "UPDATE tasks 
                     SET assigned_to = :assigned_to, 
                         assigned_by = :assigned_by,
                         updated_at = NOW() 
                     WHERE task_id = :task_id";
    
    $update_stmt = $conn->prepare($update_query);
    $update_stmt->bindParam(':assigned_to', $user_id, PDO::PARAM_INT);
    $update_stmt->bindParam(':assigned_by', $assigner_id, PDO::PARAM_INT);
    $update_stmt->bindParam(':task_id', $task_id, PDO::PARAM_INT);
    
    if ($update_stmt->execute()) {
        // Create notification for assigned user
        if ($user_id != $assigner_id) {
            $notification_query = "INSERT INTO notifications 
                                  (user_id, title, message, type, related_id, created_at)
                                  VALUES (:user_id, :title, :message, 'task_assigned', :task_id, NOW())";
            
            $notification_stmt = $conn->prepare($notification_query);
            $title = "New Task Assigned";
            $message = "You have been assigned to task: {$task['title']} in project {$task['project_name']}";
            
            $notification_stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
            $notification_stmt->bindParam(':title', $title);
            $notification_stmt->bindParam(':message', $message);
            $notification_stmt->bindParam(':task_id', $task_id, PDO::PARAM_INT);
            $notification_stmt->execute();
        }
        
        echo json_encode([
            'success' => true, 
            'message' => 'Task assigned successfully',
            'assigned_to' => [
                'user_id' => $user['user_id'],
                'name' => $user['first_name'] . ' ' . $user['last_name']
            ]
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to assign task']);
    }
    
} catch (PDOException $e) {
    echo json_encode([
        'success' => false, 
        'message' => 'Database error',
        'debug' => $e->getMessage()
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false, 
        'message' => 'Error assigning task',
        'debug' => $e->getMessage()
    ]);
}
?>
