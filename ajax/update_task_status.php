<?php
// ajax/update_task_status.php
header('Content-Type: application/json');

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check authentication
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized. Please login.']);
    exit();
}

// Get POST data
$task_id = isset($_POST['task_id']) ? intval($_POST['task_id']) : 0;
$new_status = isset($_POST['status']) ? trim($_POST['status']) : '';

// Validate inputs
if ($task_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid task ID.']);
    exit();
}

$allowed_statuses = ['todo', 'in_progress', 'done', 'approved', 'blocked'];
if (!in_array($new_status, $allowed_statuses)) {
    echo json_encode(['success' => false, 'message' => 'Invalid status value.']);
    exit();
}

try {
    // Include database config
    require_once '../config/database.php';
    
    // Get database connection
    $database = new Database();
    $conn = $database->getConnection();
    
    // Get user info
    $user_id = $_SESSION['user_id'];
    $user_role = $_SESSION['role'] ?? 'employee';
    
    // 1. Check if task exists and get details
    $check_query = "SELECT t.*, p.manager_id, p.project_name 
                    FROM tasks t 
                    LEFT JOIN projects p ON t.project_id = p.project_id 
                    WHERE t.task_id = :task_id";
    
    $check_stmt = $conn->prepare($check_query);
    $check_stmt->bindParam(':task_id', $task_id, PDO::PARAM_INT);
    $check_stmt->execute();
    
    if ($check_stmt->rowCount() === 0) {
        echo json_encode(['success' => false, 'message' => 'Task not found.']);
        exit();
    }
    
    $task = $check_stmt->fetch(PDO::FETCH_ASSOC);
    
    // 2. Check permissions
    $can_update = false;
    
    // Admins can update any task
    if ($user_role === 'admin') {
        $can_update = true;
    }
    // Managers can update tasks in their projects
    else if ($user_role === 'manager' && $task['manager_id'] == $user_id) {
        $can_update = true;
    }
    // Team leaders can update tasks in their projects
    else if ($user_role === 'team_leader') {
        // Check if user is a team leader in the same project as the task
        $team_query = "SELECT 1 FROM project_members 
                      WHERE project_id = :project_id 
                      AND user_id = :user_id 
                      AND (role_in_project = 'team_leader' OR is_team_leader = 1)";
        $team_stmt = $conn->prepare($team_query);
        $team_stmt->bindParam(':project_id', $task['project_id'], PDO::PARAM_INT);
        $team_stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $team_stmt->execute();
        
        if ($team_stmt->rowCount() > 0 || $task['assigned_to'] == $user_id) {
            $can_update = true;
        }
    }
    // Employees can only update their own tasks
    else if ($user_role === 'employee' && $task['assigned_to'] == $user_id) {
        $can_update = true;
    }
    
    if (!$can_update) {
        echo json_encode(['success' => false, 'message' => 'You do not have permission to update this task.']);
        exit();
    }
    
    // 2b. Check if moving to/from 'approved' or 'blocked' - only admins, managers and team leaders allowed
    $old_status = $task['status'];
    $involves_approved = ($new_status === 'approved' || $old_status === 'approved');
    $involves_blocked = ($new_status === 'blocked' || $old_status === 'blocked');
    
    if ($involves_approved && $user_role !== 'admin' && $user_role !== 'manager' && $user_role !== 'team_leader') {
        echo json_encode(['success' => false, 'message' => 'Only managers and team leaders can approve or unapprove tasks.']);
        exit();
    }
    
    if ($involves_blocked && $user_role !== 'admin' && $user_role !== 'manager' && $user_role !== 'team_leader') {
        echo json_encode(['success' => false, 'message' => 'Only managers and team leaders can block or unblock tasks.']);
        exit();
    }
    
    // 3. Update the task status
    $update_query = "UPDATE tasks 
                     SET status = :status, kanban_column = :status, updated_at = NOW() 
                     WHERE task_id = :task_id";
    
    $update_stmt = $conn->prepare($update_query);
    $update_stmt->bindParam(':status', $new_status);
    $update_stmt->bindParam(':task_id', $task_id, PDO::PARAM_INT);
    
    if ($update_stmt->execute()) {
        // 4. Update project progress if task is completed
        if ($new_status === 'done') {
            $progress_query = "UPDATE projects p
                              SET progress_percentage = (
                                  SELECT COALESCE(
                                      COUNT(CASE WHEN t2.status = 'done' THEN 1 END) * 100.0 / 
                                      NULLIF(COUNT(*), 0), 0)
                                  FROM tasks t2
                                  WHERE t2.project_id = p.project_id
                                  AND t2.parent_task_id IS NULL
                              )
                              WHERE p.project_id = :project_id";
            
            $progress_stmt = $conn->prepare($progress_query);
            $progress_stmt->bindParam(':project_id', $task['project_id'], PDO::PARAM_INT);
            $progress_stmt->execute();
        }
        
        echo json_encode([
            'success' => true, 
            'message' => 'Task status updated successfully.',
            'new_status' => $new_status,
            'task_name' => $task['title']
        ]);
        
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update task in database.']);
    }
    
} catch (PDOException $e) {
    // Handle database errors
    echo json_encode([
        'success' => false, 
        'message' => 'Database error occurred.',
        'debug' => $e->getMessage()
    ]);
} catch (Exception $e) {
    // Handle other errors
    echo json_encode([
        'success' => false, 
        'message' => 'An error occurred.',
        'debug' => $e->getMessage()
    ]);
}
?>