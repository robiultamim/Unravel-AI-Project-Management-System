<?php
// ajax/send_sos.php
require_once '../config/database.php';
require_once '../config/session.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

if (!Session::isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Please login first']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['task_id'])) {
    $database = new Database();
    $db = $database->getConnection();
    
    $task_id = intval($_POST['task_id']);
    $user_id = $_SESSION['user_id'];
    
    // Check if task exists and belongs to user
    $query = "SELECT t.*, u.first_name, u.last_name FROM tasks t 
              JOIN users u ON t.assigned_to = u.user_id 
              WHERE t.task_id = ? AND t.assigned_to = ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$task_id, $user_id]);
    
    if ($stmt->rowCount() == 0) {
        echo json_encode(['success' => false, 'message' => 'Task not found or not assigned to you']);
        exit();
    }
    
    $task = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Check for existing pending SOS
    $query = "SELECT * FROM sos_signals 
              WHERE task_id = ? AND status = 'pending'";
    $stmt = $db->prepare($query);
    $stmt->execute([$task_id]);
    
    if ($stmt->rowCount() > 0) {
        echo json_encode(['success' => false, 'message' => 'Help request already sent for this task']);
        exit();
    }
    
    // Create SOS signal
    $query = "INSERT INTO sos_signals (task_id, user_id, message, status, created_at) 
              VALUES (?, ?, ?, 'pending', NOW())";
    $stmt = $db->prepare($query);
    $success = $stmt->execute([$task_id, $user_id, "I need help with task: {$task['title']}"]);;
    
    if ($success) {
        // Create notification for team leader/manager
        $sos_id = $db->lastInsertId();
        
        // Find team leader/manager (simplified logic)
        $query = "SELECT user_id FROM users 
                  WHERE role IN ('team_leader', 'manager') 
                  AND is_active = TRUE 
                  LIMIT 1";
        $stmt = $db->query($query);
        $leader = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($leader) {
            $message = "{$task['first_name']} {$task['last_name']} needs help with task: {$task['title']}";
            $query = "INSERT INTO notifications (user_id, title, message, type, related_id, created_at) 
                      VALUES (?, 'SOS Request', ?, 'sos', ?, NOW())";
            $stmt = $db->prepare($query);
            $stmt->execute([$leader['user_id'], $message, $sos_id]);
        }
        
        echo json_encode(['success' => true, 'message' => 'Help request sent successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to send help request']);
    }
} else {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
}
?>