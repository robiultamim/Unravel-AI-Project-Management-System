
<?php
// pages/kanban.php
require_once 'config/database.php';
require_once 'config/session.php';
require_once 'includes/functions.php';

Session::redirectIfNotLoggedIn();

$database = new Database();
$db = $database->getConnection();
$user_id = Session::getUserID();
$role = Session::getCurrentUserRole();

// Get tasks for kanban board
$query = "SELECT t.*, p.project_name, 
                 u.first_name as assignee_first, u.last_name as assignee_last,
                 (SELECT COUNT(*) FROM sos_signals sr WHERE sr.task_id = t.task_id AND sr.status IN ('open', 'acknowledged')) as has_sos
          FROM tasks t
          LEFT JOIN projects p ON t.project_id = p.project_id
          LEFT JOIN users u ON t.assigned_to = u.user_id";
          
// Filter based on role
if ($role == 'employee') {
    $query .= " WHERE t.assigned_to = :user_id";
} elseif ($role == 'team_leader') {
    // Team leader can see tasks of their team members
    $query .= " WHERE EXISTS (SELECT 1 FROM projects p2 WHERE p2.project_id = t.project_id AND p2.team_leader_id = :user_id)";
} elseif ($role == 'manager') {
    // Manager can see tasks of their projects
    $query .= " WHERE EXISTS (SELECT 1 FROM projects p2 WHERE p2.project_id = t.project_id AND p2.manager_id = :user_id)";
}elseif ($role == 'admin') {
    // admin can see their tasks
    $query .= " WHERE EXISTS (SELECT 1 FROM projects p2 WHERE p2.project_id = t.project_id AND p2.admin_id = :user_id)";
}

$query .= " ORDER BY t.priority DESC, t.due_date ASC";

$stmt = $db->prepare($query);
if (in_array($role, ['employee', 'team_leader', 'manager', 'admin'])) {
    $stmt->bindParam(':user_id', $user_id);
}
$stmt->execute();
$tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get AI recommendations for unassigned tasks
$ai_recommendations = [];
if (in_array($role, ['admin', 'manager', 'team_leader'])) {
    $unassigned_tasks = array_filter($tasks, function($task) {
        return empty($task['assigned_to']);
    });
    
    foreach ($unassigned_tasks as $task) {
        $recommendation = get_ai_recommendation($task['task_id'], $db);
        if ($recommendation) {
            $ai_recommendations[$task['task_id']] = $recommendation;
        }
    }
}

// Get projects for task creation filter query based on role (admin, manager, team_leader)
if (in_array($role, ['admin', 'manager', 'team_leader'])) {
    $projects_query = "SELECT * FROM projects WHERE status = 'active' AND 
                       (admin_id = :user_id OR manager_id = :user_id OR team_leader_id = :user_id) 
                       ORDER BY project_name";
    $projects_stmt = $db->prepare($projects_query);
    $projects_stmt->bindParam(':user_id', $user_id);
    $projects_stmt->execute();
    $projects = $projects_stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    // For employees, get projects they are members of
    $projects_query = "SELECT DISTINCT p.* FROM projects p 
                       INNER JOIN project_members pm ON p.project_id = pm.project_id 
                       WHERE p.status = 'active' AND pm.user_id = :user_id 
                       ORDER BY p.project_name";
    $projects_stmt = $db->prepare($projects_query);
    $projects_stmt->bindParam(':user_id', $user_id);
    $projects_stmt->execute();
    $projects = $projects_stmt->fetchAll(PDO::FETCH_ASSOC);
}


// Users will be loaded dynamically based on selected project via AJAX

// Get skills for task creation
$skills_query = "SELECT * FROM skills ORDER BY skill_name";
$skills = $db->query($skills_query)->fetchAll(PDO::FETCH_ASSOC);

// Get parent tasks grouped by project
$parent_tasks_query = "SELECT pt.parent_task_id, pt.parent_task_name, pt.project_id 
                        FROM parent_task pt 
                        INNER JOIN projects p ON pt.project_id = p.project_id 
                        WHERE p.status = 'active' 
                        ORDER BY pt.project_id, pt.parent_task_name";
$parent_tasks = $db->query($parent_tasks_query)->fetchAll(PDO::FETCH_ASSOC);

