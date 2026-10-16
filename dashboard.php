<?php
// dashboard.php
require_once 'config/database.php';
require_once 'config/session.php';
require_once 'includes/functions.php';

Session::redirectIfNotLoggedIn();

$database = new Database();
$db = $database->getConnection();

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];

// Get user statistics based on role
$stats = [];

if ($role == 'admin') {
    // Admin statistics
    $query = "SELECT 
                (SELECT COUNT(DISTINCT pm.user_id) FROM project_members pm 
                 JOIN projects p ON pm.project_id = p.project_id 
                 WHERE p.admin_id = :user_id) as total_users,
                (SELECT COUNT(*) FROM projects WHERE status = 'active' AND admin_id = :user_id) as active_projects,
                (SELECT COUNT(*) FROM tasks t 
                 JOIN projects p ON t.project_id = p.project_id 
                 WHERE p.admin_id = :user_id) as total_tasks,
                (SELECT COUNT(*) FROM tasks t 
                 JOIN projects p ON t.project_id = p.project_id 
                 WHERE p.admin_id = :user_id AND (t.status = 'done' OR t.status = 'approved')) as completed_tasks";
    $stmt = $db->prepare($query);
    $stmt->execute(['user_id' => $user_id]);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
} elseif ($role == 'manager') {
    // Manager statistics
    $query = "SELECT 
                (SELECT COUNT(DISTINCT t.assigned_to) 
                 FROM tasks t 
                 JOIN projects p ON t.project_id = p.project_id 
                 WHERE p.manager_id = :user_id AND t.assigned_to IS NOT NULL) as team_members,
                (SELECT COUNT(*) FROM tasks t 
                 JOIN projects p ON t.project_id = p.project_id 
                 WHERE p.manager_id = :user_id AND t.status IN ('todo', 'in_progress')) as active_tasks,
                (SELECT COUNT(*) FROM tasks t 
                 JOIN projects p ON t.project_id = p.project_id 
                 WHERE p.manager_id = :user_id AND t.status IN ('done', 'approved')) as completed_tasks";
    $stmt = $db->prepare($query);
    $stmt->execute(['user_id' => $user_id]);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
} elseif ($role == 'team_leader') {
    // Team leader statistics
    $query = "SELECT 
                (SELECT COUNT(*) FROM project_members pm JOIN projects p ON p.project_id = pm.project_id WHERE p.team_leader_id = :user_id AND role_in_project = 'employee') as team_members,
                (SELECT COUNT(*) FROM tasks t JOIN projects p ON p.project_id = t.project_id WHERE p.team_leader_id = :user_id AND t.status IN ('todo', 'in_progress')) as active_tasks,
                (SELECT COUNT(*) FROM tasks t JOIN projects p ON p.project_id = t.project_id WHERE p.team_leader_id = :user_id AND t.status IN ('done', 'approved')) as completed_tasks,
                (SELECT COUNT(*) FROM sos_signals WHERE status = 'open') as help_requests";
    $stmt = $db->prepare($query);
    $stmt->execute(['user_id' => $user_id]);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
} else {
    // Employee statistics
    $query = "SELECT 
                (SELECT COUNT(*) FROM tasks WHERE assigned_to = :user_id) as assigned_tasks,
                (SELECT COUNT(*) FROM tasks WHERE assigned_to = :user_id AND status = 'in_progress') as in_progress_tasks,
                (SELECT COUNT(*) FROM tasks WHERE assigned_to = :user_id AND status IN ('done', 'approved')) as completed_tasks,
                (SELECT COUNT(*) FROM tasks WHERE assigned_to = :user_id AND due_date BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 7 DAY)) as due_this_week";
    $stmt = $db->prepare($query);
    $stmt->execute(['user_id' => $user_id]);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Get recent activities
$query = "SELECT n.* FROM notifications n 
          WHERE n.user_id = :user_id 
          ORDER BY n.created_at DESC LIMIT 5";
$stmt = $db->prepare($query);
$stmt->execute(['user_id' => $user_id]);
$recent_activities = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get current tasks for employee
$current_tasks = [];
$my_projects = [];
$overdue_tasks = [];

