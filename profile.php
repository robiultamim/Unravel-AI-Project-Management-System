<?php
// profile.php
require_once 'config/database.php';
require_once 'config/session.php';
require_once 'includes/functions.php';

Session::redirectIfNotLoggedIn();

$database = new Database();
$db = $database->getConnection();

$user_id = $_SESSION['user_id'];

// Handle form submission
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['add_skill'])) {
        $skill_id = $_POST['skill_id'];
        $proficiency_level = $_POST['proficiency_level'];
        
        // Check if skill already exists for user
        $check_query = "SELECT id FROM user_skills WHERE user_id = :user_id AND skill_id = :skill_id";
        $check_stmt = $db->prepare($check_query);
        $check_stmt->execute(['user_id' => $user_id, 'skill_id' => $skill_id]);
        
        if ($check_stmt->rowCount() > 0) {
            // Update existing skill
            $update_query = "UPDATE user_skills SET proficiency_level = :proficiency_level 
                            WHERE user_id = :user_id AND skill_id = :skill_id";
            $update_stmt = $db->prepare($update_query);
            $update_stmt->execute([
                'proficiency_level' => $proficiency_level,
                'user_id' => $user_id,
                'skill_id' => $skill_id
            ]);
            $message = 'Skill updated successfully!';
            $message_type = 'success';
        } else {
            // Insert new skill
            $insert_query = "INSERT INTO user_skills (user_id, skill_id, proficiency_level) 
                            VALUES (:user_id, :skill_id, :proficiency_level)";
            $insert_stmt = $db->prepare($insert_query);
            $insert_stmt->execute([
                'user_id' => $user_id,
                'skill_id' => $skill_id,
                'proficiency_level' => $proficiency_level
            ]);
            $message = 'Skill added successfully!';
            $message_type = 'success';
        }
    } elseif (isset($_POST['remove_skill'])) {
        $user_skill_id = $_POST['user_skill_id'];
        
        $delete_query = "DELETE FROM user_skills WHERE id = :id AND user_id = :user_id";
        $delete_stmt = $db->prepare($delete_query);
        $delete_stmt->execute(['id' => $user_skill_id, 'user_id' => $user_id]);
        
        $message = 'Skill removed successfully!';
        $message_type = 'success';
    }
}

// Get user information
$user_query = "SELECT * FROM users WHERE user_id = :user_id";
$user_stmt = $db->prepare($user_query);
$user_stmt->execute(['user_id' => $user_id]);
$user = $user_stmt->fetch(PDO::FETCH_ASSOC);

// Get user's skills with skill details
$skills_query = "SELECT us.id, us.skill_id, us.proficiency_level, s.skill_name, s.category 
                 FROM user_skills us 
                 JOIN skills s ON us.skill_id = s.skill_id 
                 WHERE us.user_id = :user_id 
                 ORDER BY s.category, s.skill_name";
$skills_stmt = $db->prepare($skills_query);
$skills_stmt->execute(['user_id' => $user_id]);
$user_skills = $skills_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get all available skills
$all_skills_query = "SELECT * FROM skills ORDER BY category, skill_name";
$all_skills_stmt = $db->prepare($all_skills_query);
$all_skills_stmt->execute();
$all_skills = $all_skills_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get user statistics
$stats_query = "SELECT 
                (SELECT COUNT(*) FROM tasks WHERE assigned_to = :user_id) as total_tasks,
                (SELECT COUNT(*) FROM tasks WHERE assigned_to = :user_id AND status IN ('done', 'approved')) as completed_tasks,
                (SELECT COUNT(*) FROM tasks WHERE assigned_to = :user_id AND status = 'in_progress') as active_tasks,
                (SELECT COALESCE(SUM(actual_hours), 0) FROM tasks WHERE assigned_to = :user_id) as total_hours";
