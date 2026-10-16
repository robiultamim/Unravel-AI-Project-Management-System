<?php
// ajax/get_organization_data.php
require_once '../config/database.php';
require_once '../config/session.php';

Session::redirectIfNotLoggedIn();

header('Content-Type: application/json');

$database = new Database();
$db = $database->getConnection();
$admin_id = Session::getUserId();

// Get admin user
$admin_query = "SELECT * FROM users WHERE role = 'admin' AND user_id = ? LIMIT 1";
$admin_stmt = $db->prepare($admin_query);
$admin_stmt->execute([$admin_id]);
$admin = $admin_stmt->fetch(PDO::FETCH_ASSOC);

// Get projects where admin is the owner (admin_id)
$admin_projects_query = "SELECT project_id FROM projects WHERE admin_id = ?";
$admin_projects_stmt = $db->prepare($admin_projects_query);
$admin_projects_stmt->execute([$admin_id]);
$admin_projects = $admin_projects_stmt->fetchAll(PDO::FETCH_COLUMN);

if (empty($admin_projects)) {
    // No projects assigned to this admin
    echo json_encode([
        'admin' => $admin,
        'managers' => [],
        'team_leaders' => [],
        'employees' => []
    ]);
    exit;
}

$project_placeholders = implode(',', array_fill(0, count($admin_projects), '?'));

// Get managers from admin's projects only
$managers_query = "SELECT DISTINCT u.* FROM users u
                  JOIN project_members pm ON u.user_id = pm.user_id
                  WHERE pm.role_in_project = 'manager' 
                  AND pm.project_id IN ($project_placeholders)
                  AND u.is_active = 1
                  ORDER BY u.first_name, u.last_name";
$managers_stmt = $db->prepare($managers_query);
$managers_stmt->execute($admin_projects);
$managers = $managers_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get team leaders from admin's projects only
$team_leaders_query = "SELECT DISTINCT u.* FROM users u
                      JOIN project_members pm ON u.user_id = pm.user_id
                      WHERE pm.role_in_project = 'team_leader' 
                      AND pm.project_id IN ($project_placeholders)
                      AND u.is_active = 1
                      ORDER BY u.first_name, u.last_name";
$team_leaders_stmt = $db->prepare($team_leaders_query);
$team_leaders_stmt->execute($admin_projects);
$team_leaders = $team_leaders_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get employees from admin's projects only
$employees_query = "SELECT DISTINCT u.* FROM users u
                   JOIN project_members pm ON u.user_id = pm.user_id
                   WHERE pm.role_in_project = 'employee' 
                   AND pm.project_id IN ($project_placeholders)
                   AND u.is_active = 1
                   ORDER BY u.first_name, u.last_name";
$employees_stmt = $db->prepare($employees_query);
$employees_stmt->execute($admin_projects);
$employees = $employees_stmt->fetchAll(PDO::FETCH_ASSOC);

// Structure data for organization chart
$result = [
    'admin' => $admin,
    'managers' => [],
    'team_leaders' => [],
    'employees' => []
];

// Add projects to managers (only from admin's projects)
foreach ($managers as &$manager) {
    $projects_query = "SELECT p.*, pm.role_in_project FROM projects p
                      JOIN project_members pm ON p.project_id = pm.project_id
                      WHERE pm.user_id = ? AND pm.role_in_project = 'manager'
                      AND p.admin_id = ?";
    $projects_stmt = $db->prepare($projects_query);
    $projects_stmt->execute([$manager['user_id'], $admin_id]);
    $manager['projects'] = $projects_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get team leaders under this manager (only from admin's projects)
    $tl_query = "SELECT DISTINCT u.* FROM users u
                JOIN project_members pm ON u.user_id = pm.user_id
                JOIN project_members pm2 ON pm.project_id = pm2.project_id
                JOIN projects p ON pm.project_id = p.project_id
                WHERE pm.role_in_project = 'team_leader' 
                AND pm2.user_id = ? AND pm2.role_in_project = 'manager'
                AND p.admin_id = ?
                AND u.is_active = 1";
    $tl_stmt = $db->prepare($tl_query);
    $tl_stmt->execute([$manager['user_id'], $admin_id]);
    $manager['team_leaders'] = $tl_stmt->fetchAll(PDO::FETCH_ASSOC);
}
$result['managers'] = $managers;

// Add employees to team leaders (only from admin's projects)
foreach ($team_leaders as &$leader) {
    $emp_query = "SELECT DISTINCT u.* FROM users u
                 JOIN project_members pm ON u.user_id = pm.user_id
                 JOIN project_members pm2 ON pm.project_id = pm2.project_id
                 JOIN projects p ON pm.project_id = p.project_id
                 WHERE pm.role_in_project = 'employee'
                 AND pm2.user_id = ? AND pm2.role_in_project = 'team_leader'
                 AND p.admin_id = ?
                 AND u.is_active = 1";
    $emp_stmt = $db->prepare($emp_query);
    $emp_stmt->execute([$leader['user_id'], $admin_id]);
    $leader['employees'] = $emp_stmt->fetchAll(PDO::FETCH_ASSOC);
}
$result['team_leaders'] = $team_leaders;

echo json_encode($result);
?>