<?php
// pages/manager-projects.php
require_once 'config/database.php';
require_once 'config/session.php';
require_once 'includes/functions.php';

Session::redirectIfNotLoggedIn();

// Only managers can access this page
if (Session::getCurrentUserRole() != 'manager') {
    header("Location: dashboard.php");
    exit();
}

$database = new Database();
$db = $database->getConnection();
$user_id = Session::getUserID();

// Get projects where user is manager
$projects_query = "SELECT p.*, 
                  (SELECT COUNT(*) FROM project_members pm WHERE pm.project_id = p.project_id) as member_count
                  FROM projects p
                  JOIN project_members pm ON p.project_id = pm.project_id
                  WHERE pm.user_id = ? AND pm.role_in_project = 'manager'
                  ORDER BY p.created_at DESC";
$projects_stmt = $db->prepare($projects_query);
$projects_stmt->execute([$user_id]);
$projects = $projects_stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle add/remove team members
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_team_member'])) {
    $project_id = $_POST['project_id'];
    $member_email = trim($_POST['member_email']);
    $member_role = $_POST['member_role'];
    
    // Check if user exists
    $user_query = "SELECT * FROM users WHERE email = ? AND is_active = 1";
    $user_stmt = $db->prepare($user_query);
    $user_stmt->execute([$member_email]);
    
    if ($user_stmt->rowCount() > 0) {
        $member = $user_stmt->fetch(PDO::FETCH_ASSOC);
        
        // Check if already a member
        $check_query = "SELECT * FROM project_members WHERE project_id = ? AND user_id = ?";
        $check_stmt = $db->prepare($check_query);
        $check_stmt->execute([$project_id, $member['user_id']]);
        
        if ($check_stmt->rowCount() == 0) {
            $is_team_leader = ($member_role == 'team_leader') ? 1 : 0;
            
            $add_query = "INSERT INTO project_members (project_id, user_id, role_in_project, is_team_leader, added_by) 
                         VALUES (?, ?, ?, ?, ?)";
            $add_stmt = $db->prepare($add_query);
            
            if ($add_stmt->execute([$project_id, $member['user_id'], $member_role, $is_team_leader, $user_id])) {
                $message = "Team member added successfully!";
                
                // Send notification
                $notify_query = "INSERT INTO notifications (user_id, title, message, type) 
                               VALUES (?, 'Added to Project', 'You have been added to a project as ' || ?, 'system')";
                $notify_stmt = $db->prepare($notify_query);
                $notify_stmt->execute([$member['user_id'], $member_role]);
            } else {
                $error = "Failed to add team member";
            }
        } else {
            $error = "User is already a member of this project";
        }
    } else {
        $error = "User not found";
    }
}

