<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    $_SESSION['error'] = 'Please login to continue';
    header('Location: ../login.php');
    exit();
}

// Check if form is submitted
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['error'] = 'Invalid request method';
    header('Location: ../kanban.php');
    exit();
}

// Get form data
$task_id = isset($_POST['task_id']) ? intval($_POST['task_id']) : 0;
$task_name = trim($_POST['task_name'] ?? '');
$description = trim($_POST['description'] ?? '');
$project_id = intval($_POST['project_id'] ?? 0);
$priority = $_POST['priority'] ?? 'medium';
$assigned_to = isset($_POST['assigned_to']) && !empty($_POST['assigned_to']) ? intval($_POST['assigned_to']) : null;
$start_date = $_POST['start_date'] ?? null;
$due_date = $_POST['due_date'] ?? null;
$required_skills = isset($_POST['required_skills']) ? $_POST['required_skills'] : '';
$parent_task_id = isset($_POST['parent_task_id']) && !empty($_POST['parent_task_id']) ? intval($_POST['parent_task_id']) : null;

// Validate required fields
if (empty($task_name) || $project_id <= 0) {
    $_SESSION['error'] = 'Task name and project are required';
    header('Location: ../kanban.php');
    exit();
}

// Validate date format
if ($due_date && !strtotime($due_date)) {
    $_SESSION['error'] = 'Invalid due date format';
    header('Location: ../kanban.php');
    exit();
}

if ($start_date && !strtotime($start_date)) {
    $_SESSION['error'] = 'Invalid start date format';
    header('Location: ../kanban.php');
    exit();
}

