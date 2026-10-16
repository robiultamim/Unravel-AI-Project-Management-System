<?php
// pages/gantt.php
require_once 'config/database.php';
require_once 'config/session.php';
require_once 'includes/functions.php';

Session::redirectIfNotLoggedIn();

$database = new Database();
$db = $database->getConnection();
$user_id = Session::getUserID();
$role = Session::getCurrentUserRole();


$query = "SELECT p.* FROM projects p";

// Get projects and tasks for Gantt chart based on role
if ($role == 'admin') {
    $query .= " WHERE p.admin_id = :user_id";
}elseif ($role == 'manager') {
    $query .= " WHERE p.manager_id = :user_id";
} elseif ($role == 'team_leader') {
    // Team leader can see projects they're involved in
    $query .= " WHERE p.team_leader_id = :user_id";
} elseif ($role == 'employee') {
    $query .= " WHERE EXISTS (SELECT 1 FROM tasks t WHERE t.project_id = p.project_id AND t.assigned_to = :user_id)";
}

$query .= " ORDER BY p.start_date ASC";

$stmt = $db->prepare($query);
if (in_array($role, ['admin', 'manager', 'team_leader', 'employee'])) {
    $stmt->bindParam(':user_id', $user_id);
}
$stmt->execute();
$projects = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get tasks with dependencies
$tasks_query = "SELECT t.*, p.project_name, 
                       u.first_name as assignee_first, u.last_name as assignee_last,
                       td.depends_on_task_id,
                       pt.parent_task_name,
                       t.title as task_name
                FROM tasks t
                LEFT JOIN projects p ON t.project_id = p.project_id
                LEFT JOIN users u ON t.assigned_to = u.user_id
                LEFT JOIN task_dependencies td ON t.task_id = td.task_id
                LEFT JOIN parent_task pt ON t.parent_task_id = pt.parent_task_id";
                
if (in_array($role, ['admin', 'manager', 'team_leader', 'employee'])) {
    $tasks_query .= " WHERE EXISTS (SELECT 1 FROM projects p2 WHERE p2.project_id = t.project_id";
    if ($role == 'admin') {
        $tasks_query .= " AND p2.admin_id = :user_id";
    } elseif ($role == 'manager') {
        $tasks_query .= " AND p2.manager_id = :user_id";
    } elseif ($role == 'team_leader') {
        $tasks_query .= " AND p2.team_leader_id = :user_id";
    } elseif ($role == 'employee') {
        $tasks_query .= " AND EXISTS (SELECT 1 FROM tasks t2 WHERE t2.project_id = p2.project_id AND t2.assigned_to = :user_id)";
    }
    $tasks_query .= ")";
}

$tasks_query .= " ORDER BY t.start_date ASC";

