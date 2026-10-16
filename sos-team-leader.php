<?php
// sos-team-leader.php - Team Leader SOS Management page
require_once 'config/database.php';
require_once 'config/session.php';
require_once 'includes/functions.php';

Session::redirectIfNotLoggedIn();

$database = new Database();
$db = $database->getConnection();

$user_id = $_SESSION['user_id'];
$role = Session::getCurrentUserRole();

// Only team leaders, managers, and admins can access this page
if (!in_array($role, ['team_leader', 'manager', 'admin'])) {
    header('Location: dashboard.php');
    exit;
}

$message = '';
$message_type = '';

// Handle instruction submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['give_instruction'])) {
    $sos_id = $_POST['sos_id'];
    $instruction = $_POST['instruction'];
    $new_status = $_POST['new_status'];
    
    // Update SOS with instruction
    $update_query = "UPDATE sos_signals SET 
                     instruction = :instruction,
                     acknowledged_by = :acknowledged_by,
                     acknowledged_at = NOW(),
                     status = :status";
    
    if ($new_status == 'resolved') {
        $update_query .= ", resolved_at = NOW()";
    }
    
    $update_query .= " WHERE sos_id = :sos_id";
    
    $update_stmt = $db->prepare($update_query);
    $update_stmt->execute([
        'instruction' => $instruction,
        'acknowledged_by' => $user_id,
        'status' => $new_status,
        'sos_id' => $sos_id
    ]);
    
    // Get SOS info to notify employee
    $sos_query = "SELECT s.raised_by, t.title FROM sos_signals s 
                  JOIN tasks t ON s.task_id = t.task_id 
                  WHERE s.sos_id = :sos_id";
    $sos_stmt = $db->prepare($sos_query);
    $sos_stmt->execute(['sos_id' => $sos_id]);
    $sos_info = $sos_stmt->fetch(PDO::FETCH_ASSOC);
    
    // Notify employee
    if ($sos_info) {
        $notif_query = "INSERT INTO notifications (user_id, title, message, type, related_id) 
                       VALUES (:user_id, :title, :message, 'sos', :related_id)";
        $notif_stmt = $db->prepare($notif_query);
        $notif_stmt->execute([
            'user_id' => $sos_info['raised_by'],
            'title' => 'SOS Response Received',
            'message' => 'Team leader has responded to your SOS for task: ' . $sos_info['title'],
            'related_id' => $sos_id
        ]);
    }
    
    $message = 'Instruction sent successfully! The employee has been notified.';
    $message_type = 'success';
}

// Handle status update only
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_status'])) {
    $sos_id = $_POST['sos_id'];
    $new_status = $_POST['new_status'];
    
    $update_query = "UPDATE sos_signals SET status = :status";
    
    if ($new_status == 'resolved') {
        $update_query .= ", resolved_at = NOW()";
    }
    
    $update_query .= " WHERE sos_id = :sos_id";
    
    $update_stmt = $db->prepare($update_query);
    $update_stmt->execute([
        'status' => $new_status,
        'sos_id' => $sos_id
    ]);
    
    $message = 'SOS status updated successfully!';
    $message_type = 'success';
}

// Get SOS requests for projects led by this user
$filter_status = $_GET['status'] ?? 'all';

$sos_query = "SELECT s.*, t.title as task_title, t.due_date, t.priority, t.task_id,
              p.project_name, p.project_id,
              u.first_name as emp_first, u.last_name as emp_last, u.email as emp_email
              FROM sos_signals s
              JOIN tasks t ON s.task_id = t.task_id
              JOIN projects p ON t.project_id = p.project_id
              JOIN users u ON s.raised_by = u.user_id
              WHERE 1=1";

// Filter based on role
if ($role == 'team_leader') {
    $sos_query .= " AND p.team_leader_id = :user_id";
} elseif ($role == 'manager') {
    $sos_query .= " AND p.manager_id = :user_id";
} elseif ($role == 'admin') {
    $sos_query .= " AND p.admin_id = :user_id";
}

if ($filter_status != 'all') {
    $sos_query .= " AND s.status = :status";
}

