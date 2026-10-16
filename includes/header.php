<!-- header.php -->
<div class="header">
    <div class="header-left">
        <div class="page-title">
            <?php
            $page_titles = [
                'dashboard.php' => 'Dashboard',
                'kanban.php' => 'Kanban Board',
                'gantt.php' => 'Gantt Chart',
                'tasks.php' => 'Tasks',
                'hierarchy.php' => 'Hierarchy Management',
                'users.php' => 'User Management',
                'team-performance.php' => 'Team Performance',
                'work-summary.php' => 'Work Summary',
                'profile.php' => 'My Profile',
                'notifications.php' => 'Notifications',
                'settings.php' => 'System Settings',
                'reports.php' => 'Reports'
            ];
            echo $page_titles[basename($_SERVER['PHP_SELF'])] ?? 'Dashboard';
            ?>
        </div>
    </div>
    
    <div class="header-right" >
        <div class="header-actions" style="display: flex; align-items: center;">
            
            <!-- Notifications -->
            <!-- <div class="notification-bell">
                <i class="fas fa-bell"></i>
                <span class="notification-badge"></span>
            </div> -->
            
            <!-- User info -->
            <div class="user-info">
                <div class="user-avatar">
                    <?php echo strtoupper(substr($_SESSION['first_name'], 0, 1) . substr($_SESSION['last_name'], 0, 1)); ?>
                </div>
                <div class="user-details">
                    <div class="user-name"><?php echo htmlspecialchars($_SESSION['first_name'] . ' ' . $_SESSION['last_name']); ?></div>
                    <div class="user-role"><?php echo ucfirst(str_replace('_', ' ', $_SESSION['role'])); ?></div>
                </div>
            </div>
        </div>
    </div>
</div>