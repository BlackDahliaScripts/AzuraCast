<?php

/**
 * Test script for the new Queue Media API endpoint
 * 
 * This script demonstrates how to use the new /api/station/{station_id}/queue/media/{media_id} endpoint
 * to queue media files with optional immediate playback.
 */

// Configuration - Update these values for your AzuraCast installation
$azuracast_url = 'http://localhost:8000'; // Your AzuraCast URL
$api_key = 'YOUR_API_KEY_HERE'; // Your API key with Broadcasting permissions
$station_id = 1; // Your station ID
$media_id = 123; // The media file ID you want to queue

/**
 * Example 1: Queue a media file normally (will play in queue order)
 */
function queueMediaNormal($azuracast_url, $api_key, $station_id, $media_id) {
    $url = "$azuracast_url/api/station/$station_id/queue/media/$media_id";
    
    $data = [
        'play_immediately' => false
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'X-API-Key: ' . $api_key
    ]);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    echo "Normal Queue Response (HTTP $http_code):\n";
    echo json_encode(json_decode($response), JSON_PRETTY_PRINT) . "\n\n";
    
    return json_decode($response, true);
}

/**
 * Example 2: Queue a media file and play it immediately (skip current song)
 */
function queueMediaImmediate($azuracast_url, $api_key, $station_id, $media_id) {
    $url = "$azuracast_url/api/station/$station_id/queue/media/$media_id";
    
    $data = [
        'play_immediately' => true
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'X-API-Key: ' . $api_key
    ]);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    echo "Immediate Play Response (HTTP $http_code):\n";
    echo json_encode(json_decode($response), JSON_PRETTY_PRINT) . "\n\n";
    
    return json_decode($response, true);
}

/**
 * Example 3: Get current queue to see the results
 */
function getCurrentQueue($azuracast_url, $api_key, $station_id) {
    $url = "$azuracast_url/api/station/$station_id/queue";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'X-API-Key: ' . $api_key
    ]);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    echo "Current Queue (HTTP $http_code):\n";
    $queue = json_decode($response, true);
    if (isset($queue['data'])) {
        foreach ($queue['data'] as $item) {
            echo "- {$item['song']['title']} by {$item['song']['artist']} (ID: {$item['id']})\n";
        }
    } else {
        echo json_encode($queue, JSON_PRETTY_PRINT);
    }
    echo "\n";
    
    return json_decode($response, true);
}

// Run the examples
echo "=== AzuraCast Queue Media API Test ===\n\n";

// Test normal queuing
echo "1. Testing normal queue...\n";
queueMediaNormal($azuracast_url, $api_key, $station_id, $media_id);

// Test immediate playback
echo "2. Testing immediate playback...\n";
queueMediaImmediate($azuracast_url, $api_key, $station_id, $media_id + 1); // Use different media ID

// Show current queue
echo "3. Current queue status...\n";
getCurrentQueue($azuracast_url, $api_key, $station_id);

echo "=== Test Complete ===\n";

/**
 * JavaScript/Fetch API Example:
 * 
 * // Queue media normally
 * fetch('/api/station/1/queue/media/123', {
 *     method: 'POST',
 *     headers: {
 *         'Content-Type': 'application/json',
 *         'X-API-Key': 'your-api-key'
 *     },
 *     body: JSON.stringify({
 *         play_immediately: false
 *     })
 * })
 * .then(response => response.json())
 * .then(data => console.log(data));
 * 
 * // Queue media and play immediately
 * fetch('/api/station/1/queue/media/123', {
 *     method: 'POST',
 *     headers: {
 *         'Content-Type': 'application/json',
 *         'X-API-Key': 'your-api-key'
 *     },
 *     body: JSON.stringify({
 *         play_immediately: true
 *     })
 * })
 * .then(response => response.json())
 * .then(data => console.log(data));
 */
