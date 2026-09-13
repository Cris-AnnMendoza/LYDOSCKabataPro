<?php
/**
 * AI TEST PAGE - Para makita kung gumagana ang Gemini API
 */

// Enable error display
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../config.php';

echo "<html><head><title>AI Test</title></head><body style='font-family:sans-serif;padding:40px;max-width:800px;margin:0 auto'>";
echo "<h1>🤖 AI Well-being Assistant Test</h1>";

// Check if API key is set
$apiKey = getenv('GEMINI_API_KEY');
echo "<h2>1. API Key Check</h2>";
if ($apiKey && $apiKey !== 'false') {
    echo "✅ <strong>API Key is set:</strong> " . substr($apiKey, 0, 10) . "..." . substr($apiKey, -5) . "<br>";
} else {
    echo "❌ <strong>API Key NOT set</strong> in config.php<br>";
    echo "</body></html>";
    exit;
}

// Test API connection
echo "<h2>2. Testing Gemini API Connection</h2>";
echo "Sending test request...<br><br>";

$url = 'https://generativelanguage.googleapis.com/v1/models/gemini-pro:generateContent?key=' . $apiKey;

$testPrompt = "You are a Filipino youth counselor. Respond to this: 'I am feeling stressed about school.' Keep it under 100 words.";

$data = [
    'contents' => [
        ['parts' => [['text' => $testPrompt]]]
    ],
    'generationConfig' => [
        'temperature' => 0.7,
        'maxOutputTokens' => 300
    ]
];

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_TIMEOUT, 15);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

echo "<strong>HTTP Status Code:</strong> $httpCode<br>";

if ($curlError) {
    echo "❌ <strong>cURL Error:</strong> $curlError<br>";
}

if ($httpCode === 200 && $response) {
    $result = json_decode($response, true);
    
    if (isset($result['candidates'][0]['content']['parts'][0]['text'])) {
        $aiResponse = $result['candidates'][0]['content']['parts'][0]['text'];
        echo "✅ <strong>SUCCESS! AI Response:</strong><br>";
        echo "<div style='background:#e3f2fd;padding:15px;border-radius:8px;margin:10px 0'>";
        echo nl2br(htmlspecialchars($aiResponse));
        echo "</div>";
        echo "<p><strong>✅ AI is working! You can now use the Well-being Assistant.</strong></p>";
    } else {
        echo "❌ <strong>Unexpected response format</strong><br>";
        echo "<pre style='background:#ffebee;padding:10px;border-radius:5px;overflow:auto'>";
        echo htmlspecialchars(print_r($result, true));
        echo "</pre>";
    }
} else {
    echo "❌ <strong>API Request Failed</strong><br>";
    echo "<strong>Response:</strong><br>";
    echo "<pre style='background:#ffebee;padding:10px;border-radius:5px;overflow:auto'>";
    echo htmlspecialchars($response);
    echo "</pre>";
    
    if ($httpCode === 400) {
        echo "<p><strong>Possible issues:</strong></p>";
        echo "<ul>";
        echo "<li>API key might be invalid or expired</li>";
        echo "<li>Gemini API might not be enabled for your project</li>";
        echo "<li>Check if you need to enable billing (still free tier)</li>";
        echo "</ul>";
    } elseif ($httpCode === 403) {
        echo "<p><strong>Permission denied:</strong> API key might be restricted or invalid</p>";
    }
}

echo "<hr>";
echo "<h2>3. Next Steps</h2>";
echo "<ul>";
echo "<li>If successful above, go to <a href='wellbeing_ai.php'>Well-being Assistant</a></li>";
echo "<li>If failed, check your API key at <a href='https://makersuite.google.com/app/apikey' target='_blank'>Google AI Studio</a></li>";
echo "<li>Make sure Gemini API is enabled in your Google Cloud project</li>";
echo "</ul>";

echo "</body></html>";
?>
