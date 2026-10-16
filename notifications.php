<?php
// notifications.php
require_once 'config/database.php';
require_once 'config/session.php';
require_once 'includes/functions.php';

Session::redirectIfNotLoggedIn();

$database = new Database();
$db = $database->getConnection();

$user_id = $_SESSION['user_id'];

// Handle mark as read
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['mark_read'])) {
        $notification_id = $_POST['notification_id'];
        
        $update_query = "UPDATE notifications SET is_read = 1 
                        WHERE notification_id = :notification_id AND user_id = :user_id";
        $update_stmt = $db->prepare($update_query);
        $update_stmt->execute([
            'notification_id' => $notification_id,
            'user_id' => $user_id
        ]);
        
        $message = 'Notification marked as read!';
        $message_type = 'success';
    } elseif (isset($_POST['mark_all_read'])) {
        $update_query = "UPDATE notifications SET is_read = 1 WHERE user_id = :user_id AND is_read = 0";
        $update_stmt = $db->prepare($update_query);
        $update_stmt->execute(['user_id' => $user_id]);
        
        $message = 'All notifications marked as read!';
        $message_type = 'success';
    } elseif (isset($_POST['delete_notification'])) {
        $notification_id = $_POST['notification_id'];
        
        $delete_query = "DELETE FROM notifications WHERE notification_id = :notification_id AND user_id = :user_id";
        $delete_stmt = $db->prepare($delete_query);
        $delete_stmt->execute([
            'notification_id' => $notification_id,
            'user_id' => $user_id
        ]);
        
        $message = 'Notification deleted!';
        $message_type = 'success';
    }
}

// Get notifications statistics
$stats_query = "SELECT 
                COUNT(*) as total_notifications,
                SUM(CASE WHEN is_read = 0 THEN 1 ELSE 0 END) as unread_count,
                SUM(CASE WHEN is_read = 1 THEN 1 ELSE 0 END) as read_count
                FROM notifications 
                WHERE user_id = :user_id";
$stats_stmt = $db->prepare($stats_query);
$stats_stmt->execute(['user_id' => $user_id]);
$stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);

// Get all notifications
$notifications_query = "SELECT * FROM notifications 
                       WHERE user_id = :user_id 
                       ORDER BY is_read ASC, created_at DESC";
$notifications_stmt = $db->prepare($notifications_query);
$notifications_stmt->execute(['user_id' => $user_id]);
$notifications = $notifications_stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications - Unravel</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/dashboard.css">
    <link rel="stylesheet" href="assets/css/profile.css">
    <link rel="stylesheet" href="assets/css/notifications.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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
                
                <div class="profile-container">
                    <!-- Notifications Stats Card -->
                    <div class="profile-card">
                        <div class="profile-header">
                            <div class="profile-info">
                                <h2><i class="fas fa-bell"></i> Notifications</h2>
                                <p class="profile-role">Stay updated with your activities</p>
                            </div>
                        </div>
                        
                        <!-- Notification Statistics -->
                        <div class="profile-stats">
                            <div class="stat-item">
                                <div class="stat-value"><?php echo $stats['total_notifications']; ?></div>
                                <div class="stat-label">Total</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-value unread-count"><?php echo $stats['unread_count']; ?></div>
                                <div class="stat-label">Unread</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-value"><?php echo $stats['read_count']; ?></div>
                                <div class="stat-label">Read</div>
                            </div>
                        </div>
                        
                        <!-- Mark All as Read Button -->
                        <?php if ($stats['unread_count'] > 0): ?>
                            <div style="padding: 0 20px 20px;">
                                <form method="POST">
                                    <button type="submit" name="mark_all_read" class="btn btn-primary">
                                        <i class="fas fa-check-double"></i> Mark All as Read
                                    </button>
                                </form>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Notifications List -->
                    <div class="skills-section">
                        <div class="section-header">
                            <h3><i class="fas fa-list"></i> All Notifications</h3>
                        </div>
                        
                        <div class="notifications-list">
                            <?php if (empty($notifications)): ?>
                                <div class="empty-state">
                                    <i class="fas fa-inbox"></i>
                                    <p>No notifications yet. You're all caught up!</p>
                                </div>
                            <?php else: ?>
                                <?php foreach ($notifications as $notification): ?>
                                    <div class="notification-item <?php echo $notification['is_read'] ? 'read' : 'unread'; ?>">
                                        <div class="notification-icon">
                                            <?php
                                            $icon_map = [
                                                'task_assigned' => 'fa-tasks',
                                                'task_updated' => 'fa-edit',
                                                'deadline' => 'fa-clock',
                                                'sos' => 'fa-exclamation-triangle',
                                                'reminder' => 'fa-bell',
                                                'performance' => 'fa-chart-line',
                                                'system' => 'fa-cog'
                                            ];
                                            $icon = $icon_map[$notification['type']] ?? 'fa-info-circle';
                                            ?>
                                            <i class="fas <?php echo $icon; ?>"></i>
                                        </div>
                                        
                                        <div class="notification-content">
                                            <div class="notification-header">
                                                <h4><?php echo htmlspecialchars($notification['title']); ?></h4>
                                                <span class="notification-time">
                                                    <?php 
                                                    $time_diff = time() - strtotime($notification['created_at']);
                                                    if ($time_diff < 60) {
                                                        echo 'Just now';
                                                    } elseif ($time_diff < 3600) {
                                                        echo floor($time_diff / 60) . ' min ago';
                                                    } elseif ($time_diff < 86400) {
                                                        echo floor($time_diff / 3600) . ' hours ago';
                                                    } else {
                                                        echo date('M d, Y', strtotime($notification['created_at']));
                                                    }
                                                    ?>
                                                </span>
                                            </div>
                                            
                                            <p class="notification-message">
                                                <?php echo htmlspecialchars($notification['message']); ?>
                                            </p>
                                            
                                            <div class="notification-meta">
                                                <span class="notification-type">
                                                    <?php echo ucfirst(str_replace('_', ' ', $notification['type'])); ?>
                                                </span>
                                                <?php if (!$notification['is_read']): ?>
                                                    <span class="notification-badge">New</span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        
                                        <div class="notification-actions">
                                            <?php if (!$notification['is_read']): ?>
                                                <form method="POST">
                                                    <input type="hidden" name="notification_id" value="<?php echo $notification['notification_id']; ?>">
                                                    <button type="submit" name="mark_read" class="btn-action" title="Mark as Read">
                                                        <i class="fas fa-check"></i>
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                            
                                            <form method="POST">
                                                <input type="hidden" name="notification_id" value="<?php echo $notification['notification_id']; ?>">
                                                <button type="submit" name="delete_notification" class="btn-action btn-delete" 
                                                        title="Delete" 
                                                        onclick="return confirm('Are you sure you want to delete this notification?');">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
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
</body>
</html>
