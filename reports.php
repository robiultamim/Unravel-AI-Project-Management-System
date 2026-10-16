<?php
// reports.php
require_once 'config/database.php';
require_once 'config/session.php';
require_once 'includes/functions.php';

Session::redirectIfNotLoggedIn();

// Only managers and team leaders can access reports
if (!in_array($_SESSION['role'], ['manager', 'team_leader', 'admin'])) {
    header('Location: dashboard.php');
    exit;
}

$database = new Database();
$db = $database->getConnection();

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'];

// Get projects based on role
if ($user_role == 'admin') {
    // Admins see only projects where they are admin
    $projects_query = "SELECT project_id, project_name FROM projects WHERE admin_id = :user_id ORDER BY project_name";
    $projects_stmt = $db->prepare($projects_query);
    $projects_stmt->execute(['user_id' => $user_id]);
} elseif ($user_role == 'manager') {
    // Managers see their assigned projects
    $projects_query = "SELECT DISTINCT p.project_id, p.project_name 
                       FROM projects p 
                       LEFT JOIN project_members pm ON p.project_id = pm.project_id
                       WHERE p.manager_id = :user_id OR pm.user_id = :user_id
                       ORDER BY p.project_name";
    $projects_stmt = $db->prepare($projects_query);
    $projects_stmt->execute(['user_id' => $user_id]);
} else {
    // Team leaders see projects they are assigned to
    $projects_query = "SELECT DISTINCT p.project_id, p.project_name 
                       FROM projects p 
                       JOIN project_members pm ON p.project_id = pm.project_id
                       WHERE pm.user_id = :user_id
                       ORDER BY p.project_name";
    $projects_stmt = $db->prepare($projects_query);
    $projects_stmt->execute(['user_id' => $user_id]);
}
$projects = $projects_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get selected project filter
$selected_project = isset($_GET['project_id']) ? intval($_GET['project_id']) : 0;

