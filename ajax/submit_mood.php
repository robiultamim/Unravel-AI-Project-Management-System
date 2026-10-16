<?php
// ajax/submit_mood.php
require_once '../config/database.php';
require_once '../config/session.php';

if (!isLoggedIn()) {
    http_response_code(401);
    echo 'Please login first';
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['mood'])) {
    $database = new Database();
    $db = $database->getConnection();
    
    $mood = $_POST['mood'];
    $user_id = $_SESSION['user_id'];
    
    // Check if already checked in today
    $query = "SELECT * FROM mood_checkins 
              WHERE user_id = ? 
              AND DATE(created_at) = CURDATE()";
    $stmt = $db->prepare($query);
    $stmt->execute([$user_id]);
    
    if ($stmt->rowCount() > 0) {
        // Update existing checkin
        $query = "UPDATE mood_checkins SET mood = ?, created_at = NOW() 
                  WHERE user_id = ? AND DATE(created_at) = CURDATE()";
        $stmt = $db->prepare($query);
        $stmt->execute([$mood, $user_id]);
    } else {
        // Insert new checkin
        $query = "INSERT INTO mood_checkins (user_id, mood) VALUES (?, ?)";
        $stmt = $db->prepare($query);
        $stmt->execute([$user_id, $mood]);
    }
    
    // Get team mood stats
    $query = "SELECT mood, COUNT(*) as count FROM mood_checkins 
              WHERE DATE(created_at) = CURDATE()
              GROUP BY mood";
    $stmt = $db->query($query);
    $mood_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $total = array_sum(array_column($mood_stats, 'count'));
    $positive = 0;
    
    foreach ($mood_stats as $stat) {
        if (in_array($stat['mood'], ['happy', 'excited'])) {
            $positive += $stat['count'];
        }
    }
    
    $positive_percentage = $total > 0 ? round(($positive / $total) * 100) : 0;
    
    echo "Mood recorded! $positive_percentage% of team is feeling positive today";
} else {
    http_response_code(400);
    echo 'Invalid request';
}
?>