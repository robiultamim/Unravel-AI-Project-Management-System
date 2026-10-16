<?php
// functions.php - Common utility functions

/**
 * Format date to time ago string
 */
function time_ago($datetime) {
    $time = strtotime($datetime);
    $now = time();
    $diff = $now - $time;
    
    if ($diff < 60) {
        return 'just now';
    } elseif ($diff < 3600) {
        $minutes = floor($diff / 60);
        return $minutes . ' minute' . ($minutes > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 86400) {
        $hours = floor($diff / 3600);
        return $hours . ' hour' . ($hours > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 604800) {
        $days = floor($diff / 86400);
        return $days . ' day' . ($days > 1 ? 's' : '') . ' ago';
    } else {
        return date('M d, Y', $time);
    }
}

/**
 * Get task priority badge
 */
function get_priority_badge($priority) {
    $classes = [
        'low' => 'priority-low',
        'medium' => 'priority-medium',
        'high' => 'priority-high',
        'critical' => 'priority-critical'
    ];
    
    $class = $classes[$priority] ?? 'priority-medium';
    return '<span class="task-priority ' . $class . '">' . ucfirst($priority) . '</span>';
}

/**
 * Get task status badge
 */
function get_status_badge($status) {
    $classes = [
        'todo' => 'badge-secondary',
        'in_progress' => 'badge-primary',
        'done' => 'badge-success',
        'approved' => 'badge-info',
        'blocked' => 'badge-danger'
    ];
    
    $class = $classes[$status] ?? 'badge-secondary';
    $text = str_replace('_', ' ', ucfirst($status));
    return '<span class="badge ' . $class . '">' . $text . '</span>';
}

/**
 * Get user's full name
 */
function get_user_name($user_id, $db) {
    $query = "SELECT first_name, last_name FROM users WHERE user_id = ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    return $user ? $user['first_name'] . ' ' . $user['last_name'] : 'Unknown User';
}

/**
 * Check if user has permission for action
 */
function has_permission($required_role, $user_role = null) {
    if ($user_role === null) {
        $user_role = $_SESSION['role'] ?? null;
    }
    
    $hierarchy = ['employee' => 1, 'team_leader' => 2, 'manager' => 3, 'admin' => 4];
    
    return isset($hierarchy[$user_role]) && $hierarchy[$user_role] >= $hierarchy[$required_role];
}

/**
 * Generate AI recommendation for task assignment
 */
function get_ai_recommendation($task_id, $db) {
    $query = "SELECT ts.skill_id, ts.required_level 
              FROM task_skills ts 
              WHERE ts.task_id = ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$task_id]);
    $required_skills = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($required_skills)) {
        return get_recommendation_by_workload($db);
    }
    
    // Get users with matching skills
    $skill_ids = array_column($required_skills, 'skill_id');
    $placeholders = str_repeat('?,', count($skill_ids) - 1) . '?';
    
    $query = "SELECT u.user_id, u.first_name, u.last_name, 
                     COUNT(DISTINCT us.skill_id) as matching_skills,
                     AVG(CASE 
                         WHEN us.proficiency_level = 'beginner' THEN 1
                         WHEN us.proficiency_level = 'intermediate' THEN 2
                         WHEN us.proficiency_level = 'advanced' THEN 3
                         WHEN us.proficiency_level = 'expert' THEN 4
                         ELSE 0
                     END) as avg_proficiency,
                     (SELECT COUNT(*) FROM tasks t 
                      WHERE t.assigned_to = u.user_id 
                      AND t.status IN ('todo', 'in_progress')) as current_load
              FROM users u
              JOIN user_skills us ON u.user_id = us.user_id
              WHERE us.skill_id IN ($placeholders)
              AND u.is_active = TRUE
              AND u.role = 'employee'
              GROUP BY u.user_id
              ORDER BY matching_skills DESC, avg_proficiency DESC, current_load ASC";
    
    $stmt = $db->prepare($query);
    $stmt->execute($skill_ids);
    $candidates = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (!empty($candidates)) {
        return $candidates[0];
    }
    
    return get_recommendation_by_workload($db);
}

function get_recommendation_by_workload($db) {
    $query = "SELECT u.user_id, u.first_name, u.last_name,
                     (SELECT COUNT(*) FROM tasks t 
                      WHERE t.assigned_to = u.user_id 
                      AND t.status IN ('todo', 'in_progress')) as current_load,
                     (SELECT AVG(timeliness_rating) 
                      FROM task_assignments_history tah 
                      WHERE tah.assigned_to = u.user_id) as avg_timeliness
              FROM users u
              WHERE u.is_active = TRUE
              AND u.role = 'employee'
              ORDER BY current_load ASC, avg_timeliness DESC
              LIMIT 1";
    
    $stmt = $db->query($query);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Send reminder notification
 */
function send_reminder($task_id, $reminder_type, $db) {
    $query = "SELECT t.*, u.email, u.first_name 
              FROM tasks t 
              JOIN users u ON t.assigned_to = u.user_id 
              WHERE t.task_id = ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$task_id]);
    $task = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$task) {
        return false;
    }
    
    $messages = [
        'deadline' => [
            'subject' => 'Task Deadline Approaching',
            'message' => "Hi {$task['first_name']}, your task '{$task['task_name']}' is due on " . date('M d, Y', strtotime($task['due_date']))
        ],
        'stuck' => [
            'subject' => 'Task Stuck in Progress',
            'message' => "Hi {$task['first_name']}, your task '{$task['task_name']}' has been in progress for a while. Do you need help?"
        ],
        'update' => [
            'subject' => 'Task Update Reminder',
            'message' => "Hi {$task['first_name']}, please update the progress of your task '{$task['task_name']}'"
        ]
    ];
    
    $message = $messages[$reminder_type] ?? $messages['update'];
    
    // Store in reminders table
    $insertQuery = "INSERT INTO reminders (task_id, user_id, reminder_type, message, channel, scheduled_for) 
                    VALUES (?, ?, ?, ?, 'email', NOW())";
    $insertStmt = $db->prepare($insertQuery);
    $insertStmt->execute([$task_id, $task['assigned_to'], $reminder_type, $message['message']]);
    
    // Send email (implement your email sending logic here)
    // mail($task['email'], $message['subject'], $message['message']);
    
    return true;
}

/**
 * Log user activity
 */
function log_activity($user_id, $action, $details, $db) {
    $query = "INSERT INTO activity_logs (user_id, action, details, ip_address) 
              VALUES (?, ?, ?, ?)";
    $stmt = $db->prepare($query);
    $stmt->execute([$user_id, $action, $details, $_SERVER['REMOTE_ADDR']]);
}
?>