$sos_query .= " ORDER BY 
              CASE s.status 
                WHEN 'open' THEN 1 
                WHEN 'acknowledged' THEN 2 
                WHEN 'resolved' THEN 3 
              END,
              s.raised_at DESC";

$sos_stmt = $db->prepare($sos_query);
$params = ['user_id' => $user_id];
if ($filter_status != 'all') {
    $params['status'] = $filter_status;
}
$sos_stmt->execute($params);
$sos_requests = $sos_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get counts by status
$count_query = "SELECT 
                COUNT(CASE WHEN s.status = 'open' THEN 1 END) as open_count,
                COUNT(CASE WHEN s.status = 'acknowledged' THEN 1 END) as acknowledged_count,
                COUNT(CASE WHEN s.status = 'resolved' THEN 1 END) as resolved_count,
                COUNT(*) as total_count
                FROM sos_signals s
                JOIN tasks t ON s.task_id = t.task_id
                JOIN projects p ON t.project_id = p.project_id
                WHERE 1=1";

if ($role == 'team_leader') {
    $count_query .= " AND p.team_leader_id = :user_id";
} elseif ($role == 'manager') {
    $count_query .= " AND p.manager_id = :user_id";
} elseif ($role == 'admin') {
    $count_query .= " AND p.admin_id = :user_id";
}

$count_stmt = $db->prepare($count_query);
$count_stmt->execute(['user_id' => $user_id]);
$counts = $count_stmt->fetch(PDO::FETCH_ASSOC);

