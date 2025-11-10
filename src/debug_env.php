<?php
// Debug page to check environment variables
require 'config/config.php';

echo "<h1>Environment Variables Debug</h1>";
echo "<p><strong>MAIL_FROM_ADDRESS:</strong> " . htmlspecialchars(MAIL_FROM_ADDRESS) . "</p>";
echo "<p><strong>MAIL_FROM_NAME:</strong> " . htmlspecialchars(MAIL_FROM_NAME) . "</p>";
echo "<p><strong>MAIL_USE_SMTP:</strong> " . (MAIL_USE_SMTP ? 'true' : 'false') . "</p>";

$apiKey = getenv('SENDGRID_API_KEY');
echo "<p><strong>SENDGRID_API_KEY (from getenv()):</strong> " . (empty($apiKey) ? 'NOT SET' : 'SET (length: ' . strlen($apiKey) . ')') . "</p>";

echo "<h2>All env variables starting with 'MAIL' or 'SENDGRID':</h2>";
$env = getenv();
foreach ($env as $key => $value) {
    if (strpos($key, 'MAIL') !== false || strpos($key, 'SENDGRID') !== false) {
        $masked = strlen($value) > 10 ? substr($value, 0, 10) . '...' : $value;
        echo "<p><strong>{$key}:</strong> {$masked}</p>";
    }
}

echo "<h2>Testing SendGrid API call:</h2>";
$to = 'test@example.com';
$subject = 'Test Email';
$body = '<html><body><p>Test</p></body></html>';
$customerName = 'Test User';

$data = [
    'personalizations' => [
        [
            'to' => [['email' => $to, 'name' => $customerName]],
            'subject' => $subject
        ]
    ],
    'from' => ['email' => 'noreply@dmg-portaal.com', 'name' => 'DMG Klantportaal'],
    'content' => [
        ['type' => 'text/html', 'value' => $body]
    ]
];

if (empty($apiKey)) {
    echo "<p><strong>ERROR: API Key not set!</strong></p>";
} else {
    $ch = curl_init('https://api.sendgrid.com/v3/mail/send');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $apiKey,
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    echo "<p><strong>HTTP Code:</strong> {$httpCode}</p>";
    echo "<p><strong>Curl Error:</strong> " . (empty($curlError) ? 'None' : htmlspecialchars($curlError)) . "</p>";
    echo "<p><strong>Response:</strong> " . htmlspecialchars($response) . "</p>";
    echo "<p><strong>Success:</strong> " . ($httpCode === 202 ? 'YES' : 'NO') . "</p>";
}
?>
