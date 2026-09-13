<?php
/**
 * AJAX endpoint for wellbeing AI chat
 * Used by popup and mobile versions
 */
session_start();
require_once __DIR__ . '/../config.php';

// Allow both youth users and organization presidents
$isYouth = !empty($_SESSION['user_id']);
$isPresident = !empty($_SESSION['org_president_id']);

if (!$isYouth && !$isPresident) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$pdo = db();

// Get user info based on login type
if ($isPresident) {
    $president = $_SESSION['org_president'];
    $userName = $president['full_name'];
    $userType = 'president';
    $userId = $president['id'];
    $user = $president;
} else {
    $userId = (int)$_SESSION['user_id'];
    $uStmt = $pdo->prepare('SELECT * FROM youth_users WHERE id=? LIMIT 1');
    $uStmt->execute([$userId]);
    $user = $uStmt->fetch();
    $userName = $user['first_name'] . ' ' . $user['last_name'];
    $userType = 'youth';
}

// AJAX: process message
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_chat'])) {
    header('Content-Type: application/json');
    
    $msg = trim($_POST['message'] ?? '');
    if (!$msg || mb_strlen($msg) > 1000) {
        echo json_encode(['reply' => 'Please send a valid message (max 1000 characters).']);
        exit;
    }

    $firstName = $user['first_name'] ?? $user['full_name'] ?? 'Friend';
    
    // Call AI
    $reply = callGroqAI($msg, $firstName);

    echo json_encode(['reply' => $reply, 'success' => true]);
    exit;
}

// AI Response Function using Groq API
function callGroqAI(string $input, string $name): string {
    $apiKey = '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';
    $model = 'openai/gpt-oss-20b';
    
    $systemPrompt = "You are LYDO's Well-being Assistant, a caring and professional mental health support chatbot for Filipino youth and community leaders.

**Your Role:**
- Provide empathetic, practical mental health support
- Answer ANY question - not just mental health topics
- Be helpful, informative, and supportive
- Use a warm, conversational tone

**Guidelines:**
- Address the user as '{$name}'
- Keep responses under 500 words
- Be culturally sensitive to Filipino context
- Offer practical, actionable advice
- For serious concerns, suggest professional help
- You can discuss any topic: academics, relationships, career, technology, etc.
- Always be encouraging and positive

**Response Style:**
- Use **bold** for emphasis
- Include bullet points when listing tips
- Use emojis appropriately
- End with encouraging words

**Important:** Provide helpful, accurate information while maintaining your supportive tone.";
    
    $data = [
        'model' => $model,
        'messages' => [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => $input]
        ],
        'temperature' => 0.7,
        'max_tokens' => 800,
        'top_p' => 0.9
    ];

    $ch = curl_init('https://api.groq.com/openai/v1/chat/completions');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($data),
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $apiKey,
            'Content-Type: application/json'
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => 0
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200 && $response) {
        $decoded = json_decode($response, true);
        if (isset($decoded['choices'][0]['message']['content'])) {
            return $decoded['choices'][0]['message']['content'];
        }
    }
    
    // Fallback response if AI fails
    return "I hear you, **{$name}**. 💙 I'd love to help you with that. Could you tell me more about what you're experiencing? I'm here to listen and support you.";
}