$stats_stmt = $db->prepare($stats_query);
$stats_stmt->execute(['user_id' => $user_id]);
$stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - Unravel</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/dashboard.css">
    <link rel="stylesheet" href="assets/css/profile.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="app-container">
        <?php include 'includes/sidebar.php'; ?>
        
        <div class="main-content">
            <?php include 'includes/header.php'; ?>
            
            <div class="content-wrapper">
                <?php if ($message): ?>
                    <div class="alert alert-<?php echo $message_type; ?>">
                        <?php echo htmlspecialchars($message); ?>
                    </div>
                <?php endif; ?>
                
                <div class="profile-container">
                    <!-- User Info Card -->
                    <div class="profile-card">
                        <div class="profile-header">

                            <div class="profile-info">
                                <h2><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></h2>
                                <p class="profile-role"><?php echo ucfirst(str_replace('_', ' ', $user['role'])); ?></p>
                                <p class="profile-department"><?php echo htmlspecialchars($user['department'] ?? 'N/A'); ?></p>
                                <p class="profile-email"><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($user['email']); ?></p>
                            </div>
                        </div>
                        
                        <!-- User Statistics -->
                        <div class="profile-stats">
                            <div class="stat-item">
                                <div class="stat-value"><?php echo $stats['total_tasks']; ?></div>
                                <div class="stat-label">Total Tasks</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-value"><?php echo $stats['completed_tasks']; ?></div>
                                <div class="stat-label">Completed</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-value"><?php echo $stats['active_tasks']; ?></div>
                                <div class="stat-label">Active Tasks</div>
                            </div>

                        </div>
                    </div>
                    
                    <!-- Skills Section -->
                    <div class="skills-section">
                        <div class="section-header">
                            <h3><i class="fas fa-brain"></i> My Skills</h3>
                        </div>
                        
                        <!-- Add Skill Form -->
                        <div class="add-skill-card">
                            <h4>Add New Skill</h4>
                            <form method="POST" class="add-skill-form">
                                <div class="form-group">
                                    <label for="skill_id">Select Skill</label>
                                    <select name="skill_id" id="skill_id" class="form-control" required>
                                        <option value="">-- Choose a skill --</option>
                                        <?php 
                                        $current_category = '';
                                        foreach ($all_skills as $skill): 
                                            if ($current_category != $skill['category']) {
                                                if ($current_category != '') echo '</optgroup>';
                                                echo '<optgroup label="' . htmlspecialchars($skill['category']) . '">';
                                                $current_category = $skill['category'];
                                            }
                                        ?>
                                            <option value="<?php echo $skill['skill_id']; ?>">
                                                <?php echo htmlspecialchars($skill['skill_name']); ?>
                                            </option>
                                        <?php 
                                        endforeach; 
                                        if ($current_category != '') echo '</optgroup>';
                                        ?>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label for="proficiency_level">Proficiency Level (1-5)</label>
                                    <div class="level-selector">
                                        <input type="radio" name="proficiency_level" id="level1" value="1" required>
                                        <label for="level1" class="level-label" title="Beginner">
                                            <i class="fas fa-star"></i>
                                            <span>1</span>
                                        </label>
                                        
                                        <input type="radio" name="proficiency_level" id="level2" value="2">
                                        <label for="level2" class="level-label" title="Elementary">
                                            <i class="fas fa-star"></i>
                                            <span>2</span>
                                        </label>
                                        
                                        <input type="radio" name="proficiency_level" id="level3" value="3" checked>
                                        <label for="level3" class="level-label" title="Intermediate">
                                            <i class="fas fa-star"></i>
                                            <span>3</span>
                                        </label>
                                        
                                        <input type="radio" name="proficiency_level" id="level4" value="4">
                                        <label for="level4" class="level-label" title="Advanced">
                                            <i class="fas fa-star"></i>
                                            <span>4</span>
                                        </label>
                                        
                                        <input type="radio" name="proficiency_level" id="level5" value="5">
                                        <label for="level5" class="level-label" title="Expert">
                                            <i class="fas fa-star"></i>
                                            <span>5</span>
                                        </label>
                                    </div>
                                    <div class="level-description">
                                        <small>
                                            <strong>1:</strong> Beginner | 
                                            <strong>2:</strong> Elementary | 
                                            <strong>3:</strong> Intermediate | 
                                            <strong>4:</strong> Advanced | 
                                            <strong>5:</strong> Expert
                                        </small>
                                    </div>
                                </div>
                                
                                <button type="submit" name="add_skill" class="btn btn-primary">
                                    <i class="fas fa-plus"></i> Add Skill
                                </button>
                            </form>
                        </div>
                        
                        <!-- Current Skills -->
                        <div class="current-skills">
                            <h4>Current Skills</h4>
                            <?php if (empty($user_skills)): ?>
                                <div class="empty-state">
                                    <i class="fas fa-info-circle"></i>
                                    <p>No skills added yet. Add your first skill to get started!</p>
                                </div>
                            <?php else: ?>
                                <div class="skills-grid">
                                    <?php 
                                    $skills_by_category = [];
                                    foreach ($user_skills as $skill) {
                                        $skills_by_category[$skill['category']][] = $skill;
                                    }
                                    
                                    foreach ($skills_by_category as $category => $skills): 
                                    ?>
                                        <div class="skill-category">
                                            <h5 class="category-title"><?php echo htmlspecialchars($category); ?></h5>
                                            <?php foreach ($skills as $skill): ?>
                                                <div class="skill-item">
                                                    <div class="skill-header">
                                                        <span class="skill-name">
                                                            <?php echo htmlspecialchars($skill['skill_name']); ?>
                                                        </span>
                                                        <form method="POST" style="display: inline;">
                                                            <input type="hidden" name="user_skill_id" value="<?php echo $skill['id']; ?>">
                                                            <button type="submit" name="remove_skill" class="btn-remove" 
                                                                    onclick="return confirm('Are you sure you want to remove this skill?');">
                                                                <i class="fas fa-times"></i>
                                                            </button>
                                                        </form>
                                                    </div>
                                                    <div class="skill-level">
                                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                                            <i class="fas fa-star <?php echo $i <= $skill['proficiency_level'] ? 'filled' : ''; ?>"></i>
                                                        <?php endfor; ?>
                                                        <span class="level-text">
                                                            Level <?php echo $skill['proficiency_level']; ?>/5
                                                        </span>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            
        </div>
    </div>
</body>
</html>
