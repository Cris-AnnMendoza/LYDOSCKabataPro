<?php
session_start();
require_once __DIR__ . '/../config.php';

// Allow both youth users and organization presidents
$isYouth = !empty($_SESSION['user_id']);
$isPresident = !empty($_SESSION['org_president_id']);

if (!$isYouth && !$isPresident) {
    header('Location: ../../login.php');
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

// Get notification count based on user type
$notifCount = 0;
try {
    if ($isPresident) {
        $nc = $pdo->prepare('SELECT COUNT(*) FROM org_president_notifications WHERE president_id=? AND is_read=FALSE');
        $nc->execute([$userId]);
    } else {
        $nc = $pdo->prepare('SELECT COUNT(*) FROM notifications WHERE user_id=? AND is_read=FALSE');
        $nc->execute([$userId]);
    }
    $notifCount = (int)$nc->fetchColumn();
} catch (PDOException $e) {
    // Table doesn't exist yet, set to 0
    $notifCount = 0;
}

// Ensure chat history table
$pdo->exec("CREATE TABLE IF NOT EXISTS wellbeing_chats (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id    INT UNSIGNED NOT NULL,
    user_type  ENUM('youth','president') NOT NULL DEFAULT 'youth',
    role       ENUM('user','bot') NOT NULL,
    message    TEXT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user (user_id, user_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Add user_type column if it doesn't exist
$pdo->exec("ALTER TABLE wellbeing_chats ADD COLUMN IF NOT EXISTS user_type ENUM('youth','president') NOT NULL DEFAULT 'youth' AFTER user_id");

// AJAX: process message
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_chat'])) {
    header('Content-Type: application/json');
    
    // Enable error reporting for debugging
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    
    $msg = trim($_POST['message'] ?? '');
    if (!$msg || mb_strlen($msg) > 1000) {
        echo json_encode(['reply' => 'Please send a valid message (max 1000 characters).', 'error' => 'Invalid message', 'debug' => 'Message validation failed']);
        exit;
    }

    // Save user message
    try {
        $pdo->prepare('INSERT INTO wellbeing_chats (user_id,user_type,role,message) VALUES (?,?,?,?)')
            ->execute([$userId, $userType, 'user', $msg]);
    } catch (Exception $e) {
        echo json_encode(['reply' => 'Error saving message: ' . $e->getMessage(), 'error' => $e->getMessage(), 'debug' => 'Database save failed']);
        exit;
    }
    
    try {
        // Generate AI response
        $reply = generateWellbeingReply($msg, $user, $pdo, $userId, $userType);
        
        // Debug: Check if reply is empty
        if (empty($reply)) {
            error_log('AI returned empty response, using fallback');
            $firstName = $user['first_name'] ?? $user['full_name'] ?? 'Friend';
            $reply = getFallbackResponse($msg, $firstName);
        }

        // Save bot reply
        $pdo->prepare('INSERT INTO wellbeing_chats (user_id,user_type,role,message) VALUES (?,?,?,?)')
            ->execute([$userId, $userType, 'bot', $reply]);

        echo json_encode([
            'reply' => $reply, 
            'success' => true,
            'debug' => 'Response generated successfully',
            'reply_length' => strlen($reply)
        ]);
    } catch (Exception $e) {
        error_log('Wellbeing chat error: ' . $e->getMessage());
        $firstName = $user['first_name'] ?? $user['full_name'] ?? 'Friend';
        $fallback = getFallbackResponse($msg, $firstName);
        
        // Save fallback response
        try {
            $pdo->prepare('INSERT INTO wellbeing_chats (user_id,user_type,role,message) VALUES (?,?,?,?)')
                ->execute([$userId, $userType, 'bot', $fallback]);
        } catch (Exception $e2) {
            // Ignore save error
        }
        
        echo json_encode([
            'reply' => $fallback, 
            'success' => false, 
            'error' => $e->getMessage(),
            'debug' => 'Exception caught: ' . $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
    }
    exit;
}

// AJAX: clear history
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['clear_chat'])) {
    header('Content-Type: application/json');
    $pdo->prepare('DELETE FROM wellbeing_chats WHERE user_id=? AND user_type=?')->execute([$userId, $userType]);
    echo json_encode(['ok' => true]);
    exit;
}

// Load recent chat history
$histStmt = $pdo->prepare('SELECT role, message, created_at FROM wellbeing_chats WHERE user_id=? AND user_type=? ORDER BY created_at ASC LIMIT 100');
$histStmt->execute([$userId, $userType]);
$history = $histStmt->fetchAll();

$firstName = $user['first_name'] ?? $user['full_name'] ?? 'Friend';

// ════════════════════════════════════════════════════════
// INTELLIGENT AI WELL-BEING ASSISTANT ENGINE
// ════════════════════════════════════════════════════════
function generateWellbeingReply(string $input, array $user, PDO $pdo, int $userId, string $userType = 'youth'): string {
    $text = mb_strtolower($input);
    $name = $user['first_name'] ?? $user['full_name'] ?? 'Friend';

    // Crisis detection (highest priority)
    $crisisWords = ['suicid','kill myself','end my life','want to die','no reason to live','self harm','hurt myself','cut myself','overdose','wala na akong dahilan','gusto ko nang mamatay','ayaw ko na mabuhay','papatayin ko sarili'];
    foreach ($crisisWords as $w) {
        if (str_contains($text, $w)) {
            // Crisis handling - alert admins
            $coordStmt = $pdo->prepare("SELECT full_name, email FROM admin_users WHERE role IN ('super_admin','youth_coordinator') AND is_active = TRUE ORDER BY role ASC LIMIT 3");
            $coordStmt->execute();
            $coords = $coordStmt->fetchAll();

            $alertMsg = '🚨 URGENT: ' . ucfirst($userType) . ' user ' . ($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? $user['full_name'] ?? '') . ' (ID #' . $userId . ') may be in crisis. Please check in immediately.';
            $adminStmt = $pdo->prepare("SELECT id FROM admin_users WHERE is_active = TRUE");
            $adminStmt->execute();
            $adminIds = $adminStmt->fetchAll(PDO::FETCH_COLUMN);
            
            foreach ($adminIds as $aid) {
                try {
                    $pdo->prepare("INSERT INTO notifications (user_id, title, message, type) VALUES (?, ?, ?, ?)")
                        ->execute([$aid, '🚨 Crisis Alert', $alertMsg, 'error']);
                } catch (PDOException $e) {}
            }
            
            try {
                $pdo->prepare("INSERT INTO wellbeing_chats (user_id, user_type, role, message) VALUES (?, ?, 'bot', ?)")
                    ->execute([$userId, $userType, '[SYSTEM ALERT: Crisis keywords detected. Admin notified.]']);
            } catch (PDOException $e) {}
            $coordList = '';
            if (!empty($coords)) {
                $coordList = "\n\n**Your LYDO Assistants are here for you:**\n";
                foreach ($coords as $c) {
                    $coordList .= "👤 **{$c['full_name']}** — {$c['email']}\n";
                }
            } else {
                $coordList = "\n\nPlease contact the LYDO office directly for immediate assistance.";
            }

            return "💙 **{$name}, I'm really concerned about what you just shared.**\n\n" .
                   "Please know that **you are not alone**, and what you're feeling right now is real. Help is available — right now.\n\n" .
                   "🚨 **Please reach out immediately:**\n\n" .
                   "📞 **Hopeline PH:** 1553 (24/7, free)\n" .
                   "📞 **NCMH Crisis Line:** (02) 8989-8727\n" .
                   "📞 **Emergency:** 911\n" .
                   $coordList . "\n\n" .
                   "---\n" .
                   "✅ **I have already alerted your LYDO assistants.** A coordinator will reach out to check on you.\n\n" .
                   "You matter. Your life has value. Please talk to someone right now — a family member, friend, teacher, or any of the contacts above. 🙏";
        }
    }

    // For all other questions, call AI directly
    return callGroqAPI($input, $name, $userType) ?? getFallbackResponse($input, $name);
}

// Groq AI API Call
function callGroqAPI(string $input, string $name, string $userType): ?string {
    // Hardcode API key for testing - CHANGE THIS LATER TO USE .ENV
    $apiKey = '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';
    
    error_log("callGroqAPI called with input: " . substr($input, 0, 50));
    error_log("API Key: " . substr($apiKey, 0, 15) . '...');
    
    if (!$apiKey) {
        error_log('GROQ API KEY not found');
        return null;
    }

    $systemPrompt = createWellbeingSystemPrompt($name, $userType);
    
    // Use the correct working model
    $model = 'openai/gpt-oss-20b';
    
    error_log("Calling Groq API with model: $model");
    
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
    $curlError = curl_error($ch);
    curl_close($ch);
    
    if ($curlError) {
        error_log("CURL Error: $curlError");
        return null;
    }
    
    error_log("HTTP Code: $httpCode");
    
    if ($httpCode === 200 && $response) {
        $decoded = json_decode($response, true);
        if (isset($decoded['choices'][0]['message']['content'])) {
            $content = $decoded['choices'][0]['message']['content'];
            error_log("Success! Response length: " . strlen($content));
            return $content;
        }
        error_log("Response missing content: " . substr($response, 0, 300));
    } else {
        error_log("API Error - HTTP $httpCode: " . substr($response, 0, 300));
    }
    
    return null;
}

// System Prompt for AI
function createWellbeingSystemPrompt(string $name, string $userType): string {
    return "You are LYDO's Well-being Assistant, a caring and professional mental health support chatbot for Filipino youth and community leaders.

**Your Role:**
- Provide empathetic, practical mental health support 
- Answer ANY question - not just mental health topics
- Be helpful, informative, and supportive for any topic
- Use a warm, conversational tone
- Include relevant emojis and formatting

**Guidelines:**
- Address the user as '{$name}'
- Keep responses under 500 words
- Be culturally sensitive to Filipino context
- Offer practical, actionable advice
- For serious mental health concerns, suggest professional help
- You can discuss any topic: academics, relationships, career, hobbies, current events, technology, etc.
- Always be encouraging and positive
- Use simple language that youth can understand

**Context:**
- User type: {$userType}
- Organization: LYDO (Local Youth Development Organization) Sta. Cruz, Laguna
- Available programs: Education/Scholarship, Livelihood, Health & Wellness, Volunteer

**Response Style:**
- Use **bold** for emphasis
- Include bullet points when listing tips
- Use emojis appropriately
- End with encouraging words or questions to continue conversation

**Important:** You can help with ANY question or topic, not just mental health. Whether they ask about technology, science, entertainment, school subjects, relationships, career advice, hobbies, or anything else - provide helpful, accurate information while maintaining your supportive tone.";
}

// Fallback Responses (if AI fails)
function getFallbackResponse(string $input, string $name): string {
    $text = mb_strtolower($input);
    
    // Greetings
    if (matchAny($text, ['hello','hi','hey','good morning','good afternoon','good evening','kumusta','kamusta','musta'])) {
        return "Hello, **{$name}**! 😊 I'm your LYDO Well-being Assistant powered by AI. I'm here to help you with:\n\n• 💙 **Mental health support** - stress, anxiety, sadness\n• 📚 **Academic advice** - study tips, school concerns\n• 💼 **Career guidance** - planning your future\n• 💻 **Technology questions** - any tech topic\n• 💑 **Relationships** - family, friends, romance\n• 🌟 **Personal growth** - building confidence, setting goals\n\nWhat would you like to talk about today?";
    }
    
    // Stress/Anxiety
    if (matchAny($text, ['stress','pressure','overwhelm','worried','anxiety','anxious','nervous','takot','kinakabahan'])) {
        return "I hear you, **{$name}**. Stress and anxiety are tough, but you're not alone. 💙\n\n**Here are some quick tips:**\n\n• **Breathe** - Take 5 deep breaths: in for 4 counts, hold for 4, out for 4\n• **Break it down** - What's making you stressed? List them and tackle one at a time\n• **Move your body** - Even 10 minutes of walking helps\n• **Talk to someone** - A friend, family member, or counselor\n• **Rest** - Your brain needs breaks too\n\n**Remember:** It's okay to not be okay sometimes. Take it one step at a time. You're stronger than you think! 💪\n\nWant to talk more about what's causing the stress?";
    }
    
    // Sadness
    if (matchAny($text, ['sad','depress','down','lonely','malungkot','alone'])) {
        return "I'm sorry you're feeling this way, **{$name}**. Your feelings are valid. 💙\n\n**Things that might help:**\n\n• **Reach out** - Don't isolate yourself. Talk to someone you trust\n• **Do something small** - Take a shower, go outside, listen to music\n• **Write it out** - Journal your feelings\n• **Be kind to yourself** - You're doing your best\n• **Seek help** - If feelings persist, talk to a counselor\n\n**LYDO Resources:**\n• Youth Coordinator can connect you with support\n• We have peer support groups\n• Professional counseling referrals available\n\nWhat's been on your mind lately?";
    }
    
    // School/Academic
    if (matchAny($text, ['school','study','exam','grades','academic','pag-aaral','assignment','homework'])) {
        return "Let's talk about school, **{$name}**! 📚 What specifically are you struggling with?\n\n**Study tips that work:**\n\n• **Pomodoro Technique** - Study 25 min, break 5 min\n• **Active recall** - Quiz yourself instead of just reading\n• **Teach someone** - Best way to learn is to explain it\n• **Study groups** - Learn together with friends\n• **Break big topics** - Into smaller, manageable chunks\n\n**LYDO can help:**\n• Scholarship opportunities available\n• Tutoring program connections\n• Study skills workshops\n\nWhat subject or topic do you need help with?";
    }
    
    // Career
    if (matchAny($text, ['career','job','work','future','college','course','hanapbuhay'])) {
        return "Great question, **{$name}**! Planning your future is important. 💼\n\n**Career exploration tips:**\n\n• **Know yourself** - What are you passionate about?\n• **Research** - What careers match your interests?\n• **Try things** - Volunteer, intern, shadow professionals\n• **Network** - Talk to people in fields you like\n• **Stay flexible** - Your path may change, and that's okay!\n\n**LYDO Programs:**\n• Livelihood training opportunities\n• Career mentorship available\n• Skills development workshops\n• Job placement assistance\n\nWhat career path are you interested in?";
    }
    
    // Thank you
    if (matchAny($text, ['thank','thanks','salamat'])) {
        return "You're very welcome, **{$name}**! 💙 I'm always here whenever you need:\n• Someone to talk to\n• Advice or guidance\n• Just to chat about anything\n\nDon't hesitate to reach out anytime. Take care! 😊";
    }
    
    // Goodbye
    if (matchAny($text, ['bye','goodbye','paalam','see you'])) {
        return "Take care, **{$name}**! 💙 Remember:\n• You're not alone\n• LYDO is here for you\n• Come back anytime you need support\n\nHave a wonderful day! Stay strong! 🌟";
    }
    
    // Default intelligent response
    return "I'd love to help you with that, **{$name}**! 💙\n\nCould you tell me more about what you're asking? I can assist with:\n\n• 💙 Mental health & emotional support\n• 📚 School & academic concerns  \n• 💼 Career planning & advice\n• 💻 Technology questions\n• 💑 Relationship issues\n• 🌟 Personal development\n• ❓ Or anything else on your mind!\n\nWhat specific aspect would you like to discuss? The more details you share, the better I can help you. 😊";
}
function matchAny(string $text, array $keywords): bool {
    foreach ($keywords as $kw) {
        if (str_contains($text, $kw)) return true;
    }
    return false;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1"/>
<title>Well-being Assistant – LYDO Youth Portal</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet"/>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>
<link rel="stylesheet" href="youth.css"/>
<style>
/* ── MAIN LAYOUT FIXES ── */
.main-content {
    padding: 20px;
    display: flex;
    justify-content: center;
    align-items: flex-start;
    min-height: calc(100vh - 80px);
}

/* ── CHAT LAYOUT ── */
.chat-wrap {
    display: flex;
    flex-direction: column;
    height: calc(100vh - 140px);
    width: 100%;
    max-width: 900px;
    margin: 0 auto;
    border-radius: 16px;
    overflow: hidden;
    box-shadow: 0 10px 40px rgba(0,0,0,0.1);
    background: white;
}

.chat-header {
    background: linear-gradient(135deg, #0d3b6e, #1565c0);
    padding: 20px 24px;
    display: flex;
    align-items: center;
    gap: 16px;
    flex-shrink: 0;
}

.bot-avatar {
    width: 48px;
    height: 48px;
    border-radius: 50%;
    background: rgba(255,255,255,.2);
    border: 2px solid rgba(255,255,255,.4);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.4rem;
    flex-shrink: 0;
    color: white;
}

.bot-name {
    font-size: 1.1rem;
    font-weight: 800;
    color: #fff;
    margin: 0;
}

.bot-status {
    font-size: .8rem;
    color: rgba(255,255,255,.8);
    display: flex;
    align-items: center;
    gap: 6px;
    margin-top: 4px;
}

.status-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: #43a047;
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: .5; }
}

.chat-topics {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
    padding: 12px 20px;
    background: #f8fafc;
    border-bottom: 1px solid #e2e8f0;
    flex-shrink: 0;
}

.topic-chip {
    padding: 6px 14px;
    border-radius: 20px;
    border: 1.5px solid #e2e8f0;
    background: #fff;
    font-size: .8rem;
    font-weight: 600;
    color: #475569;
    cursor: pointer;
    transition: all .2s ease;
    white-space: nowrap;
}

.topic-chip:hover {
    border-color: #1565c0;
    background: #e3f2fd;
    color: #1565c0;
    transform: translateY(-1px);
}

/* ── MESSAGES ── */
.chat-messages{flex:1;overflow-y:auto;padding:20px 16px;background:#f8fafc}
.message{margin-bottom:16px;display:flex;gap:12px}
.message.user{flex-direction:row-reverse}
.message-avatar{width:36px;height:36px;border-radius:50%;display:flex;align-items:center;justify-content:center;flex-shrink:0;font-size:.9rem}
.message.user .message-avatar{background:#1565c0;color:#fff}
.message.bot .message-avatar{background:#e3f2fd;color:#1565c0}
.message-content{max-width:70%;padding:12px 16px;border-radius:18px;line-height:1.5;font-size:.9rem}
.message.user .message-content{background:#1565c0;color:#fff;border-bottom-right-radius:4px}
.message.bot .message-content{background:#fff;color:#2d3748;border:1px solid #e2e8f0;border-bottom-left-radius:4px}
.message-time{font-size:.7rem;color:#94a3b8;margin-top:4px;text-align:right}

/* ── CHAT INPUT ── */
.chat-input{display:flex;gap:8px;padding:16px;background:#fff;border-top:1px solid #e2e8f0;border-radius:0 0 16px 16px;flex-shrink:0}
.chat-input textarea{flex:1;border:1px solid #e2e8f0;border-radius:12px;padding:12px 16px;resize:none;font-family:inherit;font-size:.9rem;line-height:1.4;max-height:120px;outline:none}
.chat-input textarea:focus{border-color:#1565c0;box-shadow:0 0 0 3px rgba(21,101,192,.1)}
.send-btn{background:#1565c0;border:none;border-radius:50%;width:44px;height:44px;display:flex;align-items:center;justify-content:center;color:#fff;cursor:pointer;transition:.2s;flex-shrink:0}
.send-btn:hover{background:#0d47a1;transform:scale(1.05)}
.send-btn:disabled{background:#cbd5e1;cursor:not-allowed;transform:none}

/* ── UTILITIES ── */
.typing{display:none;align-items:center;gap:8px;padding:12px 16px;color:#64748b;font-size:.85rem;font-style:italic}
.typing.show{display:flex}
.typing-dots{display:flex;gap:2px}
.typing-dot{width:4px;height:4px;border-radius:50%;background:#94a3b8;animation:typing 1.4s infinite}
.typing-dot:nth-child(2){animation-delay:.2s}
.typing-dot:nth-child(3){animation-delay:.4s}
@keyframes typing{0%,60%,100%{opacity:.3}30%{opacity:1}}

.clear-btn{position:absolute;top:16px;right:16px;background:rgba(255,255,255,.9);border:1px solid #e2e8f0;border-radius:8px;padding:6px 12px;font-size:.75rem;color:#64748b;cursor:pointer;transition:.2s}
.clear-btn:hover{background:#fff;color:#1565c0}

/* ── RESPONSIVE ── */
@media(max-width:768px){
  .chat-wrap{height:calc(100vh - 80px);margin:0}
  .chat-header{border-radius:0;padding:12px 16px}
  .chat-messages{padding:16px 12px}
  .message-content{max-width:85%;font-size:.85rem}
  .chat-input{padding:12px;border-radius:0}
}
</style>
</head>
<body>

<?php require_once 'topbar.php' ?>

<div class="main-layout">
  <?php require_once 'sidebar.php' ?>
  
  <main class="main-content">
    <div class="chat-wrap">
      <!-- Chat Header -->
      <div class="chat-header">
        <div class="bot-avatar">
          <i class="fas fa-robot"></i>
        </div>
        <div>
          <div class="bot-name">LYDO Well-being Assistant</div>
          <div class="bot-status">
            <span class="status-dot"></span>
            Online • Powered by AI
          </div>
        </div>
      </div>
      <!-- Quick Topics -->
      <div class="chat-topics">
        <div class="topic-chip" onclick="sendQuickMessage('Hi! How are you today?')">👋 Greeting</div>
        <div class="topic-chip" onclick="sendQuickMessage('I feel stressed about school')">😰 Stress</div>
        <div class="topic-chip" onclick="sendQuickMessage('I feel anxious and worried')">😟 Anxiety</div>
        <div class="topic-chip" onclick="sendQuickMessage('I am feeling sad lately')">💙 Sadness</div>
        <div class="topic-chip" onclick="sendQuickMessage('I need career advice')">💼 Career</div>
        <div class="topic-chip" onclick="sendQuickMessage('Relationship problems')">💑 Relationships</div>
        <div class="topic-chip" onclick="sendQuickMessage('Can you help me with my studies?')">📚 Studies</div>
        <div class="topic-chip" onclick="sendQuickMessage('Tell me about technology trends')">💻 Technology</div>
      </div>

      <!-- Messages Container -->
      <div class="chat-messages" id="messagesContainer">
        <button class="clear-btn" onclick="clearChat()" title="Clear chat history">
          <i class="fas fa-trash"></i> Clear
        </button>

        <!-- Welcome Message -->
        <div class="message bot">
          <div class="message-avatar">
            <i class="fas fa-robot"></i>
          </div>
          <div class="message-content">
            Hello, <strong><?= htmlspecialchars($firstName) ?></strong>! 😊 I'm your LYDO Well-being Assistant powered by advanced AI. 
            <br><br>
            I can help you with <strong>anything</strong> you'd like to discuss:
            <br>• Mental health and emotional support
            <br>• Academic questions and study tips  
            <br>• Career guidance and planning
            <br>• Technology and science topics
            <br>• Relationships and social issues
            <br>• Hobbies and personal interests
            <br>• Current events and general knowledge
            <br><br>
            Feel free to ask me anything - I'm here to help and support you! What's on your mind today? 💙
          </div>
        </div>

        <!-- Load Chat History -->
        <?php foreach ($history as $msg): ?>
          <div class="message <?= $msg['role'] ?>">
            <div class="message-avatar">
              <i class="fas fa-<?= $msg['role'] === 'user' ? 'user' : 'robot' ?>"></i>
            </div>
            <div class="message-content">
              <?= nl2br(htmlspecialchars($msg['message'])) ?>
              <div class="message-time"><?= date('g:i A', strtotime($msg['created_at'])) ?></div>
            </div>
          </div>
        <?php endforeach ?>

        <!-- Typing Indicator -->
        <div class="typing" id="typingIndicator">
          <div class="message-avatar">
            <i class="fas fa-robot"></i>
          </div>
          <div>
            Assistant is typing
            <div class="typing-dots">
              <div class="typing-dot"></div>
              <div class="typing-dot"></div>
              <div class="typing-dot"></div>
            </div>
          </div>
        </div>
      </div>

      <!-- Chat Input -->
      <div class="chat-input">
        <textarea 
          id="messageInput" 
          placeholder="Type your message here... I can help with any topic!"
          rows="1"
          maxlength="1000"
        ></textarea>
        <button class="send-btn" onclick="sendMessage()" id="sendBtn">
          <i class="fas fa-paper-plane"></i>
        </button>
      </div>
    </div>
  </main>
</div>
<script>
const messagesContainer = document.getElementById('messagesContainer');
const messageInput = document.getElementById('messageInput');
const sendBtn = document.getElementById('sendBtn');
const typingIndicator = document.getElementById('typingIndicator');

// Auto-resize textarea
messageInput.addEventListener('input', function() {
    this.style.height = 'auto';
    this.style.height = (this.scrollHeight) + 'px';
});

// Send message on Enter (Shift+Enter for new line)
messageInput.addEventListener('keydown', function(e) {
    if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        sendMessage();
    }
});

// Send message function
async function sendMessage() {
    const message = messageInput.value.trim();
    if (!message) return;
    
    // Add user message to chat
    addMessage('user', message);
    messageInput.value = '';
    messageInput.style.height = 'auto';
    
    // Show typing indicator
    showTyping(true);
    
    try {
        const formData = new FormData();
        formData.append('ajax_chat', '1');
        formData.append('message', message);
        
        const response = await fetch(window.location.href, {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        console.log('API Response:', data); // Debug log
        
        // Hide typing indicator
        showTyping(false);
        
        // Add bot response to chat
        if (data.reply) {
            addMessage('bot', data.reply);
        } else {
            console.error('No reply in response:', data);
            addMessage('bot', 'I apologize, but I encountered an issue. Please try again or contact support if the problem persists.');
        }
        
    } catch (error) {
        console.error('Error:', error);
        showTyping(false);
        addMessage('bot', 'I\'m having trouble connecting right now. Please check your internet connection and try again. 😔');
    }
}

// Quick message function
function sendQuickMessage(message) {
    messageInput.value = message;
    sendMessage();
}

// Add message to chat
function addMessage(role, content) {
    const messageDiv = document.createElement('div');
    messageDiv.className = `message ${role}`;
    
    const time = new Date().toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
    const icon = role === 'user' ? 'user' : 'robot';
    
    // Convert markdown-like formatting to HTML
    const formattedContent = content
        .replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>')  // **bold**
        .replace(/\n/g, '<br>')                            // line breaks
        .replace(/•\s*/g, '• ')                           // bullet points
        .replace(/📞\s*/g, '📞 ')                         // phone icons
        .replace(/✅\s*/g, '✅ ');                        // checkmarks
    
    messageDiv.innerHTML = `
        <div class="message-avatar">
            <i class="fas fa-${icon}"></i>
        </div>
        <div class="message-content">
            ${formattedContent}
            <div class="message-time">${time}</div>
        </div>
    `;
    
    // Insert before typing indicator
    messagesContainer.insertBefore(messageDiv, typingIndicator);
    scrollToBottom();
}

// Show/hide typing indicator
function showTyping(show) {
    typingIndicator.classList.toggle('show', show);
    sendBtn.disabled = show;
    if (show) scrollToBottom();
}

// Scroll to bottom of chat
function scrollToBottom() {
    setTimeout(() => {
        messagesContainer.scrollTop = messagesContainer.scrollHeight;
    }, 100);
}

// Clear chat history
async function clearChat() {
    if (!confirm('Are you sure you want to clear your chat history? This action cannot be undone.')) {
        return;
    }
    
    try {
        const formData = new FormData();
        formData.append('clear_chat', '1');
        
        const response = await fetch(window.location.href, {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        if (data.ok) {
            // Remove all messages except welcome and typing indicator
            const messages = messagesContainer.querySelectorAll('.message:not(:first-child)');
            messages.forEach(msg => msg.remove());
        }
    } catch (error) {
        console.error('Error clearing chat:', error);
        alert('Failed to clear chat history. Please try again.');
    }
}

// Initial scroll
document.addEventListener('DOMContentLoaded', function() {
    scrollToBottom();
    messageInput.focus();
});
</script>

</body>
</html>