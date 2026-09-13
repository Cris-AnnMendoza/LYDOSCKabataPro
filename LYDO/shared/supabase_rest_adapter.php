<?php
/**
 * Supabase REST API Adapter
 * Use this when direct PostgreSQL connection is blocked by network/firewall
 * This uses HTTPS (port 443) which is rarely blocked
 */

// Supabase REST API configuration
define('SUPABASE_REST_URL', 'https://wbwiatdjfhviguxzyxue.supabase.co/rest/v1');
define('SUPABASE_ANON_KEY', 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6Indid2lhdGRqZmh2aWd1eHp5eHVlIiwicm9sZSI6ImFub24iLCJpYXQiOjE3MzU4MTY0MTUsImV4cCI6MjA1MTM5MjQxNX0.Vw-av4iXAt76R_9BzgQZ8fM5ppHlYCF5-wXkfuQnQeg');

/**
 * Make REST API request to Supabase
 */
function supabaseREST($endpoint, $method = 'GET', $data = null, $params = []) {
    $url = SUPABASE_REST_URL . $endpoint;
    
    // Add query parameters
    if (!empty($params)) {
        $url .= '?' . http_build_query($params);
    }
    
    $headers = [
        'apikey: ' . SUPABASE_ANON_KEY,
        'Authorization: Bearer ' . SUPABASE_ANON_KEY,
        'Content-Type: application/json',
        'Prefer: return=representation'
    ];
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    
    if ($data && in_array($method, ['POST', 'PUT', 'PATCH'])) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        throw new Exception("Supabase REST API error: $error");
    }
    
    if ($httpCode >= 400) {
        throw new Exception("Supabase REST API error: HTTP $httpCode - $response");
    }
    
    return json_decode($response, true);
}

/**
 * Test if REST API is accessible
 */
function testSupabaseREST() {
    try {
        $result = supabaseREST('/events', 'GET', null, ['select' => 'id', 'limit' => 1]);
        return true;
    } catch (Exception $e) {
        return false;
    }
}
