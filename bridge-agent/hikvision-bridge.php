<?php

/**
 * Dream Roots Millennium School - Hikvision Local Windows Bridge Agent
 * 
 * Hardware: Hikvision DS-K1A340FWX Face Recognition Terminal
 * Role: Bridges local school Wi-Fi Hikvision terminal with cloud Laravel HTTPS API.
 * 
 * Architecture:
 * [Hikvision Terminal (LAN)] <--(ISAPI Digest)--> [Bridge Agent (Windows PC)] --(HTTPS)--> [cPanel Laravel API]
 */

declare(strict_types=1);

date_default_timezone_set('Asia/Dhaka');

$configFile = __DIR__ . DIRECTORY_SEPARATOR . 'bridge-config.json';
if (!file_exists($configFile)) {
    echo "[ERROR] Configuration file 'bridge-config.json' not found in " . __DIR__ . PHP_EOL;
    exit(1);
}

$config = json_decode(file_get_contents($configFile), true);
if (!is_array($config)) {
    echo "[ERROR] Invalid JSON in 'bridge-config.json'" . PHP_EOL;
    exit(1);
}

$cloudApi = $config['cloud_api'] ?? [];
$localDevice = $config['local_device'] ?? [];
$settings = $config['agent_settings'] ?? [];

$apiUrl = trim((string)($cloudApi['url'] ?? ''));
$deviceId = (string)($cloudApi['device_id'] ?? '');
$deviceSecret = trim((string)($cloudApi['device_secret'] ?? ''));

$deviceIp = trim((string)($localDevice['ip'] ?? ''));
$devicePort = (int)($localDevice['port'] ?? 80);
$deviceUser = trim((string)($localDevice['username'] ?? 'admin'));
$devicePass = (string)($localDevice['password'] ?? '');
$deviceTimeout = (int)($localDevice['timeout_seconds'] ?? 5);

$pollInterval = (int)($settings['poll_interval_seconds'] ?? 10);
$batchSize = (int)($settings['batch_size'] ?? 30);
$stateFile = __DIR__ . DIRECTORY_SEPARATOR . ($settings['state_file'] ?? 'bridge-state.json');
$logFile = __DIR__ . DIRECTORY_SEPARATOR . ($settings['log_file'] ?? 'bridge.log');

function logMessage(string $msg, string $logFile): void {
    $line = '[' . date('Y-m-d H:i:s') . '] ' . $msg . PHP_EOL;
    echo $line;
    @file_put_contents($logFile, $line, FILE_APPEND);
}

logMessage("==================================================", $logFile);
logMessage("Hikvision DS-K1A340FWX Local Windows Bridge Agent", $logFile);
logMessage("Target Cloud API: {$apiUrl}", $logFile);
logMessage("Device ID: {$deviceId} | Local Terminal IP: {$deviceIp}:{$devicePort}", $logFile);
logMessage("Poll Interval: {$pollInterval}s", $logFile);
logMessage("==================================================", $logFile);

// Read state
$state = [
    'last_sync_time' => date('Y-m-d\TH:i:s', strtotime('-1 hour')),
    'last_serial_no' => 0,
];

if (file_exists($stateFile)) {
    $savedState = json_decode(file_get_contents($stateFile), true);
    if (is_array($savedState)) {
        $state = array_merge($state, $savedState);
    }
}

logMessage("Starting event sync from: {$state['last_sync_time']}", $logFile);

/**
 * Perform an ISAPI request to the Hikvision terminal with HTTP Digest Authentication
 */
function hikvisionRequest(string $url, string $method, ?string $body, string $user, string $pass, int $timeout): array {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
    curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_DIGEST);
    curl_setopt($ch, CURLOPT_USERPWD, "{$user}:{$pass}");
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);

    if (strtoupper($method) === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        }
    }

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    return [
        'code' => $httpCode,
        'body' => $response,
        'error' => $error,
    ];
}

/**
 * Push formatted punches to Laravel Cloud API
 */
function pushToLaravel(string $url, string $deviceId, string $deviceSecret, array $events, int $timeout = 10): array {
    $ch = curl_init();
    $payload = json_encode(['events' => $events]);

    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Accept: application/json',
        'X-Device-Id: ' . $deviceId,
        'X-Device-Secret: ' . $deviceSecret,
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    return [
        'code' => $httpCode,
        'body' => $response,
        'error' => $error,
    ];
}

