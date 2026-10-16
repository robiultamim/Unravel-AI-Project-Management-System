<?php
// pages/hierarchy.php
require_once 'config/database.php';
require_once 'config/session.php';
require_once 'includes/functions.php';
require_once 'config/email.php';

Session::redirectIfNotLoggedIn();

// Only admin can access hierarchy management
if (Session::getCurrentUserRole() != 'admin') {
    header("Location: dashboard.php");
    exit();
}

$database = new Database();
$db = $database->getConnection();
$user_id = Session::getUserID();

// Handle form submissions
$message = '';
$error = '';

// Add new project
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_project'])) {
    $project_name = trim($_POST['project_name']);
    $description = trim($_POST['description']);
    $start_date = $_POST['start_date'];
    $end_date = $_POST['end_date'];
    
    if (empty($project_name)) {
        $error = "Project name is required";
    } else {
        $query = "INSERT INTO projects (project_name, description, start_date, end_date, status, admin_id) 
                 VALUES (?, ?, ?, ?, 'active', ?)";
        $stmt = $db->prepare($query);
        
        if ($stmt->execute([$project_name, $description, $start_date, $end_date, $user_id])) {
            $project_id = $db->lastInsertId();
            $message = "Project created successfully!";
            
            // Add current admin as project member
            $add_member_query = "INSERT INTO project_members (project_id, user_id, role_in_project, added_by) 
                               VALUES (?, ?, 'manager', ?)";
            $add_member_stmt = $db->prepare($add_member_query);
            $add_member_stmt->execute([$project_id, $user_id, $user_id]);
        } else {
            $error = "Failed to create project";
        }
    }
}

// Add manager to project
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_manager'])) {
    $project_id = $_POST['project_id'];
    $email = trim($_POST['manager_email']);
    $first_name = trim($_POST['manager_first_name']);
    $last_name = trim($_POST['manager_last_name']);
    
    if (empty($email) || empty($first_name) || empty($last_name)) {
        $error = "All fields are required";
    } else {
        // Check if user exists
        $user_query = "SELECT * FROM users WHERE email = ?";
        $user_stmt = $db->prepare($user_query);
        $user_stmt->execute([$email]);
        
        if ($user_stmt->rowCount() > 0) {
            // User exists, add as manager
            $user = $user_stmt->fetch(PDO::FETCH_ASSOC);
            
            // Check if already a member
            $member_query = "SELECT * FROM project_members WHERE project_id = ? AND user_id = ?";
            $member_stmt = $db->prepare($member_query);
            $member_stmt->execute([$project_id, $user['user_id']]);
            
            if ($member_stmt->rowCount() == 0) {
                // Add as manager
                $add_query = "INSERT INTO project_members (project_id, user_id, role_in_project, added_by) 
                            VALUES (?, ?, 'manager', ?)";
                $add_stmt = $db->prepare($add_query);
                
                if ($add_stmt->execute([$project_id, $user['user_id'], $user_id])) {
                    $message = "Manager added successfully!";

                    // Update project manager_id in projects table
                    $update_query = "UPDATE projects SET manager_id = ? WHERE project_id = ?";
                    $update_stmt = $db->prepare($update_query);
                    $update_stmt->execute([$user['user_id'], $project_id]);
                    
                    // Send notification
                    sendUserNotification($user['user_id'], "You've been added as Manager to a project", $db);
                } else {
                    $error = "Failed to add manager";
                }
            } else {
                $error = "User is already a member of this project";
            }
        } else {
            // Create new user with temporary password
            $temp_password = bin2hex(random_bytes(8)); // 16 character password
            $password_hash = password_hash($temp_password, PASSWORD_BCRYPT);
            
            // Create username from email
            $username = explode('@', $email)[0] . rand(100, 999);
            
            $create_query = "INSERT INTO users (username, email, password_hash, first_name, last_name, role) 
                           VALUES (?, ?, ?, ?, ?, 'manager')";
            $create_stmt = $db->prepare($create_query);
            
            if ($create_stmt->execute([$username, $email, $password_hash, $first_name, $last_name])) {
                $new_user_id = $db->lastInsertId();
                
                // Add to project
                $add_query = "INSERT INTO project_members (project_id, user_id, role_in_project, added_by) 
                            VALUES (?, ?, 'manager', ?)";
                $add_stmt = $db->prepare($add_query);
                
                if ($add_stmt->execute([$project_id, $new_user_id, $user_id])) {


                    // Update project manager_id in projects table
                    $update_query = "UPDATE projects SET manager_id = ? WHERE project_id = ?";  
                    $update_stmt = $db->prepare($update_query);
                    $update_stmt->execute([$new_user_id, $project_id]);

                    // Get project name for email
                    $project_query = "SELECT project_name FROM projects WHERE project_id = ?";
                    $project_stmt = $db->prepare($project_query);
                    $project_stmt->execute([$project_id]);
                    $project = $project_stmt->fetch(PDO::FETCH_ASSOC);
                    
                    // Send invitation email
                    $email_sender = new EmailSender();
                    if ($email_sender->sendInvitation($email, $first_name . ' ' . $last_name, 
                        $project['project_name'], 'Manager', $temp_password, $username)) {
                        $message = "New manager created and invitation sent!";
                    } else {
                        $message = "Manager created but email failed to send. Password: $temp_password";
                    }
                } else {
                    $error = "Failed to add manager to project";
                }
            } else {
                $error = "Failed to create user";
            }
        }
    }
}