// Get SOS-related notifications
$notif_query = "SELECT n.*, u.first_name, u.last_name 
                FROM notifications n
                LEFT JOIN users u ON n.related_id = u.user_id
                WHERE n.user_id = :user_id 
                AND n.type = 'sos'
                ORDER BY n.created_at DESC
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
    <title>SOS Management - Unravel</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/dashboard.css">
    <link rel="stylesheet" href="assets/css/profile.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .sos-container {
            padding: 20px;
            max-width: 1400px;
            margin: 0 auto;
        }
        
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .page-header h1 {
            color: #2c3e50;
            font-size: 28px;
            margin: 0;
        }
        
        .page-header h1 i {
            color: #e74c3c;
        }
        
        /* Stats Cards */
        .stats-row {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 30px;
        }
        
        @media (max-width: 992px) {
            .stats-row {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        
        @media (max-width: 576px) {
            .stats-row {
                grid-template-columns: 1fr;
            }
        }
        
        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .stat-card.active {
            border: 2px solid #4361ee;
        }
        
        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
        }
        
        .stat-icon.open {
            background: #fee2e2;
            color: #dc2626;
        }
        
        .stat-icon.acknowledged {
            background: #fef3c7;
            color: #d97706;
        }
        
        .stat-icon.resolved {
            background: #d1fae5;
            color: #059669;
        }
        
        .stat-icon.total {
            background: #e0e7ff;
            color: #4361ee;
        }
        
        .stat-info h3 {
            font-size: 28px;
            font-weight: 700;
            margin: 0;
            color: #2c3e50;
        }
        
        .stat-info p {
            color: #6c757d;
            margin: 0;
            font-size: 14px;
        }
        
        /* Filter */
        .filter-bar {
            background: white;
            border-radius: 12px;
            padding: 15px 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 15px;
            flex-wrap: wrap;
        }
        
        .filter-bar label {
            font-weight: 600;
            color: #495057;
        }
        
        .filter-select {
            padding: 10px 15px;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            font-size: 14px;
            min-width: 150px;
        }
        
        .filter-btn {
            padding: 10px 20px;
            background: #4361ee;
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
        }
        
        .filter-btn:hover {
            background: #3651d4;
        }
        
        /* Main Grid */
        .sos-main-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 30px;
        }
        
        @media (max-width: 1200px) {
            .sos-main-grid {
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
        
        /* SOS Request Cards */
        .sos-request {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            border-left: 5px solid #e74c3c;
            transition: all 0.3s;
        }
        
        .sos-request:hover {
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        
        .sos-request.acknowledged {
            border-left-color: #f39c12;
        }
        
        .sos-request.resolved {
            border-left-color: #27ae60;
            opacity: 0.8;
        }
        
        .sos-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 15px;
        }
        
        .sos-title {
            font-size: 18px;
            font-weight: 600;
            color: #2c3e50;
            margin: 0;
        }
        
        .sos-status {
            padding: 5px 12px;
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
        
        .sos-details {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 10px;
            margin-bottom: 15px;
        }
        
        .sos-detail-item {
            display: flex;
            align-items: center;
            gap: 8px;
            color: #6c757d;
            font-size: 13px;
        }
        
        .sos-detail-item i {
            width: 16px;
            color: #4361ee;
        }
        
        .sos-reason-box {
            background: white;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
        }
        
        .sos-reason-label {
            font-size: 12px;
            font-weight: 600;
            color: #6c757d;
            text-transform: uppercase;
            margin-bottom: 8px;
        }
        
        .sos-reason-text {
            color: #2c3e50;
            font-size: 14px;
            line-height: 1.6;
        }
        
        .priority-badge {
            padding: 3px 10px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .priority-low { background: #d1fae5; color: #059669; }
        .priority-medium { background: #fef3c7; color: #d97706; }
        .priority-high { background: #fed7aa; color: #ea580c; }
        .priority-critical { background: #fee2e2; color: #dc2626; }
        
        /* Instruction Form */
        .instruction-form {
            background: #e8f4fd;
            border-radius: 8px;
            padding: 15px;
            margin-top: 15px;
        }
        
        .instruction-form h4 {
            margin: 0 0 15px 0;
            color: #0c5460;
            font-size: 14px;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #495057;
            font-size: 13px;
        }
        
        .form-control {
            width: 100%;
            padding: 12px;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            font-size: 14px;
        }
        
        .form-control:focus {
            outline: none;
            border-color: #4361ee;
        }
        
        textarea.form-control {
            min-height: 100px;
            resize: vertical;
        }
        
        .form-row {
            display: flex;
            gap: 15px;
            align-items: end;
        }
        
        .form-row .form-group {
            flex: 1;
        }
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
        }
        
        .btn-primary {
            background: #4361ee;
            color: white;
        }
        
        .btn-primary:hover {
            background: #3651d4;
        }
        
        .btn-success {
            background: #27ae60;
            color: white;
        }
        
        .btn-success:hover {
            background: #219a52;
        }
        
        .btn-sm {
            padding: 8px 15px;
            font-size: 13px;
        }
        
        /* Existing Instruction Display */
        .existing-instruction {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            border-radius: 8px;
            padding: 15px;
            margin-top: 15px;
        }
        
        .existing-instruction h4 {
            color: #155724;
            margin: 0 0 10px 0;
            font-size: 14px;
        }
        
        .existing-instruction p {
            color: #155724;
            margin: 0;
        }
        
        /* Notification List */
        .notification-list {
            max-height: 400px;
            overflow-y: auto;
        }
        
        .notification-item {
            padding: 12px;
            background: #f8f9fa;
            border-radius: 8px;
            margin-bottom: 10px;
            border-left: 3px solid #e74c3c;
        }
        
        .notification-item.unread {
            background: #fff5f5;
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
        
        /* Quick Actions */
        .quick-actions {
            display: flex;
            gap: 10px;
            margin-top: 15px;
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
                        <h1><i class="fas fa-life-ring"></i> SOS Management</h1>
                    </div>
                    
                    <!-- Stats Row -->
                    <div class="stats-row">
                        <a href="?status=open" class="stat-card <?php echo $filter_status == 'open' ? 'active' : ''; ?>" style="text-decoration: none;">
                            <div class="stat-icon open">
                                <i class="fas fa-exclamation-circle"></i>
                            </div>
                            <div class="stat-info">
                                <h3><?php echo $counts['open_count']; ?></h3>
                                <p>Open</p>
                            </div>
                        </a>
                        
                        <a href="?status=acknowledged" class="stat-card <?php echo $filter_status == 'acknowledged' ? 'active' : ''; ?>" style="text-decoration: none;">
                            <div class="stat-icon acknowledged">
                                <i class="fas fa-clock"></i>
                            </div>
                            <div class="stat-info">
                                <h3><?php echo $counts['acknowledged_count']; ?></h3>
                                <p>Acknowledged</p>
                            </div>
                        </a>
                        
                        <a href="?status=resolved" class="stat-card <?php echo $filter_status == 'resolved' ? 'active' : ''; ?>" style="text-decoration: none;">
                            <div class="stat-icon resolved">
                                <i class="fas fa-check-circle"></i>
                            </div>
                            <div class="stat-info">
                                <h3><?php echo $counts['resolved_count']; ?></h3>
                                <p>Resolved</p>
                            </div>
                        </a>
                        
                        <a href="?status=all" class="stat-card <?php echo $filter_status == 'all' ? 'active' : ''; ?>" style="text-decoration: none;">
                            <div class="stat-icon total">
                                <i class="fas fa-list"></i>
                            </div>
                            <div class="stat-info">
                                <h3><?php echo $counts['total_count']; ?></h3>
                                <p>Total</p>
                            </div>
                        </a>
                    </div>
                    
                    <div class="sos-main-grid">
                        <!-- SOS Requests List -->
                        <div>
                            <div class="card">
                                <div class="card-header">
                                    <i class="fas fa-list" style="color: #e74c3c; font-size: 20px;"></i>
                                    <h3>SOS Requests 
                                        <?php if ($filter_status != 'all'): ?>
                                            <span style="font-weight: normal; color: #6c757d;">
                                                (<?php echo ucfirst($filter_status); ?>)
                                            </span>
                                        <?php endif; ?>
                                    </h3>
                                </div>
                                
                                <?php if (empty($sos_requests)): ?>
                                    <div class="empty-state">
                                        <i class="fas fa-check-circle"></i>
                                        <p>No SOS requests found.<br>Your team is doing great!</p>
                                    </div>
                                <?php else: ?>
                                    <?php foreach ($sos_requests as $sos): ?>
                                        <div class="sos-request <?php echo $sos['status']; ?>">
                                            <div class="sos-header">
                                                <h4 class="sos-title"><?php echo htmlspecialchars($sos['task_title']); ?></h4>
                                                <span class="sos-status status-<?php echo $sos['status']; ?>">
                                                    <?php echo ucfirst($sos['status']); ?>
                                                </span>
                                            </div>
                                            
                                            <div class="sos-details">
                                                <div class="sos-detail-item">
                                                    <i class="fas fa-user"></i>
                                                    <span><?php echo htmlspecialchars($sos['emp_first'] . ' ' . $sos['emp_last']); ?></span>
                                                </div>
                                                <div class="sos-detail-item">
                                                    <i class="fas fa-folder"></i>
                                                    <span><?php echo htmlspecialchars($sos['project_name']); ?></span>
                                                </div>
                                                <div class="sos-detail-item">
                                                    <i class="fas fa-calendar"></i>
                                                    <span>Due: <?php echo date('M d, Y', strtotime($sos['due_date'])); ?></span>
                                                </div>
                                                <div class="sos-detail-item">
                                                    <i class="fas fa-flag"></i>
                                                    <span class="priority-badge priority-<?php echo $sos['priority']; ?>">
                                                        <?php echo ucfirst($sos['priority']); ?>
                                                    </span>
                                                </div>
                                                <div class="sos-detail-item">
                                                    <i class="fas fa-clock"></i>
                                                    <span>Raised: <?php echo date('M d, Y h:i A', strtotime($sos['raised_at'])); ?></span>
                                                </div>
                                                <div class="sos-detail-item">
                                                    <i class="fas fa-envelope"></i>
                                                    <span><?php echo htmlspecialchars($sos['emp_email']); ?></span>
                                                </div>
                                            </div>
                                            
                                            <div class="sos-reason-box">
                                                <div class="sos-reason-label">Employee's Issue:</div>
                                                <div class="sos-reason-text">
                                                    <?php echo nl2br(htmlspecialchars($sos['reason'] ?: 'No reason provided')); ?>
                                                </div>
                                            </div>
                                            
                                            <?php if (!empty($sos['instruction'])): ?>
                                                <div class="existing-instruction">
                                                    <h4><i class="fas fa-check"></i> Your Instruction:</h4>
                                                    <p><?php echo nl2br(htmlspecialchars($sos['instruction'])); ?></p>
                                                </div>
                                            <?php endif; ?>
                                            
                                            <?php if ($sos['status'] != 'resolved'): ?>
                                                <div class="instruction-form">
                                                    <h4><i class="fas fa-comment-dots"></i> 
                                                        <?php echo empty($sos['instruction']) ? 'Give Instruction' : 'Update Instruction'; ?>
                                                    </h4>
                                                    <form method="POST">
                                                        <input type="hidden" name="sos_id" value="<?php echo $sos['sos_id']; ?>">
                                                        
                                                        <div class="form-group">
                                                            <label for="instruction_<?php echo $sos['sos_id']; ?>">Your Instruction</label>
                                                            <textarea name="instruction" id="instruction_<?php echo $sos['sos_id']; ?>" 
                                                                      class="form-control" 
                                                                      placeholder="Provide guidance to help the employee..."
                                                                      required><?php echo htmlspecialchars($sos['instruction'] ?? ''); ?></textarea>
                                                        </div>
                                                        
                                                        <div class="form-row">
                                                            <div class="form-group">
                                                                <label for="new_status_<?php echo $sos['sos_id']; ?>">Update Status</label>
                                                                <select name="new_status" id="new_status_<?php echo $sos['sos_id']; ?>" class="form-control">
                                                                    <option value="acknowledged" <?php echo $sos['status'] == 'acknowledged' ? 'selected' : ''; ?>>
                                                                        Acknowledged (In Progress)
                                                                    </option>
                                                                    <option value="resolved">Resolved (Completed)</option>
                                                                </select>
                                                            </div>
                                                            <button type="submit" name="give_instruction" class="btn btn-primary">
                                                                <i class="fas fa-paper-plane"></i> Send
                                                            </button>
                                                        </div>
                                                    </form>
                                                </div>
                                            <?php else: ?>
                                                <div class="quick-actions">
                                                    <form method="POST" style="display: inline;">
                                                        <input type="hidden" name="sos_id" value="<?php echo $sos['sos_id']; ?>">
                                                        <input type="hidden" name="new_status" value="open">
                                                        <button type="submit" name="update_status" class="btn btn-sm btn-primary">
                                                            <i class="fas fa-undo"></i> Reopen
                                                        </button>
                                                    </form>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Sidebar - Notifications -->
                        <div>
                            <div class="card">
                                <div class="card-header">
                                    <i class="fas fa-bell" style="color: #4361ee; font-size: 20px;"></i>
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
                            
                            <!-- Quick Stats -->
                            <div class="card">
                                <div class="card-header">
                                    <i class="fas fa-chart-pie" style="color: #27ae60; font-size: 20px;"></i>
                                    <h3>Quick Info</h3>
                                </div>
                                
                                <div style="padding: 10px;">
                                    <p><strong>Open SOS:</strong> <?php echo $counts['open_count']; ?> requests need attention</p>
                                    <p><strong>In Progress:</strong> <?php echo $counts['acknowledged_count']; ?> being handled</p>
                                    <p><strong>Resolved Today:</strong> 
                                        <?php 
                                        $today_query = "SELECT COUNT(*) as count FROM sos_signals s
                                                       JOIN tasks t ON s.task_id = t.task_id
                                                       JOIN projects p ON t.project_id = p.project_id
                                                       WHERE s.status = 'resolved' 
                                                       AND DATE(s.resolved_at) = CURDATE()";
                                        if ($role == 'team_leader') {
                                            $today_query .= " AND p.team_leader_id = :user_id";
                                        } elseif ($role == 'manager') {
                                            $today_query .= " AND p.manager_id = :user_id";
                                        } elseif ($role == 'admin') {
                                            $today_query .= " AND p.admin_id = :user_id";
                                        }
                                        $today_stmt = $db->prepare($today_query);
                                        $today_stmt->execute(['user_id' => $user_id]);
                                        $today_count = $today_stmt->fetch(PDO::FETCH_ASSOC);
                                        echo $today_count['count'];
                                        ?>
                                    </p>
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
