<?php
// pages/tasks.php
require_once 'config/database.php';
require_once 'config/session.php';
require_once 'includes/functions.php';

Session::redirectIfNotLoggedIn();

$database = new Database();
$db = $database->getConnection();
$user_id = Session::getUserID();
$role = Session::getCurrentUserRole();

// Get filter parameters
$status = $_GET['status'] ?? 'all';
$priority = $_GET['priority'] ?? 'all';
$project_id = $_GET['project'] ?? 'all';

// Build query
$query = "SELECT t.*, p.project_name, 
                 u.first_name as assignee_first, u.last_name as assignee_last,
                 (SELECT COUNT(*) FROM sos_signals sr WHERE sr.task_id = t.task_id AND sr.status IN ('open', 'acknowledged')) as has_sos
          FROM tasks t
          LEFT JOIN projects p ON t.project_id = p.project_id
          LEFT JOIN users u ON t.assigned_to = u.user_id
          WHERE 1=1";
          
// Apply filters based on role
if ($role == 'employee') {
    $query .= " AND t.assigned_to = :user_id";
} elseif ($role == 'team_leader') {
    $query .= " AND (p.team_leader_id = :user_id)";
} elseif ($role == 'manager') {
    $query .= " AND EXISTS (SELECT 1 FROM projects p2 WHERE p2.project_id = t.project_id AND p2.manager_id = :user_id)";
} elseif ($role == 'admin') {
    // p.admin_id = :user_id"; 
    $query .=  " AND p.admin_id = :user_id";
}

if ($status != 'all') {
    $query .= " AND t.status = :status";
}

if ($priority != 'all') {
    $query .= " AND t.priority = :priority";
}

if ($project_id != 'all') {
    $query .= " AND t.project_id = :project_id";
}

$query .= " ORDER BY t.due_date ASC, t.priority DESC";

$stmt = $db->prepare($query);
$params = [];

if (in_array($role, ['employee', 'team_leader', 'manager', 'admin'])) {
    $params[':user_id'] = $user_id;
}

if ($status != 'all') {
    $params[':status'] = $status;
}

if ($priority != 'all') {
    $params[':priority'] = $priority;
}

if ($project_id != 'all') {
    $params[':project_id'] = $project_id;
}