// Group parent tasks by project for easier JavaScript access
$parent_tasks_by_project = [];
foreach ($parent_tasks as $parent_task) {
    $parent_tasks_by_project[$parent_task['project_id']][] = $parent_task;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Unravel - Kanban Board</title>

        <link rel="stylesheet" href="assets/css/dashboard.css">

    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/kanban.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="role-<?php echo $role; ?>">
    <div class="container">
        <?php include 'includes/sidebar.php'; ?>
        
        <div class="main-content">
            <?php include 'includes/header.php'; ?>
            
            <div class="kanban-container">
                <div class="kanban-header">
                    <div>
                        <h1>Kanban Board</h1>
                        <p class="subtitle">Drag and drop tasks between columns to update their status</p>
                    </div>
                    <div class="kanban-actions">
                        <div class="task-filter">
                            <div class="filter-group">
                                <label>Project:</label>
                                <select id="project-filter" class="filter-select">
                                    <option value="">All Projects</option>
                                    <?php foreach ($projects as $project): ?>
                                        <option value="<?php echo $project['project_id']; ?>">
                                            <?php echo htmlspecialchars($project['project_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <!-- <div class="filter-group">
                                <label>Priority:</label>
                                <select id="priority-filter" class="filter-select">
                                    <option value="">All Priorities</option>
                                    <option value="low">Low</option>
                                    <option value="medium">Medium</option>
                                    <option value="high">High</option>
                                    <option value="critical">Critical</option>
                                </select>
                            </div> -->
                        </div>
                    </div>
                </div>
                
                <div class="kanban-board" id="kanbanBoard">
                    <!-- To Do Column -->
                    <div class="kanban-column" data-status="todo">
                        <div class="column-header">
                            <div class="column-title column-todo">
                                <div class="column-icon"><i class="fas fa-clipboard-list"></i></div>
                                To Do
                            </div>
                            <div class="column-actions">
                                <button class="column-action-btn" title="Add task">
                                    <i class="fas fa-plus"></i>
                                </button>
                            </div>
                        </div>
                        <div class="task-list" id="todo-list">
                            <?php foreach ($tasks as $task): 
                                if ($task['status'] == 'todo'): ?>
                                    <div class="task-card" data-task-id="<?php echo $task['task_id']; ?>" 
                                         data-project-id="<?php echo $task['project_id']; ?>"
                                         data-status="todo" draggable="true">
                                        <div class="task-header">
                                            <div class="task-title"><?php echo htmlspecialchars($task['title']); ?></div>
                                            <div class="task-actions">
                                                <button class="task-action-btn" title="Edit">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button class="task-action-btn" title="Breakdown">
                                                    <i class="fas fa-sitemap"></i>
                                                </button>
                                            </div>
                                        </div>
                                        
                                        <?php if (!empty($task['description'])): ?>
                                            <div class="task-description">
                                                <?php echo htmlspecialchars(substr($task['description'], 0, 100)); ?>
                                                <?php if (strlen($task['description']) > 100): ?>...<?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <div class="task-meta">
                                            <span><?php echo $task['project_name']; ?></span>
                                            <span class="task-priority priority-<?php echo $task['priority']; ?>">
                                                <?php echo ucfirst($task['priority']); ?>
                                            </span>
                                        </div>
                                        
                                        <?php if (isset($ai_recommendations[$task['task_id']])): ?>
                                            <div class="ai-recommendation">
                                                <div class="recommendation-label">
                                                    <i class="fas fa-robot"></i> AI Recommended
                                                </div>
                                                <div class="recommendation-content">
                                                    <div class="assignee-avatar">
                                                        <?php echo strtoupper(substr($ai_recommendations[$task['task_id']]['first_name'], 0, 1) . substr($ai_recommendations[$task['task_id']]['last_name'], 0, 1)); ?>
                                                    </div>
                                                    <span><?php echo htmlspecialchars($ai_recommendations[$task['task_id']]['first_name'] . ' ' . $ai_recommendations[$task['task_id']]['last_name']); ?></span>
                                                    <button class="assign-btn" 
                                                            data-task-id="<?php echo $task['task_id']; ?>"
                                                            data-user-id="<?php echo $ai_recommendations[$task['task_id']]['user_id']; ?>">
                                                        Assign
                                                    </button>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <div class="task-footer">
                                            <?php if ($task['assigned_to']): ?>
                                                <div class="task-assignee">
                                                    <div class="assignee-avatar">
                                                        <?php echo strtoupper(substr($task['assignee_first'], 0, 1) . substr($task['assignee_last'], 0, 1)); ?>
                                                    </div>
                                                    <span><?php echo htmlspecialchars($task['assignee_first'] . ' ' . $task['assignee_last']); ?></span>
                                                </div>
                                            <?php else: ?>
                                                <div class="task-assignee">
                                                    <span style="color: var(--gray); font-style: italic;">Unassigned</span>
                                                </div>
                                            <?php endif; ?>
                                            
                                            <div class="task-due <?php echo (strtotime($task['due_date']) < time() && $task['status'] != 'done' && $task['status'] != 'approved') ? 'overdue' : ''; ?>">
                                                <?php echo date('M d', strtotime($task['due_date'])); ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endif; 
                            endforeach; ?>
                        </div>
                    </div>
                    
                    <!-- In Progress Column -->
                    <div class="kanban-column" data-status="in_progress">
                        <div class="column-header">
                            <div class="column-title column-in_progress">
                                <div class="column-icon"><i class="fas fa-spinner"></i></div>
                                In Progress
                            </div>
                            <div class="task-count"><?php echo count(array_filter($tasks, fn($t) => $t['status'] == 'in_progress')); ?></div>
                        </div>
                        <div class="task-list" id="progress-list">
                            <?php foreach ($tasks as $task): 
                                if ($task['status'] == 'in_progress'): ?>
                                    <div class="task-card <?php echo $task['has_sos'] > 0 ? 'sos' : ''; ?>" 
                                         data-task-id="<?php echo $task['task_id']; ?>" 
                                         data-project-id="<?php echo $task['project_id']; ?>"
                                         data-status="in_progress" draggable="true">
                                        <div class="task-header">
                                            <div class="task-title"><?php echo htmlspecialchars($task['title']); ?></div>
                                            <div class="task-actions">
                                                <button class="task-action-btn" title="Edit">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <?php if ($task['has_sos'] > 0): ?>
                                                    <span class="badge badge-danger">SOS</span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        
                                        <div class="task-meta">
                                            <span><?php echo $task['project_name']; ?></span>
                                            <span class="task-priority priority-<?php echo $task['priority']; ?>">
                                                <?php echo ucfirst($task['priority']); ?>
                                            </span>
                                        </div>
                                        
                                        <div class="task-footer">
                                            <?php if ($task['assigned_to']): ?>
                                            <div class="task-assignee">
                                                <div class="assignee-avatar">
                                                    <?php echo strtoupper(substr($task['assignee_first'], 0, 1) . substr($task['assignee_last'], 0, 1)); ?>
                                                </div>
                                                <span><?php echo htmlspecialchars($task['assignee_first'] . ' ' . $task['assignee_last']); ?></span>
                                            </div>
                                            <?php else: ?>
                                            <div class="task-assignee">
                                                <span style="color: var(--gray); font-style: italic;">Unassigned</span>
                                            </div>
                                            <?php endif; ?>
                                            
                                            <div class="task-due <?php echo (strtotime($task['due_date']) < time()) ? 'overdue' : ''; ?>">
                                                <?php echo date('M d', strtotime($task['due_date'])); ?>
                                            </div>
                                        </div>
                                        
                                        <?php if ($role == 'employee' && !$task['has_sos']): ?>
                                            <a href="sos-employee.php?task_id=<?php echo $task['task_id']; ?>" style="text-decoration: none;">
                                                <button class="sos-button">
                                                    <i class="fas fa-life-ring"></i> I'm Stuck
                                                </button>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; 
                            endforeach; ?>
                        </div>
                    </div>
                    
                    <!-- Done Column -->
                    <div class="kanban-column" data-status="done">
                        <div class="column-header">
                            <div class="column-title column-done">
                                <div class="column-icon"><i class="fas fa-check-circle"></i></div>
                                Done
                            </div>
                            <div class="task-count"><?php echo count(array_filter($tasks, fn($t) => $t['status'] == 'done')); ?></div>
                        </div>
                        <div class="task-list" id="done-list">
                            <?php foreach ($tasks as $task): 
                                if ($task['status'] == 'done'): ?>
                                    <div class="task-card" data-task-id="<?php echo $task['task_id']; ?>" 
                                         data-project-id="<?php echo $task['project_id']; ?>"
                                         data-status="done" draggable="true">
                                        <div class="task-header">
                                            <div class="task-title"><?php echo htmlspecialchars($task['title']); ?></div>
                                        </div>
                                        
                                        <div class="task-footer">
                                            <?php if ($task['assigned_to']): ?>
                                            <div class="task-assignee">
                                                <div class="assignee-avatar">
                                                    <?php echo strtoupper(substr($task['assignee_first'], 0, 1) . substr($task['assignee_last'], 0, 1)); ?>
                                                </div>
                                                <span><?php echo htmlspecialchars($task['assignee_first'] . ' ' . $task['assignee_last']); ?></span>
                                            </div>
                                            <?php else: ?>
                                            <div class="task-assignee">
                                                <span style="color: var(--gray); font-style: italic;">Unassigned</span>
                                            </div>
                                            <?php endif; ?>
                                            
                                            <?php if ($task['completed_at']): ?>
                                                <div class="task-due">
                                                    Completed: <?php echo date('M d', strtotime($task['completed_at'])); ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endif; 
                            endforeach; ?>
                        </div>
                    </div>
                    
                    <!-- Approved Column -->
                    <div class="kanban-column" data-status="approved">
                        <div class="column-header">
                            <div class="column-title column-approved">
                                <div class="column-icon"><i class="fas fa-thumbs-up"></i></div>
                                Approved
                            </div>
                            <div class="task-count"><?php echo count(array_filter($tasks, fn($t) => $t['status'] == 'approved')); ?></div>
                        </div>
                        <div class="task-list" id="approved-list">
                            <?php foreach ($tasks as $task): 
                                if ($task['status'] == 'approved'): ?>
                                    <div class="task-card" data-task-id="<?php echo $task['task_id']; ?>" 
                                         data-project-id="<?php echo $task['project_id']; ?>"
                                         data-status="approved" draggable="true">
                                        <div class="task-header">
                                            <div class="task-title"><?php echo htmlspecialchars($task['title']); ?></div>
                                        </div>
                                    </div>
                                <?php endif; 
                            endforeach; ?>
                        </div>
                    </div>
                    
                    <!-- Blocked Column -->
                    <div class="kanban-column" data-status="blocked">
                        <div class="column-header">
                            <div class="column-title column-blocked">
                                <div class="column-icon"><i class="fas fa-ban"></i></div>
                                Blocked
                            </div>
                            <div class="task-count"><?php echo count(array_filter($tasks, fn($t) => $t['status'] == 'blocked')); ?></div>
                        </div>
                        <div class="task-list" id="blocked-list">
                            <?php foreach ($tasks as $task): 
                                if ($task['status'] == 'blocked'): ?>
                                    <div class="task-card blocked" data-task-id="<?php echo $task['task_id']; ?>" 
                                         data-project-id="<?php echo $task['project_id']; ?>"
                                         data-status="blocked" draggable="true">
                                        <div class="task-header">
                                            <div class="task-title"><?php echo htmlspecialchars($task['title']); ?></div>
                                        </div>
                                    </div>
                                <?php endif; 
                            endforeach; ?>
                        </div>
                    </div>
                </div>

                <?php if (in_array($role, ['admin', 'manager', 'team_leader'])): ?>
                <!-- go to parent task page floating style -->
                <a style="position: fixed; bottom: 40px; right: 150px;" href="parent-task-manager.php" class="btn btn-secondary" style="margin-top: 20px;">
                    <i class="fas fa-tasks"></i> Manage Parent Tasks
                </a>

                <button class="create-task-btn" id="createTaskBtn" title="Create New Task">
                    <i class="fas fa-plus"></i>
                </button>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Create/Edit Task Modal -->
    <div class="task-modal" id="taskModal">
        <div class="task-modal-content">
            <div class="task-modal-header">
                <h3 class="task-modal-title" id="modalTitle">Create New Task</h3>
                <button class="task-modal-close" id="closeModal">&times;</button>
            </div>
            <form id="taskForm" method="POST" action="ajax/save_task.php">
                <div class="task-modal-body">
                    <div class="form-grid">
                        <div class="form-group form-full">
                            <label for="task_name">Task Name *</label>
                            <input type="text" id="task_name" name="task_name" class="form-control" required>
                        </div>
                        
                        <div class="form-group form-full">
                            <label for="description">Description</label>
                            <textarea id="description" name="description" class="form-control" rows="3"></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label for="project_id">Project *</label>
                            <select id="project_id" name="project_id" class="form-control" required>
                                <option value="">Select Project</option>
                                <?php foreach ($projects as $project): ?>
                                    <option value="<?php echo $project['project_id']; ?>">
                                        <?php echo htmlspecialchars($project['project_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="parent_task_id">Parent Task</label>
                            <select id="parent_task_id" name="parent_task_id" class="form-control">
                                <option value="">None (Top Level Task)</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="priority">Priority *</label>
                            <select id="priority" name="priority" class="form-control" required>
                                <option value="low">Low</option>
                                <option value="medium" selected>Medium</option>
                                <option value="high">High</option>
                                <option value="critical">Critical</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="assigned_to">Assign To</label>
                            <select id="assigned_to" name="assigned_to" class="form-control">
                                <option value="">Select a project first</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="start_date">Start Date *</label>
                            <input type="date" id="start_date" name="start_date" class="form-control" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="due_date">Due Date *</label>
                            <input type="date" id="due_date" name="due_date" class="form-control" required>
                        </div>
                        
                        <div class="form-group form-full">
                            <label>Required Skills</label>
                            <div class="skills-container" id="skillsContainer">
                                <!-- Skills will be added here -->
                            </div>
                            <div style="display: flex; gap: 10px; margin-top: 10px;">
                                <select id="skillSelect" class="form-control" style="flex: 1;">
                                    <option value="">Select Skill</option>
                                    <?php foreach ($skills as $skill): ?>
                                        <option value="<?php echo $skill['skill_id']; ?>">
                                            <?php echo htmlspecialchars($skill['skill_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <button type="button" class="btn btn-outline" id="addSkillBtn">Add Skill</button>
                            </div>
                        </div>
                        
                        <input type="hidden" id="task_id" name="task_id" value="">
                        <input type="hidden" id="required_skills" name="required_skills" value="">
                    </div>
                </div>
                <div class="task-modal-footer">
                    <button type="button" class="btn btn-secondary" id="cancelBtn">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Task</button>
                </div>
            </form>
        </div>
    </div>
    
    <script src="assets/js/kanban.js"></script>
    <script>
    // Parent tasks data by project
    const parentTasksByProject = <?php echo json_encode($parent_tasks_by_project); ?>;
    
    document.addEventListener('DOMContentLoaded', function() {
        // Initialize kanban board
        const kanban = new KanbanBoard();
        kanban.init();
        
        // Task creation modal
        const createTaskBtn = document.getElementById('createTaskBtn');
        const taskModal = document.getElementById('taskModal');
        const closeModal = document.getElementById('closeModal');
        const cancelBtn = document.getElementById('cancelBtn');
        
        createTaskBtn.addEventListener('click', function() {
            document.getElementById('modalTitle').textContent = 'Create New Task';
            document.getElementById('taskForm').reset();
            document.getElementById('task_id').value = '';
            document.getElementById('skillsContainer').innerHTML = '';
            document.getElementById('required_skills').value = '';
            taskModal.style.display = 'flex';
        });
        
        closeModal.addEventListener('click', function() {
            taskModal.style.display = 'none';
        });
        
        cancelBtn.addEventListener('click', function() {
            taskModal.style.display = 'none';
        });
        
        // Close modal when clicking outside
        taskModal.addEventListener('click', function(e) {
            if (e.target === taskModal) {
                taskModal.style.display = 'none';
            }
        });
        
        // Skill management
        const skillsContainer = document.getElementById('skillsContainer');
        const skillSelect = document.getElementById('skillSelect');
        const addSkillBtn = document.getElementById('addSkillBtn');
        const requiredSkillsInput = document.getElementById('required_skills');
        
        let selectedSkills = [];
        
        function updateSkillsInput() {
            requiredSkillsInput.value = JSON.stringify(selectedSkills);
        }
        
        function addSkillTag(skillId, skillName) {
            const skillTag = document.createElement('div');
            skillTag.className = 'skill-tag';
            skillTag.innerHTML = `
                ${skillName}
                <button type="button" class="remove-skill" data-skill-id="${skillId}">&times;</button>
            `;
            skillsContainer.appendChild(skillTag);
            
            // Remove skill
            skillTag.querySelector('.remove-skill').addEventListener('click', function() {
                const id = parseInt(this.dataset.skillId);
                selectedSkills = selectedSkills.filter(s => s.skill_id !== id);
                skillTag.remove();
                updateSkillsInput();
            });
        }
        
        addSkillBtn.addEventListener('click', function() {
            const skillId = parseInt(skillSelect.value);
            const skillName = skillSelect.options[skillSelect.selectedIndex].text;
            
            if (!skillId || selectedSkills.some(s => s.skill_id === skillId)) {
                return;
            }
            
            selectedSkills.push({
                skill_id: skillId,
                skill_name: skillName
            });
            
            addSkillTag(skillId, skillName);
            skillSelect.value = '';
            updateSkillsInput();
        });
        
        // Update parent tasks when project is selected
        const projectSelect = document.getElementById('project_id');
        const parentTaskSelect = document.getElementById('parent_task_id');
        const assignedToSelect = document.getElementById('assigned_to');
        
        projectSelect.addEventListener('change', function() {
            const projectId = this.value;
            
            // Clear current options
            parentTaskSelect.innerHTML = '<option value="">None (Top Level Task)</option>';
            
            // Add parent tasks for selected project
            if (projectId && parentTasksByProject[projectId]) {
                parentTasksByProject[projectId].forEach(function(parentTask) {
                    const option = document.createElement('option');
                    option.value = parentTask.parent_task_id;
                    option.textContent = parentTask.parent_task_name;
                    parentTaskSelect.appendChild(option);
                });
            }
            
            // Load users for selected project
            assignedToSelect.innerHTML = '<option value="">Loading...</option>';
            
            if (projectId) {
                fetch(`ajax/get_project_members.php?project_id=${projectId}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            assignedToSelect.innerHTML = '<option value="">Unassigned</option>';
                            data.users.forEach(function(user) {
                                const option = document.createElement('option');
                                option.value = user.user_id;
                                option.textContent = `${user.first_name} ${user.last_name}`;
                                assignedToSelect.appendChild(option);
                            });
                            
                            if (data.users.length === 0) {
                                assignedToSelect.innerHTML = '<option value="">No employees in this project</option>';
                            }
                        } else {
                            assignedToSelect.innerHTML = '<option value="">Error loading users</option>';
                            console.error('Error loading users:', data.message);
                        }
                    })
                    .catch(error => {
                        assignedToSelect.innerHTML = '<option value="">Error loading users</option>';
                        console.error('Error fetching users:', error);
                    });
            } else {
                assignedToSelect.innerHTML = '<option value="">Select a project first</option>';
            }
        });
        
        // Task assignment
        document.querySelectorAll('.assign-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const taskId = this.dataset.taskId;
                const userId = this.dataset.userId;
                
                fetch('ajax/assign_task.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `task_id=${taskId}&user_id=${userId}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Task assigned successfully!');
                        location.reload();
                    } else {
                        alert('Error: ' + data.message);
                    }
                });
            });
        });
        
        // SOS button
        document.querySelectorAll('.sos-button').forEach(btn => {
            btn.addEventListener('click', function() {
                const taskId = this.dataset.taskId;
                
                if (!confirm('Are you sure you need help with this task?')) {
                    return;
                }
                
                fetch('ajax/send_sos.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `task_id=${taskId}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Help request sent! Your team leader has been notified.');
                        this.innerHTML = '<i class="fas fa-check"></i> Help Requested';
                        this.disabled = true;
                        this.classList.remove('sos-button');
                        this.style.background = 'var(--gray)';
                    } else {
                        alert('Error: ' + data.message);
                    }
                });
            });
        });
    });
    </script>
</body>
</html>