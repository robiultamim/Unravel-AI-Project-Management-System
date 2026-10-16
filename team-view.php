<?php
// pages/team-view.php
require_once 'config/database.php';
require_once 'config/session.php';
require_once 'includes/functions.php';

Session::redirectIfNotLoggedIn();

// Only team leaders can access this page
if (Session::getCurrentUserRole() != 'team_leader') {
    header("Location: dashboard.php");
    exit();
}

$database = new Database();
$db = $database->getConnection();
$user_id = Session::getUserID();

// Get projects where user is team leader
$projects_query = "SELECT p.*, pm.role_in_project 
                  FROM projects p
                  JOIN project_members pm ON p.project_id = pm.project_id
                  WHERE pm.user_id = ? AND pm.role_in_project = 'team_leader'
                  ORDER BY p.created_at DESC";
$projects_stmt = $db->prepare($projects_query);
$projects_stmt->execute([$user_id]);
$projects = $projects_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get team members for each project
foreach ($projects as &$project) {
    $members_query = "SELECT u.first_name, u.last_name, u.email, u.role as user_role, pm.role_in_project
                     FROM project_members pm
                     JOIN users u ON pm.user_id = u.user_id
                     WHERE pm.project_id = ? AND pm.role_in_project = 'employee'
                     ORDER BY u.first_name";
    $members_stmt = $db->prepare($members_query);
    $members_stmt->execute([$project['project_id']]);
    $project['team_members'] = $members_stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Unravel - My Team</title>
    
            <link rel="stylesheet" href="assets/css/dashboard.css">

    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/team.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="role-team_leader">
    <div class="container">
        <?php include 'includes/sidebar.php'; ?>
        
        <div class="main-content">
            <?php include 'includes/header.php'; ?>
            
            <div class="team-container">
                <div class="card">
                    <div class="card-header">
                        <div class="card-title">My Team Projects</div>
                    </div>
                    <div class="card-body">
                        <?php if (empty($projects)): ?>
                            <p class="text-center">Not assigned as team leader to any project</p>
                        <?php else: ?>
                            <div class="projects-accordion">
                                <?php foreach ($projects as $project): ?>
                                    <div class="project-accordion-item">
                                        <div class="project-accordion-header">
                                            <h4><?php echo htmlspecialchars($project['project_name']); ?></h4>
                                            <span class="project-status status-<?php echo $project['status']; ?>">
                                                <?php echo ucfirst($project['status']); ?>
                                            </span>
                                            <i class="fas fa-chevron-down"></i>
                                        </div>
                                        <div class="project-accordion-content">
                                            <p><?php echo htmlspecialchars($project['description']); ?></p>
                                            
                                            <div class="team-members">
                                                <h5>Team Members</h5>
                                                <?php if (empty($project['team_members'])): ?>
                                                    <p>No team members assigned</p>
                                                <?php else: ?>
                                                    <div class="members-grid">
                                                        <?php foreach ($project['team_members'] as $member): ?>
                                                            <div class="member-card">
                                                                <div class="member-avatar">
                                                                    <?php echo strtoupper(substr($member['first_name'], 0, 1) . substr($member['last_name'], 0, 1)); ?>
                                                                </div>
                                                                <div class="member-info">
                                                                    <div class="member-name">
                                                                        <?php echo htmlspecialchars($member['first_name'] . ' ' . $member['last_name']); ?>
                                                                    </div>
                                                                    <div class="member-email">
                                                                        <?php echo htmlspecialchars($member['email']); ?>
                                                                    </div>
                                                                    <div class="member-role">
                                                                        <span class="badge badge-secondary">
                                                                            <?php echo ucfirst(str_replace('_', ' ', $member['user_role'])); ?>
                                                                        </span>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        <?php endforeach; ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
    // Accordion functionality
    document.addEventListener('DOMContentLoaded', function() {
        const accordionHeaders = document.querySelectorAll('.project-accordion-header');
        
        accordionHeaders.forEach(header => {
            header.addEventListener('click', function() {
                const content = this.nextElementSibling;
                const icon = this.querySelector('.fa-chevron-down');
                
                // Toggle content
                if (content.style.maxHeight) {
                    content.style.maxHeight = null;
                    icon.style.transform = 'rotate(0deg)';
                } else {
                    content.style.maxHeight = content.scrollHeight + 'px';
                    icon.style.transform = 'rotate(180deg)';
                }
                
                // Close other accordions
                accordionHeaders.forEach(otherHeader => {
                    if (otherHeader !== this) {
                        const otherContent = otherHeader.nextElementSibling;
                        const otherIcon = otherHeader.querySelector('.fa-chevron-down');
                        otherContent.style.maxHeight = null;
                        otherIcon.style.transform = 'rotate(0deg)';
                    }
                });
            });
        });
        
        // Open first accordion by default
        if (accordionHeaders.length > 0) {
            accordionHeaders[0].click();
        }
    });
    </script>
</body>
</html>