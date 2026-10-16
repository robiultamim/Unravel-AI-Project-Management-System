<!-- sidebar.php -->
<div class="sidebar">
    <div class="sidebar-header">
        <a href="dashboard.php" class="logo">
            <i class="fas fa-tasks"></i>Unravel
        </a>

    </div>
    <div class="sidebar-menu">
        <a href="dashboard.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>">
            <i class="fas fa-home"></i>Dashboard
        </a>
        <a href="kanban.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'kanban.php' ? 'active' : ''; ?>">
            <i class="fas fa-columns"></i>Kanban Board
        </a>
        <a href="gantt.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'gantt.php' ? 'active' : ''; ?>">
            <i class="fas fa-chart-bar"></i>Gantt Chart
        </a>
        <a href="tasks.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'tasks.php' ? 'active' : ''; ?>">
            <i class="fas fa-tasks"></i>Tasks
        </a>
        
        <?php if ($_SESSION['role'] == 'admin'): ?>
            <a href="hierarchy.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'hierarchy.php' ? 'active' : ''; ?>">
                <i class="fas fa-sitemap"></i>Hierarchy Management
            </a>

        <?php endif; ?>
        
        <?php if ($_SESSION['role'] == 'manager'): ?>
            <a href="manager-projects.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'manager-projects.php' ? 'active' : ''; ?>">
                <i class="fas fa-sitemap"></i>Project Management
            </a>
        <?php endif; ?>
        
        <?php if (in_array($_SESSION['role'], ['admin', 'manager', 'team_leader'])): ?>
            <a href="reports.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'reports.php' ? 'active' : ''; ?>">
                <i class="fas fa-chart-pie"></i>Reports
            </a>
        <?php endif; ?>
        
        <a href="notifications.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'notifications.php' ? 'active' : ''; ?>"
            <i class="fas fa-bell"></i>Notifications
        </a>
        

        
        <!-- SOS Links -->
        <?php if ($_SESSION['role'] == 'employee'): ?>
        <a href="sos-employee.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'sos-employee.php' ? 'active' : ''; ?>">
            <i class="fas fa-life-ring"></i>I'm Stuck (SOS)
        </a>
        <?php endif; ?>
        
        <?php if (in_array($_SESSION['role'], ['team_leader'])): ?>
        <a href="sos-team-leader.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'sos-team-leader.php' ? 'active' : ''; ?>">
            <i class="fas fa-life-ring"></i>SOS Management
        </a>
        <?php endif; ?>
        
        <!-- only for employee -->
        <?php if ($_SESSION['role'] == 'employee'): ?>
        <a href="profile.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'profile.php' ? 'active' : ''; ?>">
            <i class="fas fa-user-circle"></i>My Profile
        </a>
        <?php endif; ?>
        
        <a href="logout.php" class="menu-item">
            <i class="fas fa-sign-out-alt"></i>Logout
        </a>
    </div>
</div>