// Add team leader or employee (similar logic as manager)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_member'])) {
    $project_id = $_POST['member_project_id'];
    $email = trim($_POST['member_email']);
    $first_name = trim($_POST['member_first_name']);
    $last_name = trim($_POST['member_last_name']);
    $role = $_POST['member_role']; // 'team_leader' or 'employee'
    $is_team_leader = ($role == 'team_leader') ? 1 : 0;
    
    if (empty($email) || empty($first_name) || empty($last_name)) {
        $error = "All fields are required";
    } else {
        // Similar logic as add_manager but for team_leader/employee
        $user_query = "SELECT * FROM users WHERE email = ?";
        $user_stmt = $db->prepare($user_query);
        $user_stmt->execute([$email]);
        
        if ($user_stmt->rowCount() > 0) {
            $user = $user_stmt->fetch(PDO::FETCH_ASSOC);
            
            // Check if already a member
            $member_query = "SELECT * FROM project_members WHERE project_id = ? AND user_id = ?";
            $member_stmt = $db->prepare($member_query);
            $member_stmt->execute([$project_id, $user['user_id']]);
            
            if ($member_stmt->rowCount() == 0) {
                // Add as member
                $add_query = "INSERT INTO project_members (project_id, user_id, role_in_project, is_team_leader, added_by) 
                            VALUES (?, ?, ?, ?, ?)";
                $add_stmt = $db->prepare($add_query);
                
                if ($add_stmt->execute([$project_id, $user['user_id'], $role, $is_team_leader, $user_id])) {
                    $message = ucfirst(str_replace('_', ' ', $role)) . " added successfully!";

                    // Update project team_leader_id in projects table
                    $update_query = "UPDATE projects SET team_leader_id = ? WHERE project_id = ?";
                    $update_stmt = $db->prepare($update_query);
                    $update_stmt->execute([$user['user_id'], $project_id]);

                    
                    // Send notification
                    sendUserNotification($user['user_id'], "You've been added to a project as " . $role, $db);
                } else {
                    $error = "Failed to add member";
                }
            } else {
                $error = "User is already a member of this project";
            }
        } else {
            // Create new user
            $temp_password = bin2hex(random_bytes(8));
            $password_hash = password_hash($temp_password, PASSWORD_BCRYPT);
            $username = explode('@', $email)[0] . rand(100, 999);
            
            $create_query = "INSERT INTO users (username, email, password_hash, first_name, last_name, role) 
                           VALUES (?, ?, ?, ?, ?, ?)";
            $create_stmt = $db->prepare($create_query);
            
            if ($create_stmt->execute([$username, $email, $password_hash, $first_name, $last_name, $role])) {
                $new_user_id = $db->lastInsertId();
                
                // Add to project
                $add_query = "INSERT INTO project_members (project_id, user_id, role_in_project, is_team_leader, added_by) 
                            VALUES (?, ?, ?, ?, ?)";
                $add_stmt = $db->prepare($add_query);
                
                if ($add_stmt->execute([$project_id, $new_user_id, $role, $is_team_leader, $user_id])) {

                    // Update project team_leader_id in projects table if role is team_leader
                    if ($role == 'team_leader') {
                        $update_query = "UPDATE projects SET team_leader_id = ? WHERE project_id = ?";
                        $update_stmt = $db->prepare($update_query);
                        $update_stmt->execute([$new_user_id, $project_id]);
                    }
                    
                    // Get project name
                    $project_query = "SELECT project_name FROM projects WHERE project_id = ?";
                    $project_stmt = $db->prepare($project_query);
                    $project_stmt->execute([$project_id]);
                    $project = $project_stmt->fetch(PDO::FETCH_ASSOC);
                    
                    // Send invitation email
                    $email_sender = new EmailSender();
                    if ($email_sender->sendInvitation($email, $first_name . ' ' . $last_name, 
                        $project['project_name'], ucfirst(str_replace('_', ' ', $role)), $temp_password, $username)) {
                        $message = "New " . $role . " created and invitation sent!";
                    } else {
                        $message = ucfirst($role) . " created but email failed. Password: $temp_password";
                    }
                } else {
                    $error = "Failed to add member to project";
                }
            } else {
                $error = "Failed to create user";
            }
        }
    }
}