// Main polling loop
while (true) {
    if ($deviceIp === '' || !filter_var($deviceIp, FILTER_VALIDATE_IP)) {
        logMessage("[WARN] Device IP is not set or invalid in bridge-config.json. Waiting for device setup...", $logFile);
        sleep($pollInterval);
        continue;
    }

    // 1. Probe terminal / Query AcsEvent
    $isapiSearchUrl = "http://{$deviceIp}:{$devicePort}/ISAPI/AccessControl/AcsEvent?format=json";
    $searchCond = [
        'AcsEventCond' => [
            'searchID' => 'bridge-' . time(),
            'searchResultPosition' => 0,
            'maxResults' => $batchSize,
            'major' => 5, // Access Control Events
            'minor' => 75, // Face Authentication Succeeded
            'startTime' => $state['last_sync_time'],
            'endTime' => date('Y-m-d\TH:i:s'),
        ],
    ];

    $response = hikvisionRequest(
        $isapiSearchUrl,
        'POST',
        json_encode($searchCond),
        $deviceUser,
        $devicePass,
        $deviceTimeout
    );

    if ($response['code'] === 0) {
        logMessage("[OFFLINE] Cannot reach Hikvision device at {$deviceIp}:{$devicePort} ({$response['error']}). Retrying in {$pollInterval}s...", $logFile);
        sleep($pollInterval);
        continue;
    }

    if ($response['code'] === 401) {
        logMessage("[AUTH FAILED] HTTP 401 Unauthorized from device {$deviceIp}. Check username and password in bridge-config.json.", $logFile);
        sleep($pollInterval * 2);
        continue;
    }

    if ($response['code'] !== 200) {
        logMessage("[WARN] Device responded with HTTP {$response['code']}: " . substr((string)$response['body'], 0, 150), $logFile);
        sleep($pollInterval);
        continue;
    }

    $data = json_decode((string)$response['body'], true);
    $eventList = $data['AcsEvent']['InfoList'] ?? [];

    if (empty($eventList)) {
        // No new events
        sleep($pollInterval);
        continue;
    }

    // 2. Format events for Laravel contract
    $laravelEvents = [];
    $maxSerial = $state['last_serial_no'];
    $latestTime = $state['last_sync_time'];

    foreach ($eventList as $item) {
        $serialNo = (int)($item['serialNo'] ?? 0);
        $timeStr = (string)($item['time'] ?? '');
        $employeeNo = trim((string)($item['employeeNoString'] ?? ''));

        if ($serialNo <= $state['last_serial_no'] && $timeStr <= $state['last_sync_time']) {
            continue;
        }

        if ($employeeNo === '') {
            continue;
        }

        $formattedTime = date('Y-m-d\TH:i:sP', strtotime($timeStr));

        $laravelEvents[] = [
            'external_ref' => "hik-{$deviceIp}-{$serialNo}",
            'user_id' => $employeeNo,
            'occurred_at' => $formattedTime,
            'meta' => [
                'serial_no' => $serialNo,
                'major' => $item['major'] ?? 5,
                'minor' => $item['minor'] ?? 75,
                'card_no' => $item['cardNo'] ?? null,
            ],
        ];

        if ($serialNo > $maxSerial) {
            $maxSerial = $serialNo;
        }
        if ($timeStr > $latestTime) {
            $latestTime = $timeStr;
        }
    }

    if (empty($laravelEvents)) {
        sleep($pollInterval);
        continue;
    }

    logMessage("[SYNC] Found " . count($laravelEvents) . " punch(es) from Hikvision terminal. Pushing to Laravel API...", $logFile);

    // 3. Post to Laravel
    $pushRes = pushToLaravel($apiUrl, $deviceId, $deviceSecret, $laravelEvents);

    if ($pushRes['code'] === 200) {
        $pushData = json_decode((string)$pushRes['body'], true);
        $accepted = $pushData['received'] ?? 0;
        logMessage("[SUCCESS] Laravel accepted {$accepted} punch(es). Updating state checkpoint.", $logFile);

        // Update state
        $state['last_sync_time'] = $latestTime;
        $state['last_serial_no'] = $maxSerial;
        @file_put_contents($stateFile, json_encode($state, JSON_PRETTY_PRINT));
    } else {
        logMessage("[PUSH FAILED] HTTP {$pushRes['code']} from Laravel: " . substr((string)$pushRes['body'], 0, 200), $logFile);
    }

    sleep($pollInterval);
}
