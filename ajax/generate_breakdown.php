<?php
// ajax/generate_breakdown.php - AI Task Breakdown using Groq API
header('Content-Type: application/json');

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check authentication
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$task_id = isset($_GET['task_id']) ? intval($_GET['task_id']) : 0;

if ($task_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid task ID']);
    exit();
}

try {
    // Include database config
    require_once '../config/database.php';
    
    // Get database connection
    $database = new Database();
    $db = $database->getConnection();
    
    // Get task details
    $query = "SELECT t.title, t.description, p.project_name 
              FROM tasks t 
              LEFT JOIN projects p ON t.project_id = p.project_id 
              WHERE t.task_id = :task_id";
    
    $stmt = $db->prepare($query);
    $stmt->execute(['task_id' => $task_id]);
    $task = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$task) {
        echo json_encode(['success' => false, 'message' => 'Task not found']);
        exit();
    }
    
    // Groq API Key - Configure via environment variable or replace with your actual key
    $api_key = getenv('GROQ_API_KEY') ?: 'YOUR_GROQ_API_KEY_HERE';
    
    // Prepare prompt
    $prompt = "Break down this task into simple actionable steps (max 120 words):\n\nTask: " . $task['title'];
    if (!empty($task['description'])) {
        $prompt .= "\nDescription: " . $task['description'];
    }
    $prompt .= "\n\nProvide a clear, numbered breakdown of subtasks.";
    
    // Call Groq API
    $ch = curl_init();
    
    curl_setopt_array($ch, [
        CURLOPT_URL => 'https://api.groq.com/openai/v1/chat/completions',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $api_key,
            'Content-Type: application/json'
        ],
        CURLOPT_POSTFIELDS => json_encode([
            'model' => 'llama-3.1-8b-instant',
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'You are a helpful project manager. Provide concise task breakdowns in 120 words or less. Use numbered steps.'
                ],
                [
                    'role' => 'user',
                    'content' => $prompt
                ]
            ],
            'max_tokens' => 200,
            'temperature' => 0.7
        ])
    ]);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code !== 200) {
        echo json_encode([
            'success' => false, 
            'message' => 'API request failed',
            'task_title' => $task['title']
        ]);
        exit();
    }
    
    $result = json_decode($response, true);
    
    if (isset($result['choices'][0]['message']['content'])) {
        $breakdown = $result['choices'][0]['message']['content'];
        
        echo json_encode([
            'success' => true,
            'task_title' => $task['title'],
            'task_description' => $task['description'] ?? '',
            'breakdown' => $breakdown
        ]);
    } else {
        echo json_encode([
            'success' => false, 
            'message' => 'Invalid API response',
            'task_title' => $task['title']
        ]);
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false, 
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
?>