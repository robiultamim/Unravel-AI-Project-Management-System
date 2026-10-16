<?php
// sos-employee.php - Employee SOS page
require_once 'config/database.php';
require_once 'config/session.php';
require_once 'includes/functions.php';

Session::redirectIfNotLoggedIn();

$database = new Database();
$db = $database->getConnection();

$user_id = $_SESSION['user_id'];
$role = Session::getCurrentUserRole();

// Only employees can access this page
if ($role != 'employee') {
    header('Location: dashboard.php');
    exit;
}

$message = '';
$message_type = '';

// Get pre-selected task from URL if provided
$pre_selected_task_id = isset($_GET['task_id']) ? (int)$_GET['task_id'] : null;

// Handle SOS form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['create_sos'])) {
    $task_id = $_POST['task_id'];
    $reason = $_POST['reason'];
    
    // Check if SOS already exists for this task
    $check_query = "SELECT sos_id FROM sos_signals WHERE task_id = :task_id AND status IN ('open', 'acknowledged')";
    $check_stmt = $db->prepare($check_query);
    $check_stmt->execute(['task_id' => $task_id]);
    
    if ($check_stmt->rowCount() > 0) {
        $message = 'An SOS request already exists for this task.';
        $message_type = 'error';
    } else {
        // Create SOS signal
        $insert_query = "INSERT INTO sos_signals (task_id, raised_by, reason, status) 
                        VALUES (:task_id, :raised_by, :reason, 'open')";
        $insert_stmt = $db->prepare($insert_query);
        $insert_stmt->execute([
            'task_id' => $task_id,
            'raised_by' => $user_id,
            'reason' => $reason
        ]);
        
        // Get task info to find team leader
        $task_query = "SELECT t.title, p.team_leader_id, p.project_name 
                       FROM tasks t 
                       JOIN projects p ON t.project_id = p.project_id 
                       WHERE t.task_id = :task_id";
        $task_stmt = $db->prepare($task_query);
        $task_stmt->execute(['task_id' => $task_id]);
        $task_info = $task_stmt->fetch(PDO::FETCH_ASSOC);
        
        // Notify team leader
        if ($task_info && $task_info['team_leader_id']) {
            $notif_query = "INSERT INTO notifications (user_id, title, message, type, related_id) 
                           VALUES (:user_id, :title, :message, 'sos', :related_id)";
            $notif_stmt = $db->prepare($notif_query);
            $notif_stmt->execute([
                'user_id' => $task_info['team_leader_id'],
                'title' => 'SOS Request',
                'message' => 'Employee needs help with task: ' . $task_info['title'],
                'related_id' => $task_id
            ]);
        }
        
        $message = 'SOS request created successfully! Your team leader will be notified.';
        $message_type = 'success';
    }
}

// Get employee's in-progress tasks (for creating new SOS)
$tasks_query = "SELECT t.task_id, t.title, p.project_name 
                FROM tasks t 
                JOIN projects p ON t.project_id = p.project_id 
                WHERE t.assigned_to = :user_id 
                AND t.status = 'in_progress'
                AND NOT EXISTS (
                    SELECT 1 FROM sos_signals s 
                    WHERE s.task_id = t.task_id 
                    AND s.status IN ('open', 'acknowledged')
                )
                ORDER BY t.due_date ASC";
$tasks_stmt = $db->prepare($tasks_query);
$tasks_stmt->execute(['user_id' => $user_id]);
$available_tasks = $tasks_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get employee's SOS requests with instructions
$sos_query = "SELECT s.*, t.title as task_title, t.due_date, p.project_name,
              u.first_name as leader_first, u.last_name as leader_last
              FROM sos_signals s
              JOIN tasks t ON s.task_id = t.task_id
              JOIN projects p ON t.project_id = p.project_id
              LEFT JOIN users u ON s.acknowledged_by = u.user_id
              WHERE s.raised_by = :user_id
              ORDER BY s.raised_at DESC";
$sos_stmt = $db->prepare($sos_query);
$sos_stmt->execute(['user_id' => $user_id]);
$my_sos_requests = $sos_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get SOS-related notifications for this user
$notif_query = "SELECT * FROM notifications 
                WHERE user_id = :user_id 
                AND type = 'sos'
                ORDER BY created_at DESC
                LIMIT 10";