// Get users for the report based on role and project
if ($user_role == 'admin') {
    if ($selected_project > 0) {
        $users_query = "SELECT u.user_id, u.first_name, u.last_name, u.email, u.role,
                               (SELECT COUNT(*) FROM tasks t WHERE t.assigned_to = u.user_id AND t.project_id = :project_id) as total_tasks,
                               (SELECT COUNT(*) FROM tasks t WHERE t.assigned_to = u.user_id AND t.project_id = :project_id AND t.status IN ('done', 'approved')) as completed_tasks,
                               (SELECT COUNT(*) FROM tasks t WHERE t.assigned_to = u.user_id AND t.project_id = :project_id AND t.status = 'in_progress') as in_progress_tasks,
                               (SELECT COUNT(*) FROM tasks t WHERE t.assigned_to = u.user_id AND t.project_id = :project_id AND t.status = 'todo') as todo_tasks,
                               (SELECT COUNT(*) FROM tasks t WHERE t.assigned_to = u.user_id AND t.project_id = :project_id AND t.status = 'blocked') as blocked_tasks
                        FROM users u
                        JOIN project_members pm ON u.user_id = pm.user_id
                        JOIN projects p ON pm.project_id = p.project_id
                        WHERE pm.project_id = :project_id AND u.is_active = 1 AND u.role = 'employee' AND p.admin_id = :admin_id
                        ORDER BY u.first_name, u.last_name";
        $users_stmt = $db->prepare($users_query);
        $users_stmt->execute(['project_id' => $selected_project, 'admin_id' => $user_id]);
    } else {
        $users_query = "SELECT DISTINCT u.user_id, u.first_name, u.last_name, u.email, u.role,
                               (SELECT COUNT(*) FROM tasks t WHERE t.assigned_to = u.user_id) as total_tasks,
                               (SELECT COUNT(*) FROM tasks t WHERE t.assigned_to = u.user_id AND t.status IN ('done', 'approved')) as completed_tasks,
                               (SELECT COUNT(*) FROM tasks t WHERE t.assigned_to = u.user_id AND t.status = 'in_progress') as in_progress_tasks,
                               (SELECT COUNT(*) FROM tasks t WHERE t.assigned_to = u.user_id AND t.status = 'todo') as todo_tasks,
                               (SELECT COUNT(*) FROM tasks t WHERE t.assigned_to = u.user_id AND t.status = 'blocked') as blocked_tasks
                        FROM users u
                        JOIN project_members pm ON u.user_id = pm.user_id
                        JOIN projects p ON pm.project_id = p.project_id
                        WHERE u.is_active = 1 AND u.role = 'employee' AND p.admin_id = :admin_id
                        ORDER BY u.first_name, u.last_name";
        $users_stmt = $db->prepare($users_query);
        $users_stmt->execute(['admin_id' => $user_id]);
    }
} elseif ($user_role == 'manager') {
    if ($selected_project > 0) {
        $users_query = "SELECT u.user_id, u.first_name, u.last_name, u.email, u.role,
                               (SELECT COUNT(*) FROM tasks t WHERE t.assigned_to = u.user_id AND t.project_id = :project_id) as total_tasks,
                               (SELECT COUNT(*) FROM tasks t WHERE t.assigned_to = u.user_id AND t.project_id = :project_id AND t.status IN ('done', 'approved')) as completed_tasks,
                               (SELECT COUNT(*) FROM tasks t WHERE t.assigned_to = u.user_id AND t.project_id = :project_id AND t.status = 'in_progress') as in_progress_tasks,
                               (SELECT COUNT(*) FROM tasks t WHERE t.assigned_to = u.user_id AND t.project_id = :project_id AND t.status = 'todo') as todo_tasks,
                               (SELECT COUNT(*) FROM tasks t WHERE t.assigned_to = u.user_id AND t.project_id = :project_id AND t.status = 'blocked') as blocked_tasks
                        FROM users u
                        JOIN project_members pm ON u.user_id = pm.user_id
                        WHERE pm.project_id = :project_id AND u.is_active = 1 AND u.role = 'employee'
                        ORDER BY u.first_name, u.last_name";
        $users_stmt = $db->prepare($users_query);
        $users_stmt->execute(['project_id' => $selected_project]);
    } else {
        $users_query = "SELECT DISTINCT u.user_id, u.first_name, u.last_name, u.email, u.role,
                               (SELECT COUNT(*) FROM tasks t WHERE t.assigned_to = u.user_id) as total_tasks,
                               (SELECT COUNT(*) FROM tasks t WHERE t.assigned_to = u.user_id AND t.status IN ('done', 'approved')) as completed_tasks,
                               (SELECT COUNT(*) FROM tasks t WHERE t.assigned_to = u.user_id AND t.status = 'in_progress') as in_progress_tasks,
                               (SELECT COUNT(*) FROM tasks t WHERE t.assigned_to = u.user_id AND t.status = 'todo') as todo_tasks,
                               (SELECT COUNT(*) FROM tasks t WHERE t.assigned_to = u.user_id AND t.status = 'blocked') as blocked_tasks
                        FROM users u
                        JOIN project_members pm ON u.user_id = pm.user_id
                        JOIN projects p ON pm.project_id = p.project_id
                        WHERE (p.manager_id = :user_id OR pm.project_id IN (SELECT project_id FROM project_members WHERE user_id = :user_id))
                        AND u.is_active = 1 AND u.role = 'employee'
                        ORDER BY u.first_name, u.last_name";
        $users_stmt = $db->prepare($users_query);
        $users_stmt->execute(['user_id' => $user_id]);
    }
} else {
    // Team leader - get team members
    if ($selected_project > 0) {
        $users_query = "SELECT u.user_id, u.first_name, u.last_name, u.email, u.role,
                               (SELECT COUNT(*) FROM tasks t WHERE t.assigned_to = u.user_id AND t.project_id = :project_id) as total_tasks,
                               (SELECT COUNT(*) FROM tasks t WHERE t.assigned_to = u.user_id AND t.project_id = :project_id AND t.status IN ('done', 'approved')) as completed_tasks,
                               (SELECT COUNT(*) FROM tasks t WHERE t.assigned_to = u.user_id AND t.project_id = :project_id AND t.status = 'in_progress') as in_progress_tasks,
                               (SELECT COUNT(*) FROM tasks t WHERE t.assigned_to = u.user_id AND t.project_id = :project_id AND t.status = 'todo') as todo_tasks,
                               (SELECT COUNT(*) FROM tasks t WHERE t.assigned_to = u.user_id AND t.project_id = :project_id AND t.status = 'blocked') as blocked_tasks
                        FROM users u
                        JOIN project_members pm ON u.user_id = pm.user_id
                        WHERE pm.project_id = :project_id AND u.is_active = 1 AND u.role = 'employee'
                        ORDER BY u.first_name, u.last_name";
        $users_stmt = $db->prepare($users_query);
        $users_stmt->execute(['project_id' => $selected_project]);
    } else {
        $users_query = "SELECT DISTINCT u.user_id, u.first_name, u.last_name, u.email, u.role,
                               (SELECT COUNT(*) FROM tasks t WHERE t.assigned_to = u.user_id) as total_tasks,
                               (SELECT COUNT(*) FROM tasks t WHERE t.assigned_to = u.user_id AND t.status IN ('done', 'approved')) as completed_tasks,
                               (SELECT COUNT(*) FROM tasks t WHERE t.assigned_to = u.user_id AND t.status = 'in_progress') as in_progress_tasks,
                               (SELECT COUNT(*) FROM tasks t WHERE t.assigned_to = u.user_id AND t.status = 'todo') as todo_tasks,
                               (SELECT COUNT(*) FROM tasks t WHERE t.assigned_to = u.user_id AND t.status = 'blocked') as blocked_tasks
                        FROM users u
                        JOIN project_members pm ON u.user_id = pm.user_id
                        WHERE pm.project_id IN (SELECT project_id FROM project_members WHERE user_id = :user_id)
                        AND u.is_active = 1 AND u.role = 'employee'
                        ORDER BY u.first_name, u.last_name";
        $users_stmt = $db->prepare($users_query);
        $users_stmt->execute(['user_id' => $user_id]);
    }
}
$users = $users_stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate summary statistics
$total_users = count($users);
$total_all_tasks = array_sum(array_column($users, 'total_tasks'));
$total_completed = array_sum(array_column($users, 'completed_tasks'));
$total_in_progress = array_sum(array_column($users, 'in_progress_tasks'));
$total_todo = array_sum(array_column($users, 'todo_tasks'));
$total_blocked = array_sum(array_column($users, 'blocked_tasks'));
$completion_rate = $total_all_tasks > 0 ? round(($total_completed / $total_all_tasks) * 100, 1) : 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - Unravel</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/dashboard.css">
    <link rel="stylesheet" href="assets/css/reports.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="app-container">
        <?php include 'includes/sidebar.php'; ?>
        
        <div class="main-content">
            <?php include 'includes/header.php'; ?>
            
            <div class="content-wrapper">
                <!-- Report Header with Filters -->
                <div class="report-header">
                    <div class="report-title-section">
                        <h1><i class="fas fa-chart-pie"></i> User Performance Reports</h1>
                        <p class="report-subtitle">View and export user performance data</p>
                    </div>
                    <div class="report-actions">
                        <form method="GET" class="filter-form">
                            <div class="filter-group">
                                <label for="project_id">Filter by Project:</label>
                                <select name="project_id" id="project_id" onchange="this.form.submit()">
                                    <option value="0">All Projects</option>
                                    <?php foreach ($projects as $project): ?>
                                        <option value="<?php echo $project['project_id']; ?>" <?php echo $selected_project == $project['project_id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($project['project_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </form>
                        <button type="button" class="btn btn-primary export-btn" onclick="window.print()">
                            <i class="fas fa-file-export"></i> Export Report
                        </button>
                    </div>
                </div>

                <!-- Report Canvas - This is the area that will be exported -->
                <div id="report-canvas" class="report-canvas">
                    <!-- Report Meta Info -->
                    <div class="report-meta print-only">
                        <h2>Unravel - User Performance Report</h2>
                        <p>Generated on: <?php echo date('F j, Y, g:i a'); ?></p>
                        <p>Generated by: <?php echo htmlspecialchars($_SESSION['first_name'] . ' ' . $_SESSION['last_name']); ?></p>
                        <?php if ($selected_project > 0): ?>
                            <?php 
                            $selected_project_name = '';
                            foreach ($projects as $p) {
                                if ($p['project_id'] == $selected_project) {
                                    $selected_project_name = $p['project_name'];
                                    break;
                                }
                            }
                            ?>
                            <p>Project: <?php echo htmlspecialchars($selected_project_name); ?></p>
                        <?php else: ?>
                            <p>Project: All Projects</p>
                        <?php endif; ?>
                    </div>

                    <!-- Summary Cards -->
                    <div class="summary-cards">
                        <div class="summary-card">
                            <div class="summary-icon users">
                                <i class="fas fa-users"></i>
                            </div>
                            <div class="summary-content">
                                <div class="summary-value"><?php echo $total_users; ?></div>
                                <div class="summary-label">Total Users</div>
                            </div>
                        </div>
                        <div class="summary-card">
                            <div class="summary-icon tasks">
                                <i class="fas fa-tasks"></i>
                            </div>
                            <div class="summary-content">
                                <div class="summary-value"><?php echo $total_all_tasks; ?></div>
                                <div class="summary-label">Total Tasks</div>
                            </div>
                        </div>
                        <div class="summary-card">
                            <div class="summary-icon completed">
                                <i class="fas fa-check-circle"></i>
                            </div>
                            <div class="summary-content">
                                <div class="summary-value"><?php echo $total_completed; ?></div>
                                <div class="summary-label">Completed</div>
                            </div>
                        </div>
                        <div class="summary-card">
                            <div class="summary-icon progress">
                                <i class="fas fa-spinner"></i>
                            </div>
                            <div class="summary-content">
                                <div class="summary-value"><?php echo $total_in_progress; ?></div>
                                <div class="summary-label">In Progress</div>
                            </div>
                        </div>
                        <div class="summary-card">
                            <div class="summary-icon rate">
                                <i class="fas fa-percentage"></i>
                            </div>
                            <div class="summary-content">
                                <div class="summary-value"><?php echo $completion_rate; ?>%</div>
                                <div class="summary-label">Completion Rate</div>
                            </div>
                        </div>
                    </div>

                    <!-- Status Overview -->
                    <div class="status-overview">
                        <h3><i class="fas fa-chart-bar"></i> Task Status Distribution</h3>
                        <div class="status-bars">
                            <div class="status-bar-item">
                                <div class="status-bar-header">
                                    <span class="status-name">Completed</span>
                                    <span class="status-count"><?php echo $total_completed; ?></span>
                                </div>
                                <div class="status-bar">
                                    <div class="status-bar-fill completed" style="width: <?php echo $total_all_tasks > 0 ? ($total_completed / $total_all_tasks) * 100 : 0; ?>%"></div>
                                </div>
                            </div>
                            <div class="status-bar-item">
                                <div class="status-bar-header">
                                    <span class="status-name">In Progress</span>
                                    <span class="status-count"><?php echo $total_in_progress; ?></span>
                                </div>
                                <div class="status-bar">
                                    <div class="status-bar-fill in-progress" style="width: <?php echo $total_all_tasks > 0 ? ($total_in_progress / $total_all_tasks) * 100 : 0; ?>%"></div>
                                </div>
                            </div>
                            <div class="status-bar-item">
                                <div class="status-bar-header">
                                    <span class="status-name">To Do</span>
                                    <span class="status-count"><?php echo $total_todo; ?></span>
                                </div>
                                <div class="status-bar">
                                    <div class="status-bar-fill todo" style="width: <?php echo $total_all_tasks > 0 ? ($total_todo / $total_all_tasks) * 100 : 0; ?>%"></div>
                                </div>
                            </div>
                            <div class="status-bar-item">
                                <div class="status-bar-header">
                                    <span class="status-name">Blocked</span>
                                    <span class="status-count"><?php echo $total_blocked; ?></span>
                                </div>
                                <div class="status-bar">
                                    <div class="status-bar-fill blocked" style="width: <?php echo $total_all_tasks > 0 ? ($total_blocked / $total_all_tasks) * 100 : 0; ?>%"></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- User Reports Table -->
                    <div class="reports-table-section">
                        <h3><i class="fas fa-users"></i> User Performance Details</h3>
                        <div class="table-container">
                            <table class="reports-table">
                                <thead>
                                    <tr>
                                        <th>User</th>
                                        <th>Role</th>
                                        <th>Total Tasks</th>
                                        <th>Completed</th>
                                        <th>In Progress</th>
                                        <th>To Do</th>
                                        <th>Blocked</th>
                                        <th>Completion Rate</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (count($users) > 0): ?>
                                        <?php foreach ($users as $user): ?>
                                            <?php 
                                            $user_completion_rate = $user['total_tasks'] > 0 
                                                ? round(($user['completed_tasks'] / $user['total_tasks']) * 100, 1) 
                                                : 0;
                                            ?>
                                            <tr>
                                                <td class="user-cell">
                                                    <div class="user-avatar-small">
                                                        <?php echo strtoupper(substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1)); ?>
                                                    </div>
                                                    <div class="user-info-cell">
                                                        <div class="user-name-cell"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></div>
                                                        <div class="user-email-cell"><?php echo htmlspecialchars($user['email']); ?></div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <span class="role-badge role-<?php echo $user['role']; ?>">
                                                        <?php echo ucfirst(str_replace('_', ' ', $user['role'])); ?>
                                                    </span>
                                                </td>
                                                <td class="text-center"><?php echo $user['total_tasks']; ?></td>
                                                <td class="text-center">
                                                    <span class="task-count completed"><?php echo $user['completed_tasks']; ?></span>
                                                </td>
                                                <td class="text-center">
                                                    <span class="task-count in-progress"><?php echo $user['in_progress_tasks']; ?></span>
                                                </td>
                                                <td class="text-center">
                                                    <span class="task-count todo"><?php echo $user['todo_tasks']; ?></span>
                                                </td>
                                                <td class="text-center">
                                                    <span class="task-count blocked"><?php echo $user['blocked_tasks']; ?></span>
                                                </td>
                                                <td class="text-center">
                                                    <div class="progress-cell">
                                                        <div class="mini-progress-bar">
                                                            <div class="mini-progress-fill" style="width: <?php echo $user_completion_rate; ?>%"></div>
                                                        </div>
                                                        <span class="progress-text"><?php echo $user_completion_rate; ?>%</span>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="8" class="no-data">
                                                <i class="fas fa-info-circle"></i>
                                                No users found for the selected criteria.
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Report Footer -->
                    <div class="report-footer print-only">
                        <p>This report was generated by Unravel Project Management System</p>
                        <p>Page 1 of 1</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