$tasks_stmt = $db->prepare($tasks_query);
if (in_array($role, ['admin', 'manager', 'team_leader', 'employee'])) {
    $tasks_stmt->bindParam(':user_id', $user_id);
}
$tasks_stmt->execute();
$tasks = $tasks_stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate timeline dates (next 30 days)
$start_date = date('Y-m-d');
$end_date = date('Y-m-d', strtotime('+30 days'));
$dates = [];
for ($i = 0; $i <= 30; $i++) {
    $date = date('Y-m-d', strtotime("+$i days"));
    $dates[] = [
        'date' => $date,
        'day' => date('d', strtotime($date)),
        'month' => date('M', strtotime($date)),
        'is_weekend' => in_array(date('w', strtotime($date)), [0, 6]),
        'is_today' => $date == date('Y-m-d')
    ];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Unravel - Gantt Chart</title>
    
            <link rel="stylesheet" href="assets/css/dashboard.css">

    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/gantt.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="role-<?php echo $role; ?>">
    <div class="container">
        <?php include 'includes/sidebar.php'; ?>
        
        <div class="main-content">
            <?php include 'includes/header.php'; ?>
            
            <div class="gantt-container">
                <div class="gantt-header">
                    <div>
                        <h1>Gantt Chart</h1>
                        <p class="subtitle">Visual timeline of tasks and dependencies</p>
                    </div>
                    <div class="gantt-controls">
                        <button id="zoomInBtn" title="Zoom In"><i class="fas fa-search-plus"></i></button>
                        <button id="zoomOutBtn" title="Zoom Out"><i class="fas fa-search-minus"></i></button>
                    </div>
                </div>
                
                <div class="gantt-timeline" id="ganttTimeline">
                    <div class="timeline-header">
                        <div class="timeline-cell">Task / Project</div>
                        <?php foreach ($dates as $date): ?>
                            <div class="timeline-cell">
                                <?php echo $date['month']; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <div class="timeline-days">
                        <div class="day-cell"></div>
                        <?php foreach ($dates as $date): ?>
                            <div class="day-cell <?php echo $date['is_weekend'] ? 'weekend' : ''; ?> <?php echo $date['is_today'] ? 'today' : ''; ?>">
                                <?php echo $date['day']; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <div class="gantt-body">
                        <?php foreach ($projects as $project): ?>
                            <!-- Project Row -->
                            <div class="gantt-task-row" data-project-id="<?php echo $project['project_id']; ?>">
                                <div class="task-label project-label">
                                    <i class="fas fa-folder" style="margin-right: 8px; color: var(--primary);"></i>
                                    <?php echo htmlspecialchars($project['project_name']); ?>
                                </div>
                                <div class="task-bars"></div>
                            </div>
                            
                            <?php 
                            // Get parent tasks and regular tasks for this project
                            // Debug: Check if tasks exist
                            $all_project_tasks = array_filter($tasks, function($task) use ($project) {
                                return $task['project_id'] == $project['project_id'];
                            });
                            
                            $parent_tasks = array_filter($tasks, function($task) use ($project) {
                                return $task['project_id'] == $project['project_id'] && 
                                       (empty($task['parent_task_id']) || $task['parent_task_id'] == null || $task['parent_task_id'] == 0);
                            });
                            
                            $child_tasks = array_filter($tasks, function($task) use ($project) {
                                return $task['project_id'] == $project['project_id'] && 
                                       !empty($task['parent_task_id']) && $task['parent_task_id'] != null && $task['parent_task_id'] != 0;
                            });
                            
                            // Display parent tasks
                            foreach ($parent_tasks as $task):
                                $start_offset = floor((strtotime($task['start_date']) - strtotime($start_date)) / (60 * 60 * 24));
                                $duration = floor((strtotime($task['due_date']) - strtotime($task['start_date'])) / (60 * 60 * 24)) + 1;
                            ?>
                            <!-- Parent Task Row -->
                            <div class="gantt-task-row" data-task-id="<?php echo $task['task_id']; ?>">
                                <div class="task-label">
                                    <i class="fas fa-tasks" style="margin-right: 8px; margin-left: 20px; color: var(--secondary);"></i>
                                    <?php echo htmlspecialchars($task['task_name']); ?>
                                </div>
                                <div class="task-bars">
                                    <?php 
                                    // Show task bar regardless of date range for debugging
                                    $top_position = 15;
                                    $width = max(40, $duration * 40); // Minimum 40px
                                    $left_position = max(0, $start_offset * 40);
                                    
                                    $bar_class = 'primary';
                                    if ($task['status'] == 'done' || $task['status'] == 'approved') {
                                        $bar_class = 'success';
                                    } elseif ($task['status'] == 'blocked') {
                                        $bar_class = 'danger';
                                    } elseif ($task['status'] == 'in_progress') {
                                        $bar_class = 'warning';
                                    }
                                    
                                    $progress = 0;
                                    if ($task['actual_hours'] > 0 && $task['estimated_hours'] > 0) {
                                        $progress = min(100, ($task['actual_hours'] / $task['estimated_hours']) * 100);
                                    }
                                    ?>
                                    <div class="task-bar <?php echo $bar_class; ?>" 
                                         style="left: <?php echo $left_position; ?>px; width: <?php echo $width; ?>px; top: <?php echo $top_position; ?>px;"
                                         data-task-id="<?php echo $task['task_id']; ?>"
                                         title="<?php echo htmlspecialchars($task['task_name']); ?>">
                                        <div class="bar-progress" style="width: <?php echo $progress; ?>%;"></div>
                                        <span style="position: relative; z-index: 1;">
                                            <?php echo htmlspecialchars($task['task_name']); ?>
                                        </span>
                                    </div>
                                    <?php 
                                    if ($task['estimated_hours'] == 0 || $duration == 0): ?>
                                        <div class="task-milestone" 
                                             style="left: <?php echo $left_position + 14; ?>px; top: <?php echo $top_position + 24; ?>px;"
                                             data-task-id="<?php echo $task['task_id']; ?>"></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                            
                            <?php 
                            // Display child tasks
                            foreach ($child_tasks as $task):
                                $start_offset = floor((strtotime($task['start_date']) - strtotime($start_date)) / (60 * 60 * 24));
                                $duration = floor((strtotime($task['due_date']) - strtotime($task['start_date'])) / (60 * 60 * 24)) + 1;
                                
                                // Parent task name is already available from the JOIN
                                $parent_task_name = !empty($task['parent_task_name']) ? $task['parent_task_name'] : '';
                            ?>
                            <!-- Child Task Row -->
                            <div class="gantt-task-row" data-task-id="<?php echo $task['task_id']; ?>">
                                <div class="task-label">
                                    <span style="margin-left: 40px;">
                                        <?php echo htmlspecialchars($parent_task_name . ' - ' . $task['task_name']); ?>
                                    </span>
                                </div>
                                <div class="task-bars">
                                    <?php 
                                    // Show task bar regardless of date range for debugging
                                    $top_position = 15;
                                    $width = max(40, $duration * 40); // Minimum 40px
                                    $left_position = max(0, $start_offset * 40);
                                    
                                    $bar_class = 'primary';
                                    if ($task['status'] == 'done' || $task['status'] == 'approved') {
                                        $bar_class = 'success';
                                    } elseif ($task['status'] == 'blocked') {
                                        $bar_class = 'danger';
                                    } elseif ($task['status'] == 'in_progress') {
                                        $bar_class = 'warning';
                                    }
                                    
                                    $progress = 0;
                                    if ($task['actual_hours'] > 0 && $task['estimated_hours'] > 0) {
                                        $progress = min(100, ($task['actual_hours'] / $task['estimated_hours']) * 100);
                                    }
                                    ?>
                                    <div class="task-bar <?php echo $bar_class; ?>" 
                                         style="left: <?php echo $left_position; ?>px; width: <?php echo $width; ?>px; top: <?php echo $top_position; ?>px;"
                                         data-task-id="<?php echo $task['task_id']; ?>"
                                         title="<?php echo htmlspecialchars($task['task_name']); ?>">
                                        <div class="bar-progress" style="width: <?php echo $progress; ?>%;"></div>
                                        <span style="position: relative; z-index: 1;">
                                            <?php echo htmlspecialchars($task['task_name']); ?>
                                        </span>
                                    </div>

                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <div class="gantt-legend">
                    <div class="legend-item">
                        <div class="legend-color primary"></div>
                        <span>To Do / Planned</span>
                    </div>
                    <div class="legend-item">
                        <div class="legend-color warning"></div>
                        <span>In Progress</span>
                    </div>
                    <div class="legend-item">
                        <div class="legend-color success"></div>
                        <span>Done / Approved</span>
                    </div>
                    <div class="legend-item">
                        <div class="legend-color danger"></div>
                        <span>Blocked</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    

</body>
</html>