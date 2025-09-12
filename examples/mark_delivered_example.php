<?php

require_once __DIR__ . '/../vendor/autoload.php';

use GetStream\StreamChat\Client;

// Initialize the Stream Chat client
$client = new Client(
    'your_api_key_here',
    'your_api_secret_here'
);

// Create a channel instance
$channel = $client->Channel('messaging', 'channel_id_here');

// Example 1: Basic usage with just user ID
try {
    $response = $channel->markDelivered('user_id_here');
    echo "Delivery receipt sent successfully\n";
    echo "Response status: " . $response->getStatusCode() . "\n";
} catch (Exception $e) {
    echo "Error sending delivery receipt: " . $e->getMessage() . "\n";
}

// Example 2: Advanced usage with custom data
try {
    $data = [
        'channel_delivered_message' => [
            'messaging:channel_id_here' => 'message_id_here'
        ],
        'client_id' => 'web_client_123',
        'connection_id' => 'connection_456'
    ];
    
    $response = $channel->markDelivered('user_id_here', $data);
    echo "Delivery receipt with custom data sent successfully\n";
    echo "Response status: " . $response->getStatusCode() . "\n";
} catch (Exception $e) {
    echo "Error sending delivery receipt with custom data: " . $e->getMessage() . "\n";
}

// Example 3: Using with multiple channels
try {
    $data = [
        'channel_delivered_message' => [
            'messaging:channel_1' => 'message_1_id',
            'messaging:channel_2' => 'message_2_id',
            'team:team_channel' => 'team_message_id'
        ]
    ];
    
    $response = $channel->markDelivered('user_id_here', $data);
    echo "Multi-channel delivery receipt sent successfully\n";
    echo "Response status: " . $response->getStatusCode() . "\n";
} catch (Exception $e) {
    echo "Error sending multi-channel delivery receipt: " . $e->getMessage() . "\n";
}

echo "\nNote: The delivery_receipts setting must be enabled for the user\n";
echo "for this functionality to work properly.\n";

