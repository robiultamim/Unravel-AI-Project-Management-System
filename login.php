<?php
// login.php
require_once 'config/database.php';
require_once 'config/session.php';

if (Session::isLoggedIn()) {
    header("Location: dashboard.php");
    exit();
}

$error = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $database = new Database();
    $db = $database->getConnection();
    
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $role = $_POST['role'] ?? 'employee';
    
    if (empty($username) || empty($password)) {
        $error = "Please enter username and password";
    } else {
        // Check user
        $query = "SELECT * FROM users WHERE username = ? AND is_active = TRUE";
        $stmt = $db->prepare($query);
        $stmt->execute([$username]);
        
        if ($stmt->rowCount() > 0) {
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (password_verify($password, $user['password_hash'])) {
                // Check if selected role matches user's actual role
                if ($role !== $user['role']) {
                    $error = "Invalid role selection for this user";
                } else {
                    // Set session variables
                    $_SESSION['user_id'] = $user['user_id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['role'] = $user['role'];
                    $_SESSION['first_name'] = $user['first_name'];
                    $_SESSION['last_name'] = $user['last_name'];
                    $_SESSION['email'] = $user['email'];
                    
                    // Update last login
                    $updateQuery = "UPDATE users SET last_login = NOW() WHERE user_id = ?";
                    $updateStmt = $db->prepare($updateQuery);
                    $updateStmt->execute([$user['user_id']]);
                    
                    // Redirect to dashboard
                    header("Location: dashboard.php");
                    exit();
                }
            } else {
                $error = "Invalid username or password";
            }
        } else {
            $error = "Invalid username or password";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Unravel - Login</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/auth.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="login-container">
        <div class="login-box">
            <div class="login-header">
                <a href="index.php" class="logo">
                    <i class="fas fa-tasks"></i> Unravel
                </a>
                <h1>Welcome Back</h1>
                <p>Sign in to manage your tasks efficiently</p>
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <div class="form-group">
                    <label for="username">Username</label> <br>
                    <input style="width: 100%; padding: 8px; box-sizing: border-box; border: 1px solid #ccc; border-radius: 4px;" placeholder="Enter your username" type="text" id="username" name="username" required 
                           value="<?php echo $_POST['username'] ?? ''; ?>">
                </div>
                
                <div class="form-group">
                    <label for="password">Password</label> <br>
                    <input style="width: 100%; padding: 8px; box-sizing: border-box; border: 1px solid #ccc; border-radius: 4px;" placeholder="Enter your password" type="password" id="password" name="password" required>
                </div>
                
                <div class="form-group">
                    <label>Login as:</label>
                    <div class="role-selection">
                        <label class="role-option">
                            <input type="radio" name="role" value="admin" required>
                            <div class="role-icon">
                                <i class="fas fa-crown"></i>
                                <span>Admin</span>
                            </div>
                        </label>
                        <label class="role-option">
                            <input type="radio" name="role" value="manager">
                            <div class="role-icon">
                                <i class="fas fa-user-tie"></i>
                                <span>Manager</span>
                            </div>
                        </label>
                        <label class="role-option">
                            <input type="radio" name="role" value="team_leader" checked>
                            <div class="role-icon">
                                <i class="fas fa-users-cog"></i>
                                <span>Team Leader</span>
                            </div>
                        </label>
                        <label class="role-option">
                            <input type="radio" name="role" value="employee">
                            <div class="role-icon">
                                <i class="fas fa-user"></i>
                                <span>Employee</span>
                            </div>
                        </label>
                    </div>
                </div>
                
                <div class="form-group">
                    <button type="submit" class="btn btn-primary btn-block">
                        <i class="fas fa-sign-in-alt"></i> Sign In
                    </button>
                </div>
                
                <div class="form-footer">
                    <p>Don't have an account? <a href="register.php">Register here</a></p>
                </div>
            </form>
        </div>
    </div>
    
    <script src="assets/js/auth.js"></script>
</body>
</html>