$notif_stmt = $db->prepare($notif_query);
$notif_stmt->execute(['user_id' => $user_id]);
$notifications = $notif_stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SOS Request - Unravel</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/dashboard.css">
    <link rel="stylesheet" href="assets/css/profile.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .sos-container {
            padding: 20px;
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .page-header {
            margin-bottom: 30px;
        }
        
        .page-header h1 {
            color: #2c3e50;
            font-size: 28px;
            margin-bottom: 10px;
        }
        
        .page-header p {
            color: #6c757d;
        }
        
        .sos-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
        }
        
        @media (max-width: 992px) {
            .sos-grid {
                grid-template-columns: 1fr;
            }
        }
        
        .card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            padding: 25px;
            margin-bottom: 20px;
        }
        
        .card-header {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f0f0f0;
        }
        
        .card-header h3 {
            margin: 0;
            color: #2c3e50;
        }
        
        .card-header i {
            color: #e74c3c;
            font-size: 24px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #495057;
        }
        
        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            font-size: 14px;
            transition: border-color 0.3s;
        }
        
        .form-control:focus {
            outline: none;
            border-color: #4361ee;
        }
        
        textarea.form-control {
            min-height: 120px;
            resize: vertical;
        }
        
        .btn {
            padding: 12px 25px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.3s;
        }
        
        .btn-danger {
            background: #e74c3c;
            color: white;
        }
        
        .btn-danger:hover {
            background: #c0392b;
        }
        
        .btn-block {
            width: 100%;
        }
        
        .sos-list {
            max-height: 600px;
            overflow-y: auto;
        }
        
        .sos-item {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 15px;
            border-left: 4px solid #e74c3c;
        }
        
        .sos-item.acknowledged {
            border-left-color: #f39c12;
        }
        
        .sos-item.resolved {
            border-left-color: #27ae60;
        }
        
        .sos-item-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 10px;
        }
        
        .sos-task-title {
            font-weight: 600;
            color: #2c3e50;
            font-size: 16px;
        }
        
        .sos-status {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .status-open {
            background: #fee2e2;
            color: #dc2626;
        }
        
        .status-acknowledged {
            background: #fef3c7;
            color: #d97706;
        }
        
        .status-resolved {
            background: #d1fae5;
            color: #059669;
        }
        
        .sos-project {
            color: #6c757d;
            font-size: 13px;
            margin-bottom: 10px;
        }
        
        .sos-reason {
            background: white;
            padding: 12px;
            border-radius: 6px;
            margin-bottom: 10px;
            font-size: 14px;
            color: #495057;
        }
        
        .sos-reason-label {
            font-weight: 600;
            color: #6c757d;
            font-size: 12px;
            text-transform: uppercase;
            margin-bottom: 5px;
        }
        
        .instruction-box {
            background: #e8f4fd;
            border: 1px solid #bee5eb;
            border-radius: 8px;
            padding: 15px;
            margin-top: 15px;
        }
        
        .instruction-box h4 {
            color: #0c5460;
            margin: 0 0 10px 0;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .instruction-box p {
            color: #0c5460;
            margin: 0;
            font-size: 14px;
        }
        
        .instruction-from {
            font-size: 12px;
            color: #6c757d;
            margin-top: 10px;
        }
        
        .sos-meta {
            display: flex;
            justify-content: space-between;
            font-size: 12px;
            color: #6c757d;
            margin-top: 10px;
        }
        
        .notification-list {
            max-height: 300px;
            overflow-y: auto;
        }
        
        .notification-item {
            padding: 12px;
            background: #f8f9fa;
            border-radius: 8px;
            margin-bottom: 10px;
            border-left: 3px solid #4361ee;
        }
        
        .notification-item.unread {
            background: #e8f4fd;
        }
        
        .notification-title {
            font-weight: 600;
            color: #2c3e50;
            font-size: 14px;
        }
        
        .notification-message {
            color: #6c757d;
            font-size: 13px;
            margin-top: 5px;
        }
        
        .notification-time {
            color: #adb5bd;
            font-size: 11px;
            margin-top: 5px;
        }
        
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #6c757d;
        }
        
        .empty-state i {
            font-size: 48px;
            margin-bottom: 15px;
            opacity: 0.5;
        }
    </style>
</head>
<body>
    <div class="app-container">
        <?php include 'includes/sidebar.php'; ?>
        
        <div class="main-content">
            <?php include 'includes/header.php'; ?>
            
            <div class="content-wrapper">
                <?php if ($message): ?>
                    <div class="alert alert-<?php echo $message_type; ?>">
                        <?php echo htmlspecialchars($message); ?>
                    </div>
                <?php endif; ?>
                
                <div class="sos-container">
                    <div class="page-header">
                        <h1><i class="fas fa-life-ring"></i> I'm Stuck - Request Help</h1>
                        <p>Create an SOS request when you need help with a task. Your team leader will be notified.</p>
                    </div>
                    
                    <div class="sos-grid">
                        <!-- Left Column - Create SOS & Notifications -->
                        <div>
                            <!-- Create SOS Form -->
                            <div class="card">
                                <div class="card-header">
                                    <i class="fas fa-plus-circle"></i>
                                    <h3>Create SOS Request</h3>
                                </div>
                                
                                <?php if (empty($available_tasks)): ?>
                                    <div class="empty-state">
                                        <i class="fas fa-check-circle"></i>
                                        <p>No tasks available for SOS request.<br>You either have no in-progress tasks or already have active SOS requests.</p>
                                    </div>
                                <?php else: ?>
                                    <form method="POST">
                                        <div class="form-group">
                                            <label for="task_id">Select Task</label>
                                            <select name="task_id" id="task_id" class="form-control" required>
                                                <option value="">-- Choose a task --</option>
                                                <?php foreach ($available_tasks as $task): ?>
                                                    <option value="<?php echo $task['task_id']; ?>" <?php echo ($pre_selected_task_id == $task['task_id']) ? 'selected' : ''; ?>>
                                                        <?php echo htmlspecialchars($task['title'] . ' (' . $task['project_name'] . ')'); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        
                                        <div class="form-group">
                                            <label for="reason">Describe your problem</label>
                                            <textarea name="reason" id="reason" class="form-control" 
                                                      placeholder="What are you stuck on? Describe the issue in detail..."
                                                      required></textarea>
                                        </div>
                                        
                                        <button type="submit" name="create_sos" class="btn btn-danger btn-block">
                                            <i class="fas fa-life-ring"></i> Send SOS Request
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>
                            
                            <!-- SOS Notifications -->
                            <div class="card">
                                <div class="card-header">
                                    <i class="fas fa-bell" style="color: #4361ee;"></i>
                                    <h3>SOS Notifications</h3>
                                </div>
                                
                                <div class="notification-list">
                                    <?php if (empty($notifications)): ?>
                                        <div class="empty-state">
                                            <i class="fas fa-bell-slash"></i>
                                            <p>No SOS notifications yet.</p>
                                        </div>
                                    <?php else: ?>
                                        <?php foreach ($notifications as $notif): ?>
                                            <div class="notification-item <?php echo !$notif['is_read'] ? 'unread' : ''; ?>">
                                                <div class="notification-title"><?php echo htmlspecialchars($notif['title']); ?></div>
                                                <div class="notification-message"><?php echo htmlspecialchars($notif['message']); ?></div>
                                                <div class="notification-time">
                                                    <?php echo date('M d, Y h:i A', strtotime($notif['created_at'])); ?>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Right Column - My SOS Requests -->
                        <div>
                            <div class="card">
                                <div class="card-header">
                                    <i class="fas fa-list"></i>
                                    <h3>My SOS Requests</h3>
                                </div>
                                
                                <div class="sos-list">
                                    <?php if (empty($my_sos_requests)): ?>
                                        <div class="empty-state">
                                            <i class="fas fa-thumbs-up"></i>
                                            <p>No SOS requests yet. You're doing great!</p>
                                        </div>
                                    <?php else: ?>
                                        <?php foreach ($my_sos_requests as $sos): ?>
                                            <div class="sos-item <?php echo $sos['status']; ?>">
                                                <div class="sos-item-header">
                                                    <span class="sos-task-title"><?php echo htmlspecialchars($sos['task_title']); ?></span>
                                                    <span class="sos-status status-<?php echo $sos['status']; ?>">
                                                        <?php echo ucfirst($sos['status']); ?>
                                                    </span>
                                                </div>
                                                
                                                <div class="sos-project">
                                                    <i class="fas fa-folder"></i> <?php echo htmlspecialchars($sos['project_name']); ?>
                                                    &nbsp;|&nbsp;
                                                    <i class="fas fa-calendar"></i> Due: <?php echo date('M d, Y', strtotime($sos['due_date'])); ?>
                                                </div>
                                                
                                                <div class="sos-reason-label">Your Issue:</div>
                                                <div class="sos-reason">
                                                    <?php echo nl2br(htmlspecialchars($sos['reason'])); ?>
                                                </div>
                                                
                                                <?php if (!empty($sos['instruction'])): ?>
                                                    <div class="instruction-box">
                                                        <h4><i class="fas fa-lightbulb"></i> Team Leader's Instruction</h4>
                                                        <p><?php echo nl2br(htmlspecialchars($sos['instruction'])); ?></p>
                                                        <?php if ($sos['leader_first']): ?>
                                                            <div class="instruction-from">
                                                                From: <?php echo htmlspecialchars($sos['leader_first'] . ' ' . $sos['leader_last']); ?>
                                                                <?php if ($sos['acknowledged_at']): ?>
                                                                    • <?php echo date('M d, Y h:i A', strtotime($sos['acknowledged_at'])); ?>
                                                                <?php endif; ?>
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                <?php endif; ?>
                                                
                                                <div class="sos-meta">
                                                    <span>Requested: <?php echo date('M d, Y h:i A', strtotime($sos['raised_at'])); ?></span>
                                                    <?php if ($sos['resolved_at']): ?>
                                                        <span>Resolved: <?php echo date('M d, Y', strtotime($sos['resolved_at'])); ?></span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