// Remove member from project
if (isset($_GET['remove_member'])) {
    $member_id = $_GET['remove_member'];
    
    // Check if admin owns the project
    $check_query = "SELECT pm.*, p.project_name FROM project_members pm
                   JOIN projects p ON pm.project_id = p.project_id
                   WHERE pm.id = ? AND pm.added_by = ?";
    $check_stmt = $db->prepare($check_query);
    $check_stmt->execute([$member_id, $user_id]);
    
    if ($check_stmt->rowCount() > 0) {
        $remove_query = "DELETE FROM project_members WHERE id = ?";
        $remove_stmt = $db->prepare($remove_query);
        
        if ($remove_stmt->execute([$member_id])) {
            $message = "Member removed successfully";
        } else {
            $error = "Failed to remove member";
        }
    } else {
        $error = "You don't have permission to remove this member";
    }
}

// Get all projects
$projects_query = "SELECT p.*, 
                  (SELECT COUNT(*) FROM project_members pm WHERE pm.project_id = p.project_id) as member_count,
                  (SELECT CONCAT(first_name, ' ', last_name) FROM users u WHERE u.user_id = p.manager_id) as manager_name,
                  (SELECT COUNT(*) FROM project_members pm WHERE pm.project_id = p.project_id AND pm.is_team_leader = 1) as has_team_leader
                  FROM projects p WHERE p.admin_id = ?
                  ORDER BY p.created_at DESC";
$projects_stmt = $db->prepare($projects_query);
$projects_stmt->execute([$_SESSION['user_id']]);
$projects = $projects_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get all users for dropdown
$users_query = "SELECT * FROM users WHERE is_active = 1 ORDER BY first_name, last_name";
$users = $db->query($users_query)->fetchAll(PDO::FETCH_ASSOC);