// Remove team member
if (isset($_GET['remove_member'])) {
    $member_id = $_GET['remove_member'];
    $project_id = $_GET['project_id'];
    
    // Verify manager owns the project
    $verify_query = "SELECT * FROM project_members 
                    WHERE project_id = ? AND user_id = ? AND role_in_project = 'manager'";
    $verify_stmt = $db->prepare($verify_query);
    $verify_stmt->execute([$project_id, $user_id]);
    
    if ($verify_stmt->rowCount() > 0) {
        $remove_query = "DELETE FROM project_members WHERE id = ? AND project_id = ?";
        $remove_stmt = $db->prepare($remove_query);
        
        if ($remove_stmt->execute([$member_id, $project_id])) {
            $message = "Team member removed successfully";
        } else {
            $error = "Failed to remove team member";
        }
    } else {
        $error = "You don't have permission to remove members from this project";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Unravel - Manage Projects</title>

            <link rel="stylesheet" href="assets/css/dashboard.css">


    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/manager.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="role-manager">
    <div class="container">
        <?php include 'includes/sidebar.php'; ?>
        
        <div class="main-content">
            <?php include 'includes/header.php'; ?>
            
            <div class="manager-container">
                <?php if ($message): ?>
                    <div class="alert alert-success"><?php echo $message; ?></div>
                <?php endif; ?>
                
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <div class="card">
                    <div class="card-header">
                        <div class="card-title">My Projects</div>
                    </div>
                    <div class="card-body">
                        <div class="projects-list">
                            <?php if (empty($projects)): ?>
                                <p class="text-center">No projects assigned as manager</p>
                            <?php else: ?>
                                <?php foreach ($projects as $project): ?>
                                    <div class="project-item">
                                        <div class="project-info">
                                            <h4><?php echo htmlspecialchars($project['project_name']); ?></h4>
                                            <p><?php echo htmlspecialchars($project['description']); ?></p>
                                            <div class="project-meta">
                                                <span><i class="fas fa-users"></i> <?php echo $project['member_count']; ?> Members</span>
                                                <span><i class="fas fa-calendar"></i> <?php echo date('M d, Y', strtotime($project['start_date'])); ?></span>
                                                <span class="project-status status-<?php echo $project['status']; ?>">
                                                    <?php echo ucfirst($project['status']); ?>
                                                </span>
                                            </div>
                                        </div>
                                        <div class="project-actions">
                                            <a href="?view_team=<?php echo $project['project_id']; ?>" class="btn btn-outline">
                                                <i class="fas fa-users"></i> Manage Team
                                            </a>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Team Management -->
                <?php if (isset($_GET['view_team'])): 
                    $project_id = $_GET['view_team'];
                    
                    // Get project details
                    $project_query = "SELECT * FROM projects WHERE project_id = ?";
                    $project_stmt = $db->prepare($project_query);
                    $project_stmt->execute([$project_id]);
                    $current_project = $project_stmt->fetch(PDO::FETCH_ASSOC);
                    
                    // Get team members
                    $team_query = "SELECT pm.*, u.first_name, u.last_name, u.email, u.role as user_role
                                 FROM project_members pm
                                 JOIN users u ON pm.user_id = u.user_id
                                 WHERE pm.project_id = ? AND pm.role_in_project != 'manager'
                                 ORDER BY 
                                     CASE pm.role_in_project 
                                         WHEN 'team_leader' THEN 1
                                         WHEN 'employee' THEN 2
                                     END";
                    $team_stmt = $db->prepare($team_query);
                    $team_stmt->execute([$project_id]);
                    $team_members = $team_stmt->fetchAll(PDO::FETCH_ASSOC);
                ?>
                    <div class="card">
                        <div class="card-header">
                            <div class="card-title">
                                Team: <?php echo htmlspecialchars($current_project['project_name']); ?>
                                <a href="manager-projects.php" class="btn btn-sm btn-outline" style="float: right;">
                                    <i class="fas fa-arrow-left"></i> Back
                                </a>
                            </div>
                        </div>
                        <div class="card-body">
                            <!-- Add Team Member Form -->
                            <div class="add-member-form">
                                <h4>Add Team Member</h4>
                                <form method="POST" action="">
                                    <input type="hidden" name="project_id" value="<?php echo $project_id; ?>">
                                    <div class="form-row">
                                        <div class="form-group">
                                            <label>Email</label>
                                            <input type="email" name="member_email" class="form-control" required>
                                        </div>
                                        <div class="form-group">
                                            <label>Role</label>
                                            <select name="member_role" class="form-control" required>
                                                <!-- <option value="team_leader">Team Leader</option> -->
                                                <option value="employee" selected>Employee</option>
                                            </select>
                                        </div>
                                        <div class="form-group">
                                            <button type="submit" name="add_team_member" class="btn btn-primary">
                                                <i class="fas fa-user-plus"></i> Add Member
                                            </button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                            
                            <!-- Team Members Table -->
                            <div class="team-table">
                                <h4>Team Members</h4>
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Name</th>
                                            <th>Email</th>
                                            <th>Project Role</th>
                                            <th>System Role</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($team_members)): ?>
                                            <tr>
                                                <td colspan="5" class="text-center">No team members yet</td>
                                            </tr>
                                        <?php else: ?>
                                            <?php foreach ($team_members as $member): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($member['first_name'] . ' ' . $member['last_name']); ?></td>
                                                    <td><?php echo htmlspecialchars($member['email']); ?></td>
                                                    <td>
                                                        <span class="badge <?php echo $member['role_in_project'] == 'team_leader' ? 'badge-info' : 'badge-secondary'; ?>">
                                                            <?php echo ucfirst(str_replace('_', ' ', $member['role_in_project'])); ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <span class="badge badge-secondary">
                                                            <?php echo ucfirst(str_replace('_', ' ', $member['user_role'])); ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <a href="?remove_member=<?php echo $member['id']; ?>&project_id=<?php echo $project_id; ?>" 
                                                           class="btn btn-sm btn-outline"
                                                           onclick="return confirm('Remove this team member?')">
                                                            <i class="fas fa-trash"></i> Remove
                                                        </a>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>