try {
    $database = new Database();
    $conn = $database->getConnection();
    
    $user_id = $_SESSION['user_id'];
    
    // Prepare dates for database
    $start_date_db = $start_date ? date('Y-m-d', strtotime($start_date)) : null;
    $due_date_db = $due_date ? date('Y-m-d', strtotime($due_date)) : null;
    
    if ($task_id > 0) {
        // Update existing task
        $sql = "UPDATE tasks SET 
                title = :title,
                description = :description,
                project_id = :project_id,
                priority = :priority,
                assigned_to = :assigned_to,
                start_date = :start_date,
                due_date = :due_date,
                parent_task_id = :parent_task_id,
                updated_at = NOW()
                WHERE task_id = :task_id";
        
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':title', $task_name);
        $stmt->bindParam(':description', $description);
        $stmt->bindParam(':project_id', $project_id, PDO::PARAM_INT);
        $stmt->bindParam(':priority', $priority);
        $stmt->bindParam(':assigned_to', $assigned_to, $assigned_to ? PDO::PARAM_INT : PDO::PARAM_NULL);
        $stmt->bindParam(':start_date', $start_date_db, $start_date_db ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $stmt->bindParam(':due_date', $due_date_db, $due_date_db ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $stmt->bindParam(':parent_task_id', $parent_task_id, $parent_task_id ? PDO::PARAM_INT : PDO::PARAM_NULL);
        $stmt->bindParam(':task_id', $task_id, PDO::PARAM_INT);
        
        if ($stmt->execute()) {
            // Update skills if provided
            if (!empty($required_skills)) {
                $skills_data = json_decode($required_skills, true);
                $skill_ids = [];
                
                if (is_array($skills_data)) {
                    foreach ($skills_data as $skill) {
                        if (isset($skill['skill_id'])) {
                            $skill_ids[] = intval($skill['skill_id']);
                        }
                    }
                }
                
                // Delete existing skills
                $delete_sql = "DELETE FROM task_skills WHERE task_id = :task_id";
                $delete_stmt = $conn->prepare($delete_sql);
                $delete_stmt->bindParam(':task_id', $task_id, PDO::PARAM_INT);
                $delete_stmt->execute();
                
                // Insert new skills
                if (!empty($skill_ids)) {
                    $insert_sql = "INSERT INTO task_skills (task_id, skill_id, required_level) VALUES ";
                    $insert_values = [];
                    $insert_params = [];
                    
                    foreach ($skill_ids as $index => $skill_id) {
                        $insert_values[] = "(:task_id, :skill_id{$index}, 3)";
                        $insert_params[":skill_id{$index}"] = $skill_id;
                    }
                    
                    if (!empty($insert_values)) {
                        $insert_sql .= implode(', ', $insert_values);
                        $insert_stmt = $conn->prepare($insert_sql);
                        $insert_stmt->bindParam(':task_id', $task_id, PDO::PARAM_INT);
                        
                        foreach ($insert_params as $param => $value) {
                            $insert_stmt->bindValue($param, $value, PDO::PARAM_INT);
                        }
                        
                        $insert_stmt->execute();
                    }
                }
            }
            
            $_SESSION['success'] = 'Task updated successfully!';
        } else {
            $_SESSION['error'] = 'Failed to update task';
        }
        
    } else {
        // Create new task
        $sql = "INSERT INTO tasks 
                (title, description, project_id, priority, assigned_to, assigned_by, start_date, due_date, parent_task_id, status, kanban_column, created_at, updated_at)
                VALUES 
                (:title, :description, :project_id, :priority, :assigned_to, :assigned_by, :start_date, :due_date, :parent_task_id, 'todo', 'todo', NOW(), NOW())";
        
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':title', $task_name);
        $stmt->bindParam(':description', $description);
        $stmt->bindParam(':project_id', $project_id, PDO::PARAM_INT);
        $stmt->bindParam(':priority', $priority);
        $stmt->bindParam(':assigned_to', $assigned_to, $assigned_to ? PDO::PARAM_INT : PDO::PARAM_NULL);
        $stmt->bindParam(':assigned_by', $user_id, PDO::PARAM_INT);
        $stmt->bindParam(':start_date', $start_date_db, $start_date_db ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $stmt->bindParam(':due_date', $due_date_db, $due_date_db ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $stmt->bindParam(':parent_task_id', $parent_task_id, $parent_task_id ? PDO::PARAM_INT : PDO::PARAM_NULL);
        
        if ($stmt->execute()) {
            $new_task_id = $conn->lastInsertId();
            
            // Add skills if provided
            if (!empty($required_skills)) {
                $skills_data = json_decode($required_skills, true);
                $skill_ids = [];
                
                if (is_array($skills_data)) {
                    foreach ($skills_data as $skill) {
                        if (isset($skill['skill_id'])) {
                            $skill_ids[] = intval($skill['skill_id']);
                        }
                    }
                }
                
                if (!empty($skill_ids)) {
                    $insert_sql = "INSERT INTO task_skills (task_id, skill_id, required_level) VALUES ";
                    $insert_values = [];
                    $insert_params = [];
                    
                    foreach ($skill_ids as $index => $skill_id) {
                        $insert_values[] = "(:task_id, :skill_id{$index}, 3)";
                        $insert_params[":skill_id{$index}"] = $skill_id;
                    }
                    
                    if (!empty($insert_values)) {
                        $insert_sql .= implode(', ', $insert_values);
                        $insert_stmt = $conn->prepare($insert_sql);
                        $insert_stmt->bindParam(':task_id', $new_task_id, PDO::PARAM_INT);
                        
                        foreach ($insert_params as $param => $value) {
                            $insert_stmt->bindValue($param, $value, PDO::PARAM_INT);
                        }
                        
                        $insert_stmt->execute();
                    }
                }
            }
            
            // Create notification if assigned to someone
            if ($assigned_to && $assigned_to != $user_id) {
                $notification_sql = "INSERT INTO notifications 
                                    (user_id, title, message, type, related_id, created_at)
                                    VALUES (:user_id, :title, :message, 'task_assigned', :task_id, NOW())";
                
                $notification_stmt = $conn->prepare($notification_sql);
                $title = "New Task Assigned";
                $message = "You have been assigned a new task: {$task_name}";
                
                $notification_stmt->bindParam(':user_id', $assigned_to, PDO::PARAM_INT);
                $notification_stmt->bindParam(':title', $title);
                $notification_stmt->bindParam(':message', $message);
                $notification_stmt->bindParam(':task_id', $new_task_id, PDO::PARAM_INT);
                $notification_stmt->execute();
            }
            
            $_SESSION['success'] = 'Task created successfully!';
        } else {
            $_SESSION['error'] = 'Failed to create task';
        }
    }
    
    // Redirect back to kanban board
    header('Location: ../kanban.php');
    exit();
    
} catch (PDOException $e) {
    $_SESSION['error'] = 'Database error: ' . $e->getMessage();
    header('Location: ../kanban.php');
    exit();
} catch (Exception $e) {
    $_SESSION['error'] = 'Error: ' . $e->getMessage();
    header('Location: ../kanban.php');
    exit();
}
?>