// Function to send user notification
function sendUserNotification($user_id, $message, $db) {
    $query = "INSERT INTO notifications (user_id, title, message, type) 
             VALUES (?, 'Project Update', ?, 'system')";
    $stmt = $db->prepare($query);
    $stmt->execute([$user_id, $message]);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Unravel - Hierarchy Management</title>

            <link rel="stylesheet" href="assets/css/dashboard.css">

    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/hierarchy.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="role-<?php echo Session::getCurrentUserRole(); ?>">
    <div class="container">
        <?php include 'includes/sidebar.php'; ?>
        
        <div class="main-content">
            <?php include 'includes/header.php'; ?>
            
            <!-- Hierarchy Content -->
            <div class="hierarchy-container">
                <?php if ($message): ?>
                    <div class="alert alert-success"><?php echo $message; ?></div>
                <?php endif; ?>
                
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <!-- Create New Project Card -->
                <div class="card">
                    <div class="card-header">
                        <div class="card-title">Create New Project</div>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="">
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="project_name">Project Name *</label>
                                    <input type="text" id="project_name" name="project_name" class="form-control" required>
                                </div>
                                <div class="form-group">
                                    <label for="start_date">Start Date</label>
                                    <input type="date" id="start_date" name="start_date" class="form-control">
                                </div>
                                <div class="form-group">
                                    <label for="end_date">End Date</label>
                                    <input type="date" id="end_date" name="end_date" class="form-control">
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="description">Description</label>
                                <textarea id="description" name="description" class="form-control" rows="3"></textarea>
                            </div>
                            <div class="form-group">
                                <button type="submit" name="add_project" class="btn btn-primary">
                                    <i class="fas fa-plus"></i> Create Project
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Projects List -->
                <div class="card">
                    <div class="card-header">
                        <div class="card-title">Projects</div>
                    </div>
                    <div class="card-body">
                        <div class="projects-grid">
                            <?php foreach ($projects as $project): ?>
                                <div class="project-card">
                                    <div class="project-header">
                                        <h4><?php echo htmlspecialchars($project['project_name']); ?></h4>
                                        <span class="project-status status-<?php echo $project['status']; ?>">
                                            <?php echo ucfirst($project['status']); ?>
                                        </span>
                                    </div>
                                    <div class="project-info">
                                        <p><?php echo htmlspecialchars($project['description']); ?></p>
                                        <div class="project-meta">
                                            <span><i class="fas fa-users"></i> <?php echo $project['member_count']; ?> Members</span>
                                            <span><i class="fas fa-calendar"></i> <?php echo date('M d, Y', strtotime($project['created_at'])); ?></span>
                                        </div>
                                    </div>
                                    <div class="project-actions">
                                        <?php if (empty($project['manager_id'])): ?>
                                            <button class="btn btn-sm btn-outline" onclick="showAddManagerModal(<?php echo $project['project_id']; ?>)">
                                                <i class="fas fa-user-tie"></i> Add Manager
                                            </button>
                                        <?php endif; ?>
                                        <button class="btn btn-sm btn-outline" onclick="showAddMemberModal(<?php echo $project['project_id']; ?>, <?php echo $project['has_team_leader']; ?>)">
                                            <i class="fas fa-user-plus"></i> Add Member
                                        </button>
                                        <a href="?view_members=<?php echo $project['project_id']; ?>" class="btn btn-sm btn-outline">
                                            <i class="fas fa-eye"></i> View Members
                                        </a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                
                <!-- View Project Members -->
                <?php if (isset($_GET['view_members'])): 
                    $project_id = $_GET['view_members'];
                    
                    $members_query = "SELECT pm.*, u.first_name, u.last_name, u.email, u.role as user_role
                                     FROM project_members pm
                                     JOIN users u ON pm.user_id = u.user_id
                                     WHERE pm.project_id = ?
                                     ORDER BY 
                                         CASE pm.role_in_project 
                                             WHEN 'manager' THEN 1
                                             WHEN 'team_leader' THEN 2
                                             WHEN 'employee' THEN 3
                                         END";
                    $members_stmt = $db->prepare($members_query);
                    $members_stmt->execute([$project_id]);
                    $members = $members_stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    $project_query = "SELECT project_name FROM projects WHERE project_id = ?";
                    $project_stmt = $db->prepare($project_query);
                    $project_stmt->execute([$project_id]);
                    $current_project = $project_stmt->fetch(PDO::FETCH_ASSOC);
                ?>
                    <div class="card">
                        <div class="card-header">
                            <div class="card-title">
                                Members of: <?php echo htmlspecialchars($current_project['project_name']); ?>
                                <a href="hierarchy.php" class="btn btn-sm btn-outline" style="float: right;">
                                    <i class="fas fa-arrow-left"></i> Back
                                </a>
                            </div>
                        </div>
                        <div class="card-body">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Email</th>
                                        <!-- <th>Project Role</th> -->
                                        <th>Project Role</th>
                                        <th>Added On</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($members as $member): ?>
                                        <tr>
                                            <td>
                                                <?php echo htmlspecialchars($member['first_name'] . ' ' . $member['last_name']); ?>

                                            </td>
                                            <td><?php echo htmlspecialchars($member['email']); ?></td>
                                            <!-- <td>
                                                <?php 
                                                $role_badges = [
                                                    'manager' => 'badge-warning',
                                                    'team_leader' => 'badge-info',
                                                    'employee' => 'badge-secondary'
                                                ];
                                                $badge_class = $role_badges[$member['role_in_project']] ?? 'badge-secondary';
                                                ?>
                                                <span class="badge <?php echo $badge_class; ?>">
                                                    <?php echo ucfirst(str_replace('_', ' ', $member['role_in_project'])); ?>
                                                </span>
                                            </td> -->
                                            <td>
                                                <?php 
                                                $system_badges = [
                                                    'admin' => 'badge-success',
                                                    'manager' => 'badge-warning',
                                                    'team_leader' => 'badge-info',
                                                    'employee' => 'badge-secondary'
                                                ];
                                                $sys_badge = $system_badges[$member['user_role']] ?? 'badge-secondary';
                                                ?>
                                                <span class="badge <?php echo $sys_badge; ?>">
                                                    <?php echo ucfirst(str_replace('_', ' ', $member['user_role'])); ?>
                                                </span>
                                            </td>
                                            <td><?php echo date('M d, Y', strtotime($member['added_at'])); ?></td>
                                            <td>
                                                <?php if ($member['role_in_project'] != 'manager' || $member['user_id'] != $user_id): ?>
                                                    <a href="?remove_member=<?php echo $member['id']; ?>&view_members=<?php echo $project_id; ?>" 
                                                       class="btn btn-sm btn-outline" 
                                                       onclick="return confirm('Are you sure you want to remove this member?')">
                                                        <i class="fas fa-trash"></i> Remove
                                                    </a>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endif; ?>
                
                <!-- Organization Structure Visualization -->
                <div class="card">
                    <div class="card-header">
                        <div class="card-title">Organization Structure</div>
                    </div>
                    <div class="card-body">
                        <div id="orgChart">
                            <!-- Chart will be loaded via JavaScript -->
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Add Manager Modal -->
    <div class="modal" id="addManagerModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Add Manager to Project</h3>
                <button class="modal-close">&times;</button>
            </div>
            <div class="modal-body">
                <form method="POST" action="" id="managerForm">
                    <input type="hidden" id="manager_project_id" name="project_id">
                    <div class="form-group">
                        <label for="manager_email">Email *</label>
                        <input type="email" id="manager_email" name="manager_email" class="form-control" required>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="manager_first_name">First Name *</label>
                            <input type="text" id="manager_first_name" name="manager_first_name" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="manager_last_name">Last Name *</label>
                            <input type="text" id="manager_last_name" name="manager_last_name" class="form-control" required>
                        </div>
                    </div>
                    <div class="form-group">
                        <button type="submit" name="add_manager" class="btn btn-primary">
                            <i class="fas fa-user-tie"></i> Add Manager
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Add Member Modal -->
    <div class="modal" id="addMemberModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Add Team Member</h3>
                <button class="modal-close">&times;</button>
            </div>
            <div class="modal-body">
                <form method="POST" action="" id="memberForm">
                    <input type="hidden" id="member_project_id" name="member_project_id">
                    <div class="form-group">
                        <label for="member_email">Email *</label>
                        <input type="email" id="member_email" name="member_email" class="form-control" required>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="member_first_name">First Name *</label>
                            <input type="text" id="member_first_name" name="member_first_name" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="member_last_name">Last Name *</label>
                            <input type="text" id="member_last_name" name="member_last_name" class="form-control" required>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="member_role">Role *</label>
                        <select id="member_role" name="member_role" class="form-control" required>
                            <option value="team_leader" id="team_leader_option">Team Leader</option>
                            <option value="employee" selected>Employee</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <button type="submit" name="add_member" class="btn btn-primary">
                            <i class="fas fa-user-plus"></i> Add Member
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script src="assets/js/hierarchy.js"></script>
    <script>
    function showAddManagerModal(projectId) {
        document.getElementById('manager_project_id').value = projectId;
        document.getElementById('addManagerModal').style.display = 'flex';
    }
    
    function showAddMemberModal(projectId, hasTeamLeader) {
        document.getElementById('member_project_id').value = projectId;
        const teamLeaderOption = document.getElementById('team_leader_option');
        const memberRoleSelect = document.getElementById('member_role');
        
        if (hasTeamLeader > 0) {
            // Hide team leader option and select employee
            teamLeaderOption.style.display = 'none';
            memberRoleSelect.value = 'employee';
        } else {
            // Show team leader option
            teamLeaderOption.style.display = 'block';
        }
        
        document.getElementById('addMemberModal').style.display = 'flex';
    }
    
    // Close modals
    document.querySelectorAll('.modal-close').forEach(btn => {
        btn.addEventListener('click', function() {
            this.closest('.modal').style.display = 'none';
        });
    });
    
    // Close modal when clicking outside
    document.querySelectorAll('.modal').forEach(modal => {
        modal.addEventListener('click', function(e) {
            if (e.target === this) {
                this.style.display = 'none';
            }
        });
    });
    </script>
</body>
</html>