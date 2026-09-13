<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<html><head><title>API Key Checker</title></head><body style='font-family:sans-serif;padding:20px;max-width:600px;margin:0 auto'>";
echo "<h1>🔍 Gemini API Key Checker</h1>";

// Method 1: Check environment variable
echo "<h2>Method 1: Check putenv()</h2>";
$key1 = getenv('GEMINI_API_KEY');
if ($key1) {
    echo "✅ Found: " . substr($key1, 0, 15) . "...<br>";
} else {
    echo "❌ Not found via getenv()<br>";
}

// Method 2: Check $_ENV
echo "<h2>Method 2: Check \$_ENV</h2>";
if (isset($_ENV['GEMINI_API_KEY'])) {
    echo "✅ Found in \$_ENV<br>";
} else {
    echo "❌ Not in \$_ENV<br>";
}

// Method 3: Direct test
echo "<h2>Method 3: Direct API Test</h2>";
$apiKey = 'AIzaSyAQ.Ab8RN6IuOjWwnGucNFjjeM3Zx3YOS7MtzWpGFsr95arxw8Kz6A';

echo "Using key: " . substr($apiKey, 0, 20) . "...<br><br>";

// Test with gemini-1.5-flash
$url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key=' . $apiKey;

$data = json_encode([
    'contents' => [
        ['parts' => [['text' => 'Say hello in 5 words']]]
    ]
]);

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

echo "<strong>HTTP Code:</strong> $httpCode<br>";
if ($curlError) {
    echo "<strong>cURL Error:</strong> $curlError<br>";
}

echo "<br><strong>Raw Response:</strong><br>";
echo "<pre style='background:#f0f0f0;padding:10px;border-radius:5px;overflow:auto;font-size:11px'>";
echo htmlspecialchars($response);
echo "</pre>";

if ($httpCode === 200) {
    $result = json_decode($response, true);
    if (isset($result['candidates'][0]['content']['parts'][0]['text'])) {
        echo "<br>✅ <strong style='color:green'>SUCCESS! AI Response:</strong><br>";
        echo "<div style='background:#e8f5e9;padding:15px;border-radius:8px;border:2px solid #4caf50'>";
        echo htmlspecialchars($result['candidates'][0]['content']['parts'][0]['text']);
        echo "</div>";
    }
} else {
    echo "<br>❌ <strong style='color:red'>FAILED</strong><br>";
    $error = json_decode($response, true);
    if (isset($error['error']['message'])) {
        echo "Error: " . htmlspecialchars($error['error']['message']) . "<br>";
    }
}

// Check cURL is enabled
echo "<h2>Method 4: PHP Configuration</h2>";
echo "cURL enabled: " . (function_exists('curl_init') ? '✅ Yes' : '❌ No') . "<br>";
echo "allow_url_fopen: " . (ini_get('allow_url_fopen') ? '✅ Yes' : '❌ No') . "<br>";
echo "OpenSSL: " . (extension_loaded('openssl') ? '✅ Yes' : '❌ No') . "<br>";

echo "</body></html>";
?>