$stmt->execute($params);
$tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get projects for filter
$projects_query = "SELECT * FROM projects WHERE status = 'active' AND admin_id = :user_id ORDER BY project_name";
$projects_stmt = $db->prepare($projects_query);
$projects_stmt->execute([':user_id' => $user_id]);
$projects = $projects_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get work logs for employee
$work_logs = [];
if ($role == 'employee') {
    $logs_query = "SELECT wl.*, t.title 
                   FROM work_logs wl
                   JOIN tasks t ON wl.task_id = t.task_id
                   WHERE wl.user_id = :user_id
                   ORDER BY wl.log_date DESC
                   LIMIT 10";
    $logs_stmt = $db->prepare($logs_query);
    $logs_stmt->execute([':user_id' => $user_id]);
    $work_logs = $logs_stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Unravel - My Tasks</title>

            <link rel="stylesheet" href="assets/css/dashboard.css">


    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/tasks.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="role-<?php echo $role; ?>">
    <div class="container">
        <?php include 'includes/sidebar.php'; ?>
        
        <div class="main-content">
            <?php include 'includes/header.php'; ?>
            
            <div class="tasks-container">
                <div class="tasks-header">
                    <div>
                        <?php if (in_array($role, ['admin', 'manager'])): ?>
                            <h2>Manage Tasks</h2>
                        <?php else: ?>
                            <h2>My Tasks</h2>
                        <?php endif; ?>
                    </div>
                    <div class="tasks-actions">
                        <a href="kanban.php" class="btn btn-primary">
                            <i class="fas fa-columns"></i> Kanban View
                        </a>
                        <a href="gantt.php" class="btn btn-outline">
                            <i class="fas fa-chart-bar"></i> Gantt View
                        </a>
                    </div>
                </div>
                
                <!-- Filters -->
                <div class="filters-card">
                    <form method="GET" action="" class="filters-form">
                        <div class="filter-row">
                            <div class="filter-group">
                                <label>Status:</label>
                                <select name="status" class="filter-select" onchange="this.form.submit()">
                                    <option value="all" <?php echo $status == 'all' ? 'selected' : ''; ?>>All Status</option>
                                    <option value="todo" <?php echo $status == 'todo' ? 'selected' : ''; ?>>To Do</option>
                                    <option value="in_progress" <?php echo $status == 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                                    <option value="done" <?php echo $status == 'done' ? 'selected' : ''; ?>>Done</option>
                                    <option value="approved" <?php echo $status == 'approved' ? 'selected' : ''; ?>>Approved</option>
                                    <option value="blocked" <?php echo $status == 'blocked' ? 'selected' : ''; ?>>Blocked</option>
                                </select>
                            </div>
                            
                            <div class="filter-group">
                                <label>Priority:</label>
                                <select name="priority" class="filter-select" onchange="this.form.submit()">
                                    <option value="all" <?php echo $priority == 'all' ? 'selected' : ''; ?>>All Priorities</option>
                                    <option value="low" <?php echo $priority == 'low' ? 'selected' : ''; ?>>Low</option>
                                    <option value="medium" <?php echo $priority == 'medium' ? 'selected' : ''; ?>>Medium</option>
                                    <option value="high" <?php echo $priority == 'high' ? 'selected' : ''; ?>>High</option>
                                    <option value="critical" <?php echo $priority == 'critical' ? 'selected' : ''; ?>>Critical</option>
                                </select>
                            </div>
                            
                            <div class="filter-group">
                                <label>Project:</label>
                                <select name="project" class="filter-select" onchange="this.form.submit()">
                                    <option value="all" <?php echo $project_id == 'all' ? 'selected' : ''; ?>>All Projects</option>
                                    <?php foreach ($projects as $project): ?>
                                        <option value="<?php echo $project['project_id']; ?>" 
                                                <?php echo $project_id == $project['project_id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($project['project_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <button type="button" class="btn btn-outline" onclick="window.location.href='tasks.php';">
                                <i class="fas fa-redo"></i> Reset
                            </button>
                        </div>
                    </form>
                </div>
                
                <div class="tasks-grid">
                    <!-- Tasks List -->
                    <div class="tasks-list-section">
                        <div class="section-header">
                            <h3>Tasks (<?php echo count($tasks); ?>)</h3>

                        </div>
                        
                        <div class="tasks-table-container">
                            <table class="tasks-table">
                                <thead>
                                    <tr>
                                        <th>Task Name</th>
                                        <th>Project</th>
                                        <th>Status</th>
                                        <th>Priority</th>
                                        <th>Due Date</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($tasks)): ?>
                                        <tr>
                                            <td colspan="6" class="text-center">No tasks found</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($tasks as $task): ?>
                                            <tr class="task-row <?php echo $task['has_sos'] > 0 ? 'has-sos' : ''; ?>">
                                                <td>
                                                    <div class="task-name">
                                                        <strong><?php echo htmlspecialchars($task['title']); ?></strong>
                                                        <?php if ($task['has_sos'] > 0): ?>
                                                            <span class="sos-indicator" title="Help Requested">
                                                                <i class="fas fa-life-ring"></i>
                                                            </span>
                                                        <?php endif; ?>
                                                    </div>
                                                    <?php if (!empty($task['description'])): ?>
                                                        <div class="task-desc">
                                                            <?php echo htmlspecialchars(substr($task['description'], 0, 100)); ?>
                                                            <?php if (strlen($task['description']) > 100): ?>...<?php endif; ?>
                                                        </div>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo htmlspecialchars($task['project_name']); ?></td>
                                                <td>
                                                    <span class="task-status status-<?php echo $task['status']; ?>">
                                                        <?php echo ucfirst(str_replace('_', ' ', $task['status'])); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="task-priority priority-<?php echo $task['priority']; ?>">
                                                        <?php echo ucfirst($task['priority']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <div class="due-date <?php echo (strtotime($task['due_date']) < time() && !in_array($task['status'], ['done', 'approved'])) ? 'overdue' : ''; ?>">
                                                        <?php echo date('M d, Y', strtotime($task['due_date'])); ?>
                                                        <?php if (strtotime($task['due_date']) < time() && !in_array($task['status'], ['done', 'approved'])): ?>
                                                            <span class="overdue-badge">Overdue</span>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="task-actions">
                                                        <?php if ($role == 'employee' && $task['status'] == 'in_progress' && $task['has_sos'] == 0): ?>

                                                            <a href="sos-employee.php?task_id=<?php echo $task['task_id']; ?>">
                                                                <button class="btn btn-sm btn-warning sos-btn" 
                                                                        title="Request Help">
                                                                    <i class="fas fa-life-ring"></i> SOS
                                                                </button>
                                                            </a>
                                                        <?php endif; ?>
                                                        
                                                        
                                                        <button class="btn btn-sm btn-outline breakdown-btn" 
                                                                data-task-id="<?php echo $task['task_id']; ?>"
                                                                title="AI Task Breakdown">
                                                            <i class="fas fa-sitemap"></i>
                                                        </button>
                                                        
                                                        <?php if (in_array($role, ['admin', 'manager', 'team_leader'])): ?>
                                                            <button class="btn btn-sm btn-outline edit-task-btn" 
                                                                    data-task-id="<?php echo $task['task_id']; ?>"
                                                                    title="Edit">
                                                                <i class="fas fa-edit"></i>
                                                            </button>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    
                    <!-- Right sidebar with stats and logs -->
                    <div class="tasks-sidebar">
                        <!-- Task Stats -->
                        <div class="stats-card">
                            <h4>Task Statistics</h4>
                            <div class="stats-grid">
                                <div class="stat-item">
                                    <div class="stat-value"><?php echo count(array_filter($tasks, fn($t) => $t['status'] == 'todo')); ?></div>
                                    <div class="stat-label">To Do</div>
                                </div>
                                <div class="stat-item">
                                    <div class="stat-value"><?php echo count(array_filter($tasks, fn($t) => $t['status'] == 'in_progress')); ?></div>
                                    <div class="stat-label">In Progress</div>
                                </div>
                                <div class="stat-item">
                                    <div class="stat-value"><?php echo count(array_filter($tasks, fn($t) => $t['status'] == 'done')); ?></div>
                                    <div class="stat-label">Done</div>
                                </div>
                                <div class="stat-item">
                                    <div class="stat-value"><?php echo count(array_filter($tasks, fn($t) => strtotime($t['due_date']) < time() && !in_array($t['status'], ['done', 'approved']))); ?></div>
                                    <div class="stat-label">Overdue</div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Quick Actions -->
                        <div class="actions-card">
                            <h4>Quick Actions</h4>
                            <div class="actions-list">
                                <?php if (in_array($role, ['admin', 'manager', 'team_leader'])): ?>
                                    <button class="btn btn-primary btn-block" onclick="window.location.href='kanban.php#createTaskBtn'">
                                        <i class="fas fa-plus"></i> Create New Task
                                    </button>
                                <?php endif; ?>
                                
                                
                                <button class="btn btn-outline btn-block" onclick="printTaskList()">
                                    <i class="fas fa-print"></i> Print Task List
                                </button>
                            </div>
                        </div>
                        
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Breakdown Modal -->
    <div id="breakdownModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:9999; justify-content:center; align-items:center;">
        <div style="background:white; border-radius:12px; max-width:600px; width:90%; max-height:80vh; overflow-y:auto; box-shadow:0 10px 40px rgba(0,0,0,0.3);">
            <div style="padding:20px; border-bottom:1px solid #eee; display:flex; justify-content:space-between; align-items:center;">
                <h3 style="margin:0; color:#2c3e50;"><i class="fas fa-sitemap" style="color:#4361ee;"></i> AI Task Breakdown</h3>
                <button id="closeBreakdownModal" style="background:none; border:none; font-size:24px; cursor:pointer; color:#6c757d;">&times;</button>
            </div>
            <div id="breakdownContent" style="padding:25px;">
                <div style="text-align:center; padding:40px;">
                    <i class="fas fa-spinner fa-spin" style="font-size:32px; color:#4361ee;"></i>
                    <p style="margin-top:15px; color:#6c757d;">Generating breakdown...</p>
                </div>
            </div>
        </div>
    </div>

    <script>
    function printTaskList() {
        window.print();
    }
    
    // Breakdown Modal
    document.querySelectorAll('.breakdown-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var taskId = this.getAttribute('data-task-id');
            var modal = document.getElementById('breakdownModal');
            var content = document.getElementById('breakdownContent');
            
            // Show modal with loading
            modal.style.display = 'flex';
            content.innerHTML = '<div style="text-align:center; padding:40px;"><i class="fas fa-spinner fa-spin" style="font-size:32px; color:#4361ee;"></i><p style="margin-top:15px; color:#6c757d;">Generating AI breakdown...</p></div>';
            
            // Fetch breakdown
            fetch('ajax/generate_breakdown.php?task_id=' + taskId)
                .then(function(response) { return response.json(); })
                .then(function(data) {
                    if (data.success) {
                        content.innerHTML = 
                            '<div style="margin-bottom:20px;">' +
                            '<h4 style="color:#2c3e50; margin:0 0 5px 0;">' + data.task_title + '</h4>' +
                            (data.task_description ? '<p style="color:#6c757d; font-size:14px; margin:0;">' + data.task_description + '</p>' : '') +
                            '</div>' +
                            '<div style="background:#f8f9fa; border-radius:8px; padding:20px; border-left:4px solid #4361ee;">' +
                            '<h5 style="margin:0 0 15px 0; color:#4361ee;"><i class="fas fa-robot"></i> AI Breakdown</h5>' +
                            '<div style="white-space:pre-wrap; line-height:1.8; color:#2c3e50;">' + data.breakdown + '</div>' +
                            '</div>';
                    } else {
                        content.innerHTML = '<div style="text-align:center; padding:40px; color:#dc3545;"><i class="fas fa-exclamation-circle" style="font-size:32px;"></i><p style="margin-top:15px;">' + (data.message || 'Failed to generate breakdown') + '</p></div>';
                    }
                })
                .catch(function(error) {
                    content.innerHTML = '<div style="text-align:center; padding:40px; color:#dc3545;"><i class="fas fa-exclamation-circle" style="font-size:32px;"></i><p style="margin-top:15px;">Error connecting to server</p></div>';
                });
        });
    });
    
    // Close modal
    document.getElementById('closeBreakdownModal').addEventListener('click', function() {
        document.getElementById('breakdownModal').style.display = 'none';
    });
    
    document.getElementById('breakdownModal').addEventListener('click', function(e) {
        if (e.target === this) {
            this.style.display = 'none';
        }
    });
    </script>
</body>
</html>