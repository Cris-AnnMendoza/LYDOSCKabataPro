<?php
/**
 * GROQ AI TEST PAGE
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../config.php';

echo "<html><head><title>Groq AI Test</title></head><body style='font-family:sans-serif;padding:40px;max-width:800px;margin:0 auto'>";
echo "<h1>⚡ Groq AI Test</h1>";

// Check API key
$apiKey = getenv('GROQ_API_KEY');
echo "<h2>1. API Key Check</h2>";
if ($apiKey && $apiKey !== 'false') {
    echo "✅ <strong>API Key is set:</strong> " . substr($apiKey, 0, 15) . "..." . substr($apiKey, -10) . "<br>";
} else {
    echo "❌ <strong>API Key NOT set</strong> in config.php<br>";
    echo "</body></html>";
    exit;
}

// Test Groq API
echo "<h2>2. Testing Groq API Connection</h2>";
echo "Sending test request to Groq...<br><br>";

$url = 'https://api.groq.com/openai/v1/chat/completions';

$testPrompt = "You are a Filipino youth counselor. Someone says: 'I am feeling very stressed about school.' Respond in 50 words with practical advice.";

$data = [
    'model' => 'openai/gpt-oss-20b',
    'messages' => [
        ['role' => 'system', 'content' => 'You are a compassionate Filipino youth counselor.'],
        ['role' => 'user', 'content' => 'I am feeling very stressed about school.']
    ],
    'temperature' => 0.7,
    'max_tokens' => 300
];

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $apiKey,
    'Content-Type: application/json'
]);
curl_setopt($ch, CURLOPT_TIMEOUT, 15);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

echo "<strong>HTTP Status Code:</strong> $httpCode<br>";

if ($curlError) {
    echo "❌ <strong>cURL Error:</strong> $curlError<br>";
}

echo "<br><strong>Raw Response:</strong><br>";
echo "<pre style='background:#f5f5f5;padding:10px;border-radius:5px;overflow:auto;max-height:200px;font-size:11px'>";
echo htmlspecialchars($response);
echo "</pre><br>";

if ($httpCode === 200 && $response) {
    $result = json_decode($response, true);
    
    if (isset($result['choices'][0]['message']['content'])) {
        $aiResponse = $result['choices'][0]['message']['content'];
        echo "✅ <strong style='color:green;font-size:18px'>SUCCESS! Groq AI is working!</strong><br><br>";
        echo "<div style='background:#e8f5e9;padding:20px;border-radius:12px;border:3px solid #4caf50;margin:10px 0'>";
        echo "<strong>AI Response:</strong><br><br>";
        echo nl2br(htmlspecialchars($aiResponse));
        echo "</div>";
        
        echo "<br><div style='background:#fff3cd;padding:15px;border-radius:8px;border:2px solid #ffc107'>";
        echo "🎉 <strong>Perfect! Your AI assistant is ready!</strong><br>";
        echo "Go to <a href='wellbeing_ai.php' style='color:#1565c0;font-weight:bold'>Well-being Assistant</a> and try asking complex questions!";
        echo "</div>";
        
    } else {
        echo "❌ <strong>Unexpected response format</strong><br>";
    }
} else {
    echo "❌ <strong>API Request Failed</strong><br>";
    
    if ($httpCode === 401) {
        echo "<p style='color:red'><strong>Error 401: Authentication failed</strong><br>";
        echo "Your API key might be invalid or expired. Get a new one from <a href='https://console.groq.com' target='_blank'>console.groq.com</a></p>";
    } elseif ($httpCode === 429) {
        echo "<p style='color:orange'><strong>Error 429: Rate limit</strong><br>";
        echo "Too many requests. Wait a minute and try again.</p>";
    } elseif ($httpCode === 0) {
        echo "<p style='color:red'><strong>Connection Error</strong><br>";
        echo "Cannot reach Groq API. Check your internet connection.</p>";
    }
}

echo "<hr>";
echo "<h2>3. System Info</h2>";
echo "PHP Version: " . phpversion() . "<br>";
echo "cURL enabled: " . (function_exists('curl_init') ? '✅ Yes' : '❌ No') . "<br>";
echo "OpenSSL: " . (extension_loaded('openssl') ? '✅ Yes' : '❌ No') . "<br>";

echo "</body></html>";
?>
