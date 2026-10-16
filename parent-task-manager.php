<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Parent Task Manager</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            border-radius: 10px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            padding: 30px;
        }
        h1 {
            color: #333;
            margin-bottom: 10px;
        }
        .subtitle {
            color: #666;
            margin-bottom: 30px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        label {
            display: block;
            margin-bottom: 5px;
            color: #333;
            font-weight: 500;
        }
        input, select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
        }
        button {
            background: #667eea;
            color: white;
            padding: 12px 30px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            font-weight: 500;
        }
        button:hover {
            background: #5568d3;
        }
        .success {
            background: #10b981;
            color: white;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .error {
            background: #ef4444;
            color: white;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .parent-tasks-list {
            margin-top: 40px;
        }
        .parent-task-item {
            background: #f9fafb;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 10px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .parent-task-info {
            flex: 1;
        }
        .parent-task-name {
            font-weight: 600;
            color: #333;
        }
        .parent-task-project {
            color: #666;
            font-size: 14px;
        }
        .delete-btn {
            background: #ef4444;
            padding: 8px 15px;
            font-size: 14px;
        }
        .delete-btn:hover {
            background: #dc2626;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Parent Task Manager</h1>
        <p class="subtitle">Create and manage parent tasks for your projects</p>
        
        <?php
        require_once 'config/database.php';
        require_once 'config/session.php';
        
        Session::redirectIfNotLoggedIn();
        
        $database = new Database();
        $db = $database->getConnection();
        $user_id = Session::getUserID();
        
        // Handle form submission
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (isset($_POST['action']) && $_POST['action'] === 'create') {
                $parent_task_name = trim($_POST['parent_task_name'] ?? '');
                $project_id = intval($_POST['project_id'] ?? 0);
                
                if (!empty($parent_task_name) && $project_id > 0) {
                    $query = "INSERT INTO parent_task (parent_task_name, project_id) VALUES (:name, :project_id)";
                    $stmt = $db->prepare($query);
                    $stmt->bindParam(':name', $parent_task_name);
                    $stmt->bindParam(':project_id', $project_id, PDO::PARAM_INT);
                    
                    if ($stmt->execute()) {
                        echo '<div class="success">Parent task created successfully!</div>';
                    } else {
                        echo '<div class="error">Failed to create parent task.</div>';
                    }
                } else {
                    echo '<div class="error">Please fill in all required fields.</div>';
                }
            } elseif (isset($_POST['action']) && $_POST['action'] === 'delete') {
                $parent_task_id = intval($_POST['parent_task_id'] ?? 0);
                
                if ($parent_task_id > 0) {
                    $query = "DELETE FROM parent_task WHERE parent_task_id = :id";
                    $stmt = $db->prepare($query);
                    $stmt->bindParam(':id', $parent_task_id, PDO::PARAM_INT);
                    
                    if ($stmt->execute()) {
                        echo '<div class="success">Parent task deleted successfully!</div>';
                    } else {
                        echo '<div class="error">Failed to delete parent task.</div>';
                    }
                }
            }
        }
        
        // Get active projects
        $projects_query = "SELECT * FROM projects WHERE status = 'active' AND admin_id = :user_id ORDER BY project_name";
        $projects_stmt = $db->prepare($projects_query);
        $projects_stmt->bindParam(':user_id', $user_id);
        $projects_stmt->execute();
        $projects = $projects_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get existing parent tasks
        $parent_tasks_query = "SELECT pt.*, p.project_name 
                               FROM parent_task pt 
                               INNER JOIN projects p ON pt.project_id = p.project_id 
                               WHERE p.admin_id = :user_id 
                               ORDER BY p.project_name, pt.parent_task_name";
        $parent_tasks_stmt = $db->prepare($parent_tasks_query);
        $parent_tasks_stmt->bindParam(':user_id', $user_id);
        $parent_tasks_stmt->execute();
        $parent_tasks = $parent_tasks_stmt->fetchAll(PDO::FETCH_ASSOC);
        ?>
        
        <form method="POST">
            <input type="hidden" name="action" value="create">
            
            <div class="form-group">
                <label for="parent_task_name">Parent Task Name *</label>
                <input type="text" id="parent_task_name" name="parent_task_name" required 
                       placeholder="e.g., Frontend Development, Backend Development">
            </div>
            
            <div class="form-group">
                <label for="project_id">Project *</label>
                <select id="project_id" name="project_id" required>
                    <option value="">Select Project</option>
                    <?php foreach ($projects as $project): ?>
                        <option value="<?php echo $project['project_id']; ?>">
                            <?php echo htmlspecialchars($project['project_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <button type="submit">Create Parent Task</button>
        </form>
        
        <div class="parent-tasks-list">
            <h2>Existing Parent Tasks</h2>
            <?php if (count($parent_tasks) > 0): ?>
                <?php foreach ($parent_tasks as $pt): ?>
                    <div class="parent-task-item">
                        <div class="parent-task-info">
                            <div class="parent-task-name">
                                <?php echo htmlspecialchars($pt['parent_task_name']); ?>
                            </div>
                            <div class="parent-task-project">
                                Project: <?php echo htmlspecialchars($pt['project_name']); ?>
                            </div>
                        </div>
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="parent_task_id" value="<?php echo $pt['parent_task_id']; ?>">
                            <button type="submit" class="delete-btn" 
                                    onclick="return confirm('Are you sure you want to delete this parent task?');">
                                Delete
                            </button>
                        </form>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p style="color: #666; margin-top: 20px;">No parent tasks created yet.</p>
            <?php endif; ?>
        </div>
        
        <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #ddd;">
            <a href="kanban.php" style="color: #667eea; text-decoration: none;">
                ← Back to Kanban Board
            </a>
        </div>
    </div>
</body>
</html>
