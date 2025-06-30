# Queue Media API Endpoint Documentation

## Overview

This document describes the new API endpoint that allows you to queue specific media files by their ID in AzuraCast, with an optional parameter to play the media immediately.

## Endpoint Details

**URL:** `POST /api/station/{station_id}/queue/media/{media_id}`

**Authentication:** Requires API key with Broadcasting permissions

**Content-Type:** `application/json`

## Parameters

### Path Parameters

- `station_id` (integer, required): The ID of the station
- `media_id` (integer, required): The ID of the media file to queue

### Request Body

```json
{
  "play_immediately": false
}
```

- `play_immediately` (boolean, optional, default: false): Whether to play the media file immediately by skipping the current song

## Response Format

### Success Response (HTTP 201 - Normal Queue)

```json
{
  "success": true,
  "message": "Media queued successfully.",
  "queue_id": 456,
  "media_id": 123
}
```

### Success Response (HTTP 200 - Immediate Play)

```json
{
  "success": true,
  "message": "Media queued and playing immediately.",
  "queue_id": 456,
  "media_id": 123
}
```

### Error Response (HTTP 404 - Media Not Found)

```json
{
  "success": false,
  "message": "Media file not found."
}
```

### Error Response (HTTP 500 - Immediate Play Failed)

```json
{
  "success": false,
  "message": "Media queued but failed to play immediately: [error details]",
  "queue_id": 456,
  "media_id": 123
}
```

## Usage Examples

### Example 1: Queue Media Normally

```bash
curl -X POST "http://your-azuracast.com/api/station/1/queue/media/123" \
  -H "Content-Type: application/json" \
  -H "X-API-Key: your-api-key" \
  -d '{"play_immediately": false}'
```

### Example 2: Queue Media and Play Immediately

```bash
curl -X POST "http://your-azuracast.com/api/station/1/queue/media/123" \
  -H "Content-Type: application/json" \
  -H "X-API-Key: your-api-key" \
  -d '{"play_immediately": true}'
```

### Example 3: JavaScript/Fetch API

```javascript
// Queue media normally
async function queueMedia(stationId, mediaId, playImmediately = false) {
  const response = await fetch(`/api/station/${stationId}/queue/media/${mediaId}`, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'X-API-Key': 'your-api-key'
    },
    body: JSON.stringify({
      play_immediately: playImmediately
    })
  });
  
  return await response.json();
}

// Usage
queueMedia(1, 123, false); // Queue normally
queueMedia(1, 124, true);  // Queue and play immediately
```

### Example 4: PHP

```php
function queueMedia($azuracastUrl, $apiKey, $stationId, $mediaId, $playImmediately = false) {
    $url = "$azuracastUrl/api/station/$stationId/queue/media/$mediaId";
    
    $data = ['play_immediately' => $playImmediately];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'X-API-Key: ' . $apiKey
    ]);
    
    $response = curl_exec($ch);
    curl_close($ch);
    
    return json_decode($response, true);
}

// Usage
$result = queueMedia('http://localhost:8000', 'your-api-key', 1, 123, false);
```

## How It Works

### Normal Queuing (`play_immediately: false`)

1. Creates a new queue entry for the specified media file
2. Sets the timestamp to the current time
3. Rebuilds the AutoDJ queue to include the new item
4. The media will play in its natural queue order

### Immediate Playback (`play_immediately: true`)

1. Creates a new queue entry for the specified media file
2. Clears any existing unsent queue items to prioritize the new media
3. Sets the timestamp to the current time
4. Rebuilds the AutoDJ queue
5. Sends a skip command to Liquidsoap to immediately play the next song (which will be the queued media)

## Requirements

- AzuraCast installation with Liquidsoap backend
- API key with Broadcasting permissions
- Valid station ID and media ID
- Station must support AutoDJ queue functionality

## Error Handling

The endpoint includes comprehensive error handling:

- **Media Not Found**: Returns 404 if the media ID doesn't exist for the station
- **Permission Denied**: Returns 403 if the API key lacks Broadcasting permissions
- **Station Not Found**: Returns 404 if the station ID is invalid
- **Immediate Play Failure**: Returns 500 with details if immediate playback fails (media is still queued)

## Integration Notes

### Getting Media IDs

You can get media IDs from other API endpoints:

```bash
# List all media files for a station
curl -H "X-API-Key: your-api-key" \
  "http://your-azuracast.com/api/station/1/files"
```

### Checking Queue Status

After queuing media, you can check the current queue:

```bash
# Get current queue
curl -H "X-API-Key: your-api-key" \
  "http://your-azuracast.com/api/station/1/queue"
```

## Use Cases

1. **DJ Applications**: Allow DJs to queue specific tracks during live shows
2. **Request Systems**: Automatically queue listener requests
3. **Emergency Broadcasting**: Immediately play important announcements
4. **Automated Scheduling**: Queue specific content based on time or events
5. **Interactive Applications**: Let users queue their favorite songs

## Security Considerations

- Always use HTTPS in production
- Protect API keys and don't expose them in client-side code
- Consider rate limiting to prevent abuse
- Validate media IDs to ensure users can only queue appropriate content

## Troubleshooting

### Common Issues

1. **"Queue is empty" error**: The AutoDJ queue system may not be properly configured
2. **"Station does not use Liquidsoap backend"**: This endpoint only works with Liquidsoap
3. **Permission denied**: Ensure your API key has Broadcasting permissions
4. **Media not found**: Verify the media ID exists and belongs to the specified station

### Debug Steps

1. Check station configuration and ensure AutoDJ is enabled
2. Verify the media file exists using the files API endpoint
3. Test with a simple queue operation before trying immediate playback
4. Check AzuraCast logs for detailed error information

## File Structure

The implementation consists of:

- `backend/src/Controller/Api/Stations/QueueMediaAction.php` - Main controller
- `backend/config/routes/api_station.php` - Route configuration
- OpenAPI documentation attributes for automatic API documentation generation

## OpenAPI/Swagger Documentation

The endpoint is fully documented with OpenAPI attributes and will appear in your AzuraCast API documentation at `/api/openapi.json`.