if ($role == 'admin') {
    // Get projects for admin
    $query = "SELECT p.project_id, p.project_name, p.status, p.progress_percentage,
              (SELECT COUNT(*) FROM tasks t WHERE t.project_id = p.project_id) as total_tasks,
              (SELECT COUNT(*) FROM tasks t WHERE t.project_id = p.project_id AND t.status IN ('done', 'approved')) as completed_tasks
              FROM projects p
              WHERE p.admin_id = :user_id
              ORDER BY p.created_at DESC LIMIT 3";
    $stmt = $db->prepare($query);
    $stmt->execute(['user_id' => $user_id]);
    $my_projects = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} elseif ($role == 'manager') {
    // Get projects for manager
    $query = "SELECT p.project_id, p.project_name, p.status, p.progress_percentage,
              (SELECT COUNT(*) FROM tasks t WHERE t.project_id = p.project_id) as total_tasks,
              (SELECT COUNT(*) FROM tasks t WHERE t.project_id = p.project_id AND t.status IN ('done', 'approved')) as completed_tasks
              FROM projects p
              WHERE p.manager_id = :user_id
              ORDER BY p.created_at DESC LIMIT 3";
    $stmt = $db->prepare($query);
    $stmt->execute(['user_id' => $user_id]);
    $my_projects = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} elseif ($role == 'team_leader') {
    // Get projects for team leader
    $query = "SELECT p.project_id, p.project_name, p.status, p.progress_percentage,
              (SELECT COUNT(*) FROM tasks t WHERE t.project_id = p.project_id) as total_tasks,
              (SELECT COUNT(*) FROM tasks t WHERE t.project_id = p.project_id AND t.status IN ('done', 'approved')) as completed_tasks
              FROM projects p
              WHERE p.team_leader_id = :user_id
              ORDER BY p.created_at DESC LIMIT 3";
    $stmt = $db->prepare($query);
    $stmt->execute(['user_id' => $user_id]);
    $my_projects = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} elseif ($role == 'employee') {
    $query = "SELECT t.*, p.project_name FROM tasks t 
              LEFT JOIN projects p ON t.project_id = p.project_id 
              WHERE t.assigned_to = :user_id 
              AND t.status IN ('todo', 'in_progress')
              ORDER BY t.due_date ASC LIMIT 3";
    $stmt = $db->prepare($query);
    $stmt->execute(['user_id' => $user_id]);
    $current_tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get projects the employee is part of
    $query = "SELECT p.project_id, p.project_name, p.status, p.progress_percentage,
              (SELECT COUNT(*) FROM tasks t WHERE t.project_id = p.project_id AND t.assigned_to = :user_id) as my_tasks,
              (SELECT COUNT(*) FROM tasks t WHERE t.project_id = p.project_id AND t.assigned_to = :user_id AND t.status IN ('done', 'approved')) as completed_tasks
              FROM projects p
              JOIN project_members pm ON p.project_id = pm.project_id
              WHERE pm.user_id = :user_id
              ORDER BY p.created_at DESC LIMIT 3";
    $stmt = $db->prepare($query);
    $stmt->execute(['user_id' => $user_id]);
    $my_projects = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get overdue tasks
    $query = "SELECT t.*, p.project_name FROM tasks t 
              LEFT JOIN projects p ON t.project_id = p.project_id 
              WHERE t.assigned_to = :user_id 
              AND t.status NOT IN ('done', 'approved')
              AND t.due_date < CURDATE()
              ORDER BY t.due_date ASC LIMIT 5";
    $stmt = $db->prepare($query);
    $stmt->execute(['user_id' => $user_id]);
    $overdue_tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Unravel - Dashboard</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="role-<?php echo $role; ?>">
    <div class="container">
        <?php include 'includes/sidebar.php'; ?>
        
        <div class="main-content">
            <?php include 'includes/header.php'; ?>
            
            <div class="dashboard-grid">
                <!-- Role-specific dashboard content -->
                <?php if ($role == 'admin'): ?>
                    <!-- Admin Dashboard -->
                    <div class="dashboard-role role-admin">
                        <div class="card">
                            <div class="card-header">
                                <div class="card-title">System Overview</div>
                            </div>
                            <div class="card-body">
                                <div class="stat-card">
                                    <div class="stat-label">Total Users</div>
                                    <div class="stat-value"><?php echo $stats['total_users'] ?? 0; ?></div>
                                </div>
                                <div class="stat-card">
                                    <div class="stat-label">Active Projects</div>
                                    <div class="stat-value"><?php echo $stats['active_projects'] ?? 0; ?></div>
                                </div>
                                <div class="stat-card">
                                    <div class="stat-label">Total Tasks</div>
                                    <div class="stat-value"><?php echo $stats['total_tasks'] ?? 0; ?></div>
                                </div>
                                <div class="stat-card">
                                    <div class="stat-label">Completed Tasks</div>
                                    <div class="stat-value"><?php echo $stats['completed_tasks'] ?? 0; ?></div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Common cards for all roles -->
                        <div class="card">
                            <div class="card-header">
                                <div class="card-title">Quick Actions</div>
                            </div>
                            <div class="card-body">
                                <a href="kanban.php" class="btn btn-primary" style="width: 100%; margin-bottom: 10px;">
                                    <i class="fas fa-plus"></i> Create New Task
                                </a>
                                <a href="reports.php" class="btn btn-outline" style="width: 100%; margin-bottom: 10px;">
                                    <i class="fas fa-chart-pie"></i> Generate Report
                                </a>
                                <a href="reports.php" class="btn btn-outline" style="width: 100%;">
                                    <i class="fas fa-user"></i> My Work Summary
                                </a>
                            </div>
                        </div>
                        
                        <!-- My Projects -->
                        <div class="card">
                            <div class="card-header">
                                <div class="card-title">My Projects</div>
                            </div>
                            <div class="card-body">
                                <?php if (empty($my_projects)): ?>
                                    <p style="color: var(--text-secondary); text-align: center;">No projects</p>
                                <?php else: ?>
                                    <?php foreach ($my_projects as $project): 
                                        $project_progress = $project['total_tasks'] > 0 ? round(($project['completed_tasks'] / $project['total_tasks']) * 100) : 0;
                                    ?>
                                        <div class="project-item" style="margin-bottom: 15px; padding: 10px; background: var(--bg-secondary); border-radius: 8px;">
                                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                                                <div class="project-name" style="font-weight: 600;"><?php echo htmlspecialchars($project['project_name']); ?></div>
                                                <span class="badge" style="background: <?php echo $project['status'] == 'active' ? 'var(--success)' : 'var(--warning)'; ?>; color: white; padding: 2px 8px; border-radius: 4px; font-size: 12px;">
                                                    <?php echo ucfirst($project['status']); ?>
                                                </span>
                                            </div>
                                            <div style="font-size: 12px; color: var(--text-secondary); margin-bottom: 5px;">
                                                Tasks: <?php echo $project['completed_tasks']; ?>/<?php echo $project['total_tasks']; ?> completed
                                            </div>
                                            <div class="progress-bar" style="height: 6px; background: var(--bg-tertiary); border-radius: 3px;">
                                                <div class="progress-fill" style="width: <?php echo $project_progress; ?>%; height: 100%; background: <?php echo $project_progress >= 80 ? 'var(--success)' : ($project_progress >= 50 ? 'var(--primary)' : 'var(--warning)'); ?>; border-radius: 3px;"></div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Recent Activity -->
                        <div class="card">
                            <div class="card-header">
                                <div class="card-title">Recent Activity</div>
                            </div>
                            <div class="card-body">
                                <?php if (empty($recent_activities)): ?>
                                    <p style="color: var(--text-secondary); text-align: center;">No recent activity</p>
                                <?php else: ?>
                                    <?php foreach ($recent_activities as $activity): ?>
                                        <div class="activity-item" style="display: flex; align-items: flex-start; margin-bottom: 12px; padding-bottom: 12px; border-bottom: 1px solid var(--border-color);">
                                            <div class="activity-icon" style="width: 32px; height: 32px; border-radius: 50%; background: var(--primary); color: white; display: flex; align-items: center; justify-content: center; margin-right: 10px; flex-shrink: 0;">
                                                <i class="fas fa-<?php echo $activity['type'] == 'task_assigned' ? 'tasks' : ($activity['type'] == 'deadline' ? 'clock' : 'bell'); ?>"></i>
                                            </div>
                                            <div style="flex: 1;">
                                                <div style="font-weight: 500;"><?php echo htmlspecialchars($activity['title']); ?></div>
                                                <div style="font-size: 12px; color: var(--text-secondary);"><?php echo date('M d, h:i A', strtotime($activity['created_at'])); ?></div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                                <a href="notifications.php" class="btn btn-sm btn-outline" style="width: 100%;">View All Notifications</a>
                            </div>
                        </div>
                                
                        
                    </div>
                    
                <?php elseif ($role == 'manager'): ?>
                    <!-- Manager Dashboard -->
                    <div class="dashboard-role role-manager">
                        <div class="card">
                            <div class="card-header">
                                <div class="card-title">Team Performance</div>
                            </div>
                            <div class="card-body">
                                <div class="stat-card">
                                    <div class="stat-label">Total Team Employees</div>
                                    <div class="stat-value"><?php echo $stats['team_members'] ?? 0; ?></div>
                                </div>
                                <div class="stat-card">
                                    <div class="stat-label">Active Tasks</div>
                                    <div class="stat-value"><?php echo $stats['active_tasks'] ?? 0; ?></div>
                                </div>
                                <div class="stat-card">
                                    <div class="stat-label">Completed Tasks</div>
                                    <div class="stat-value"><?php echo $stats['completed_tasks'] ?? 0; ?></div>
                                </div>
                                <div class="stat-card">
                                    <div class="stat-label">Completion Rate</div>
                                    <div class="stat-value">
                                        <?php 
                                        $total = ($stats['active_tasks'] ?? 0) + ($stats['completed_tasks'] ?? 0);
                                        echo $total > 0 ? round(($stats['completed_tasks'] ?? 0) / $total * 100) . '%' : '0%';
                                        ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="card">
                            <div class="card-header">
                                <div class="card-title">Pending Approvals</div>
                            </div>
                            <div class="card-body">
                                <?php
                                $query = "SELECT COUNT(*) as count FROM tasks WHERE status = 'done'";
                                $stmt = $db->query($query);
                                $approvals = $stmt->fetch(PDO::FETCH_ASSOC);
                                ?>
                                <div class="approval-item">
                                    <div class="approval-title">Tasks for Approval</div>
                                    <div class="approval-desc"><?php echo $approvals['count']; ?> tasks waiting for your approval</div>
                                    <a href="tasks.php?status=done" class="btn btn-sm btn-primary">Review</a>
                                </div>
                            </div>
                        </div>




                        <!-- Team Performance for manager -->
                        <div class="card">
                            <div class="card-header">
                                <div class="card-title">Team Performance</div>
                            </div>
                            <div class="card-body">
                                <?php
                                $team_query = "SELECT DISTINCT u.user_id, u.first_name, u.last_name, 
                                            (SELECT COUNT(*) FROM tasks t 
                                             JOIN projects p ON t.project_id = p.project_id 
                                             WHERE t.assigned_to = u.user_id AND t.status IN ('done', 'approved') AND p.manager_id = :user_id) as completed,
                                            (SELECT COUNT(*) FROM tasks t 
                                             JOIN projects p ON t.project_id = p.project_id 
                                             WHERE t.assigned_to = u.user_id AND p.manager_id = :user_id) as total
                                            FROM users u 
                                            JOIN project_members pm ON u.user_id = pm.user_id
                                            JOIN projects p ON pm.project_id = p.project_id
                                            WHERE p.manager_id = :user_id AND pm.role_in_project = 'employee'
                                            LIMIT 4";
                                $team_stmt = $db->prepare($team_query);
                                $team_stmt->execute(['user_id' => $user_id]);
                                $team_members = $team_stmt->fetchAll(PDO::FETCH_ASSOC);
                                
                                foreach ($team_members as $member):
                                    $percentage = $member['total'] > 0 ? round(($member['completed'] / $member['total']) * 100) : 0;
                                ?>
                                    <div class="team-member">
                                        <div class="member-name"><?php echo htmlspecialchars($member['first_name'] . ' ' . $member['last_name']); ?></div>
                                        <div class="member-percentage"><?php echo $percentage; ?>%</div>
                                        <div class="progress-bar">
                                            <div class="progress-fill" style="width: <?php echo $percentage; ?>%; 
                                                background: <?php echo $percentage >= 80 ? 'var(--success)' : ($percentage >= 60 ? 'var(--primary)' : 'var(--warning)'); ?>;"></div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>




                        <!-- Quick Actions for manager -->
                        <div class="card">
                            <div class="card-header">
                                <div class="card-title">Quick Actions</div>
                            </div>
                            <div class="card-body">
                                <a href="kanban.php" class="btn btn-primary" style="width: 100%; margin-bottom: 10px;">
                                    <i class="fas fa-plus"></i> Create New Task
                                </a>
                                <a href="reports.php" class="btn btn-outline" style="width: 100%; margin-bottom: 10px;">
                                    <i class="fas fa-chart-pie"></i> Generate Report
                                </a>
                                <a href="reports.php" class="btn btn-outline" style="width: 100%;">
                                    <i class="fas fa-user"></i> My Work Summary
                                </a>
                            </div>
                        </div>
                        
                        <!-- My Projects -->
                        <div class="card">
                            <div class="card-header">
                                <div class="card-title">My Projects</div>
                            </div>
                            <div class="card-body">
                                <?php if (empty($my_projects)): ?>
                                    <p style="color: var(--text-secondary); text-align: center;">No projects</p>
                                <?php else: ?>
                                    <?php foreach ($my_projects as $project): 
                                        $project_progress = $project['total_tasks'] > 0 ? round(($project['completed_tasks'] / $project['total_tasks']) * 100) : 0;
                                    ?>
                                        <div class="project-item" style="margin-bottom: 15px; padding: 10px; background: var(--bg-secondary); border-radius: 8px;">
                                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                                                <div class="project-name" style="font-weight: 600;"><?php echo htmlspecialchars($project['project_name']); ?></div>
                                                <span class="badge" style="background: <?php echo $project['status'] == 'active' ? 'var(--success)' : 'var(--warning)'; ?>; color: white; padding: 2px 8px; border-radius: 4px; font-size: 12px;">
                                                    <?php echo ucfirst($project['status']); ?>
                                                </span>
                                            </div>
                                            <div style="font-size: 12px; color: var(--text-secondary); margin-bottom: 5px;">
                                                Tasks: <?php echo $project['completed_tasks']; ?>/<?php echo $project['total_tasks']; ?> completed
                                            </div>
                                            <div class="progress-bar" style="height: 6px; background: var(--bg-tertiary); border-radius: 3px;">
                                                <div class="progress-fill" style="width: <?php echo $project_progress; ?>%; height: 100%; background: <?php echo $project_progress >= 80 ? 'var(--success)' : ($project_progress >= 50 ? 'var(--primary)' : 'var(--warning)'); ?>; border-radius: 3px;"></div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>

                    </div>
                    
                <?php elseif ($role == 'team_leader'): ?>
                    <!-- Team Leader Dashboard -->
                    <div class="dashboard-role role-teamleader">
                        <div class="card">
                            <div class="card-header">
                                <div class="card-title">Team Status</div>
                            </div>
                            <div class="card-body">
                                <div class="stat-card">
                                    <div class="stat-label">Total Team Employee</div>
                                    <div class="stat-value"><?php echo $stats['team_members'] ?? 0; ?></div>
                                </div>
                                <div class="stat-card">
                                    <div class="stat-label">Active Tasks</div>
                                    <div class="stat-value"><?php echo $stats['active_tasks'] ?? 0; ?></div>
                                </div>
                                <div class="stat-card">
                                    <div class="stat-label">Completed Tasks</div>
                                    <div class="stat-value"><?php echo $stats['completed_tasks'] ?? 0; ?></div>
                                </div>
                                <div class="stat-card">
                                    <div class="stat-label">Help Requests</div>
                                    <div class="stat-value"><?php echo $stats['help_requests'] ?? 0; ?></div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="card">
                            <div class="card-header">
                                <div class="card-title">Team Tasks Overview</div>
                            </div>
                            <div class="card-body">
                                <?php
                                $total = ($stats['active_tasks'] ?? 0) + ($stats['completed_tasks'] ?? 0);
                                $completed_pct = $total > 0 ? ($stats['completed_tasks'] ?? 0) / $total * 100 : 0;
                                ?>
                                <div class="progress-item">
                                    <div class="progress-label">Completed</div>
                                    <div class="progress-value"><?php echo $stats['completed_tasks'] ?? 0; ?>/<?php echo $total; ?></div>
                                    <div class="progress-bar">
                                        <div class="progress-fill" style="width: <?php echo $completed_pct; ?>%"></div>
                                    </div>
                                </div>
                            </div>
                        </div>


                        <!-- Team Performance for team leader -->
                        <div class="card">
                            <div class="card-header">
                                <div class="card-title">Team Performance</div>
                            </div>
                            <div class="card-body">
                                <?php
                                $team_query = "SELECT DISTINCT u.user_id, u.first_name, u.last_name, 
                                            (SELECT COUNT(*) FROM tasks t 
                                             JOIN projects p ON t.project_id = p.project_id 
                                             WHERE t.assigned_to = u.user_id AND t.status IN ('done', 'approved') AND p.team_leader_id = :user_id) as completed,
                                            (SELECT COUNT(*) FROM tasks t 
                                             JOIN projects p ON t.project_id = p.project_id 
                                             WHERE t.assigned_to = u.user_id AND p.team_leader_id = :user_id) as total
                                            FROM users u 
                                            JOIN project_members pm ON u.user_id = pm.user_id
                                            JOIN projects p ON pm.project_id = p.project_id
                                            WHERE p.team_leader_id = :user_id AND pm.role_in_project = 'employee'
                                            LIMIT 4";
                                $team_stmt = $db->prepare($team_query);
                                $team_stmt->execute(['user_id' => $user_id]);
                                $team_members = $team_stmt->fetchAll(PDO::FETCH_ASSOC);
                                
                                foreach ($team_members as $member):
                                    $percentage = $member['total'] > 0 ? round(($member['completed'] / $member['total']) * 100) : 0;
                                ?>
                                    <div class="team-member">
                                        <div class="member-name"><?php echo htmlspecialchars($member['first_name'] . ' ' . $member['last_name']); ?></div>
                                        <div class="member-percentage"><?php echo $percentage; ?>%</div>
                                        <div class="progress-bar">
                                            <div class="progress-fill" style="width: <?php echo $percentage; ?>%; 
                                                background: <?php echo $percentage >= 80 ? 'var(--success)' : ($percentage >= 60 ? 'var(--primary)' : 'var(--warning)'); ?>;"></div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>










                        <!-- Quick Actions -->
                        <div class="card">
                            <div class="card-header">
                                <div class="card-title">Quick Actions</div>
                            </div>
                            <div class="card-body">
                                <a href="kanban.php" class="btn btn-primary" style="width: 100%; margin-bottom: 10px;">
                                    <i class="fas fa-plus"></i> Create New Task
                                </a>
                                <a href="reports.php" class="btn btn-outline" style="width: 100%; margin-bottom: 10px;">
                                    <i class="fas fa-chart-pie"></i> Generate Report
                                </a>
                                <a href="reports.php" class="btn btn-outline" style="width: 100%;">
                                    <i class="fas fa-user"></i> My Work Summary
                                </a>
                            </div>
                        </div>
                        
                        <!-- My Projects -->
                        <div class="card">
                            <div class="card-header">
                                <div class="card-title">My Projects</div>
                            </div>
                            <div class="card-body">
                                <?php if (empty($my_projects)): ?>
                                    <p style="color: var(--text-secondary); text-align: center;">No projects</p>
                                <?php else: ?>
                                    <?php foreach ($my_projects as $project): 
                                        $project_progress = $project['total_tasks'] > 0 ? round(($project['completed_tasks'] / $project['total_tasks']) * 100) : 0;
                                    ?>
                                        <div class="project-item" style="margin-bottom: 15px; padding: 10px; background: var(--bg-secondary); border-radius: 8px;">
                                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                                                <div class="project-name" style="font-weight: 600;"><?php echo htmlspecialchars($project['project_name']); ?></div>
                                                <span class="badge" style="background: <?php echo $project['status'] == 'active' ? 'var(--success)' : 'var(--warning)'; ?>; color: white; padding: 2px 8px; border-radius: 4px; font-size: 12px;">
                                                    <?php echo ucfirst($project['status']); ?>
                                                </span>
                                            </div>
                                            <div style="font-size: 12px; color: var(--text-secondary); margin-bottom: 5px;">
                                                Tasks: <?php echo $project['completed_tasks']; ?>/<?php echo $project['total_tasks']; ?> completed
                                            </div>
                                            <div class="progress-bar" style="height: 6px; background: var(--bg-tertiary); border-radius: 3px;">
                                                <div class="progress-fill" style="width: <?php echo $project_progress; ?>%; height: 100%; background: <?php echo $project_progress >= 80 ? 'var(--success)' : ($project_progress >= 50 ? 'var(--primary)' : 'var(--warning)'); ?>; border-radius: 3px;"></div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>

                    </div>
                    
                <?php else: ?>
                    <!-- Employee Dashboard -->
                    <div class="dashboard-role role-employee">
                        <div class="card">
                            <div class="card-header">
                                <div class="card-title">My Tasks</div>
                            </div>
                            <div class="card-body">
                                <div class="stat-card">
                                    <div class="stat-label">Assigned</div>
                                    <div class="stat-value"><?php echo $stats['assigned_tasks'] ?? 0; ?></div>
                                </div>
                                <div class="stat-card">
                                    <div class="stat-label">In Progress</div>
                                    <div class="stat-value"><?php echo $stats['in_progress_tasks'] ?? 0; ?></div>
                                </div>
                                <div class="stat-card">
                                    <div class="stat-label">Completed</div>
                                    <div class="stat-value"><?php echo $stats['completed_tasks'] ?? 0; ?></div>
                                </div>
                                <div class="stat-card">
                                    <div class="stat-label">Due This Week</div>
                                    <div class="stat-value"><?php echo $stats['due_this_week'] ?? 0; ?></div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Overdue Tasks Alert -->
                        <?php if (!empty($overdue_tasks)): ?>
                        <div class="card" style="border-left: 4px solid var(--danger);">
                            <div class="card-header">
                                <div class="card-title" style="color: var(--danger);"><i class="fas fa-exclamation-triangle"></i> Overdue Tasks</div>
                            </div>
                            <div class="card-body">
                                <?php foreach ($overdue_tasks as $task): ?>
                                    <div class="task-item" style="border-left: 3px solid var(--danger); padding-left: 10px; margin-bottom: 10px;">
                                        <div class="task-title"><?php echo htmlspecialchars($task['title']); ?></div>
                                        <div class="task-meta">
                                            <span><?php echo $task['project_name']; ?></span>
                                            <span style="color: var(--danger);">Overdue: <?php echo date('M d', strtotime($task['due_date'])); ?></span>
                                        </div>
                                        <a href="kanban.php" class="btn btn-sm btn-primary">Work on it</a>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <!-- My Projects -->
                        <div class="card">
                            <div class="card-header">
                                <div class="card-title">My Projects</div>
                            </div>
                            <div class="card-body">
                                <?php if (empty($my_projects)): ?>
                                    <p style="color: var(--text-secondary); text-align: center;">No projects assigned</p>
                                <?php else: ?>
                                    <?php foreach ($my_projects as $project): 
                                        $project_progress = $project['my_tasks'] > 0 ? round(($project['completed_tasks'] / $project['my_tasks']) * 100) : 0;
                                    ?>
                                        <div class="project-item" style="margin-bottom: 15px; padding: 10px; background: var(--bg-secondary); border-radius: 8px;">
                                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                                                <div class="project-name" style="font-weight: 600;"><?php echo htmlspecialchars($project['project_name']); ?></div>
                                                <span class="badge" style="background: <?php echo $project['status'] == 'active' ? 'var(--success)' : 'var(--warning)'; ?>; color: white; padding: 2px 8px; border-radius: 4px; font-size: 12px;">
                                                    <?php echo ucfirst($project['status']); ?>
                                                </span>
                                            </div>
                                            <div style="font-size: 12px; color: var(--text-secondary); margin-bottom: 5px;">
                                                My Tasks: <?php echo $project['completed_tasks']; ?>/<?php echo $project['my_tasks']; ?> completed
                                            </div>
                                            <div class="progress-bar" style="height: 6px; background: var(--bg-tertiary); border-radius: 3px;">
                                                <div class="progress-fill" style="width: <?php echo $project_progress; ?>%; height: 100%; background: <?php echo $project_progress >= 80 ? 'var(--success)' : ($project_progress >= 50 ? 'var(--primary)' : 'var(--warning)'); ?>; border-radius: 3px;"></div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- My Progress -->
                        <div class="card">
                            <div class="card-header">
                                <div class="card-title">My Progress</div>
                            </div>
                            <div class="card-body">
                                <?php 
                                $total_tasks = $stats['assigned_tasks'] ?? 0;
                                $completed = $stats['completed_tasks'] ?? 0;
                                $in_progress = $stats['in_progress_tasks'] ?? 0;
                                $todo = $total_tasks - $completed - $in_progress;
                                $completion_rate = $total_tasks > 0 ? round(($completed / $total_tasks) * 100) : 0;
                                ?>
                                <div style="text-align: center; margin-bottom: 20px;">
                                    <div style="font-size: 48px; font-weight: bold; color: var(--primary);"><?php echo $completion_rate; ?>%</div>
                                    <div style="color: var(--text-secondary);">Overall Completion Rate</div>
                                </div>
                                <div class="progress-item" style="margin-bottom: 10px;">
                                    <div style="display: flex; justify-content: space-between; margin-bottom: 5px;">
                                        <span>Completed</span>
                                        <span><?php echo $completed; ?> tasks</span>
                                    </div>
                                    <div class="progress-bar" style="height: 8px; background: var(--bg-tertiary); border-radius: 4px;">
                                        <div class="progress-fill" style="width: <?php echo $total_tasks > 0 ? ($completed / $total_tasks) * 100 : 0; ?>%; height: 100%; background: var(--success); border-radius: 4px;"></div>
                                    </div>
                                </div>
                                <div class="progress-item" style="margin-bottom: 10px;">
                                    <div style="display: flex; justify-content: space-between; margin-bottom: 5px;">
                                        <span>In Progress</span>
                                        <span><?php echo $in_progress; ?> tasks</span>
                                    </div>
                                    <div class="progress-bar" style="height: 8px; background: var(--bg-tertiary); border-radius: 4px;">
                                        <div class="progress-fill" style="width: <?php echo $total_tasks > 0 ? ($in_progress / $total_tasks) * 100 : 0; ?>%; height: 100%; background: var(--primary); border-radius: 4px;"></div>
                                    </div>
                                </div>
                                <div class="progress-item">
                                    <div style="display: flex; justify-content: space-between; margin-bottom: 5px;">
                                        <span>To Do</span>
                                        <span><?php echo $todo > 0 ? $todo : 0; ?> tasks</span>
                                    </div>
                                    <div class="progress-bar" style="height: 8px; background: var(--bg-tertiary); border-radius: 4px;">
                                        <div class="progress-fill" style="width: <?php echo $total_tasks > 0 && $todo > 0 ? ($todo / $total_tasks) * 100 : 0; ?>%; height: 100%; background: var(--warning); border-radius: 4px;"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Quick Actions -->
                        <div class="card">
                            <div class="card-header">
                                <div class="card-title">Quick Actions</div>
                            </div>
                            <div class="card-body">
                                <a href="kanban.php" class="btn btn-primary" style="width: 100%; margin-bottom: 10px;">
                                    <i class="fas fa-tasks"></i> View My Tasks
                                </a>
                                <a href="reports.php" class="btn btn-outline" style="width: 100%; margin-bottom: 10px;">
                                    <i class="fas fa-chart-pie"></i> My Work Summary
                                </a>
                                <a href="sos-employee.php" class="btn btn-outline" style="width: 100%;">
                                    <i class="fas fa-life-ring"></i> Request Help (SOS)
                                </a>
                            </div>
                        </div>
                        
                        <!-- Recent Activity -->
                        <div class="card">
                            <div class="card-header">
                                <div class="card-title">Recent Activity</div>
                            </div>
                            <div class="card-body">
                                <?php if (empty($recent_activities)): ?>
                                    <p style="color: var(--text-secondary); text-align: center;">No recent activity</p>
                                <?php else: ?>
                                    <?php foreach ($recent_activities as $activity): ?>
                                        <div class="activity-item" style="display: flex; align-items: flex-start; margin-bottom: 12px; padding-bottom: 12px; border-bottom: 1px solid var(--border-color);">
                                            <div class="activity-icon" style="width: 32px; height: 32px; border-radius: 50%; background: var(--primary); color: white; display: flex; align-items: center; justify-content: center; margin-right: 10px; flex-shrink: 0;">
                                                <i class="fas fa-<?php echo $activity['type'] == 'task_assigned' ? 'tasks' : ($activity['type'] == 'deadline' ? 'clock' : 'bell'); ?>"></i>
                                            </div>
                                            <div style="flex: 1;">
                                                <div style="font-weight: 500;"><?php echo htmlspecialchars($activity['title']); ?></div>
                                                <div style="font-size: 12px; color: var(--text-secondary);"><?php echo date('M d, h:i A', strtotime($activity['created_at'])); ?></div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                                <a href="notifications.php" class="btn btn-sm btn-outline" style="width: 100%;">View All Notifications</a>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
                
                
            </div>
        </div>
    </div>

</body>
</html>