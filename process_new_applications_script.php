<?php

require_once 'config.php';

function logMessage($message) {
    $log_file = LOG_DIR . '/new_applications_log.txt';
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($log_file, "[{$timestamp}] {$message}" . PHP_EOL, FILE_APPEND | LOCK_EX);
}

function logSMSActivity($phone, $message_id, $omnimessage_id = null) {
    $log_file = LOG_DIR . '/sms_log.txt';
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = json_encode([
        'timestamp' => $timestamp,
        'phone' => $phone,
        'message_id' => $message_id,
        'omnimessage_id' => $omnimessage_id
    ]);
    file_put_contents($log_file, $logEntry . PHP_EOL, FILE_APPEND | LOCK_EX);
}

function insertFailedSMS($pdo, $formNumber, $mobilePhone, $errorMessage, $httpCode) {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO failed_sms (form_number, mobile_phone, error_message, http_code) 
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$formNumber, $mobilePhone, $errorMessage, $httpCode]);
        return true;
    } catch (Exception $e) {
        logMessage("Failed to insert failed SMS record: " . $e->getMessage());
        return false;
    }
}

function isValidPhoneNumber($phone) {
    $phone = preg_replace('/[^0-9]/', '', trim($phone));
    
    return !empty($phone) && strlen($phone) >= 4;
}

function sendSMS($formNumber, $mobilePhone, $apiCredentials) {
    $tracking_url = BASE_TRACKING_URL . '/?id=' . urlencode($formNumber);
    
    $sms_body = [
        'to' => $mobilePhone,
        'messages' => [
            [
                'channel' => 'sms',
                'sender' => SMS_SENDER,
                'text' => "Aplikimi eshte bere me sukses, me numer {$formNumber}.\n\n" .
                         "Per ta gjurmuar ate ne kohe reale vizitoni:\n" .
                         "{$tracking_url}\n\n" .
                         SMS_SENDER
            ]
        ]
    ];
    
    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => [
                'Content-Type: application/json',
                'Accept: application/json',
                'Authorization: Basic ' . base64_encode($apiCredentials['username'] . ':' . $apiCredentials['password'])
            ],
            'content' => json_encode($sms_body),
            'timeout' => 30
        ]
    ]);
    
    $response = @file_get_contents($apiCredentials['url'], false, $context);
    
    if ($response === false) {
        return [
            'success' => false, 
            'error' => 'Unable to connect to SMS API', 
            'message_id' => null, 
            'omnimessage_id' => null,
            'http_code' => 0
        ];
    }
    
    $http_response_header_line = $http_response_header[0] ?? '';
    preg_match('/HTTP\/\d\.\d\s+(\d+)/', $http_response_header_line, $matches);
    $http_code = $matches[1] ?? 0;
    
    if ($http_code >= 200 && $http_code < 300) {
        $response_data = json_decode($response, true);
        
        if ($response_data && isset($response_data['messages'][0]['message_id'])) {
            return [
                'success' => true,
                'message_id' => $response_data['messages'][0]['message_id'],
                'omnimessage_id' => $response_data['omnimessage_id'] ?? null,
                'http_code' => $http_code
            ];
        }
    }
    
    return [
        'success' => false,
        'error' => "HTTP {$http_code}",
        'message_id' => null,
        'omnimessage_id' => null,
        'http_code' => $http_code
    ];
}

try {
    logMessage("Starting new applications processing");
    
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $input = file_get_contents(API_NEW_APPLICATIONS_ENDPOINT);
    if ($input === false) {
        logMessage("Failed to fetch data from API endpoint: " . API_NEW_APPLICATIONS_ENDPOINT);
        exit(1);
    }
    
    if (empty($input)) {
        logMessage("No data received from API endpoint");
        exit(0);
    }
    
    logMessage("Reading from API endpoint: " . API_NEW_APPLICATIONS_ENDPOINT);
    
    $data = json_decode($input, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception("JSON decode error: " . json_last_error_msg());
    }
    
    if (!is_array($data) || empty($data)) {
        logMessage("No application data received");
        exit(0);
    }
    
    logMessage("Received " . count($data) . " application records");
    
    $smsCredentials = [
        'url' => SMS_API_URL,
        'username' => SMS_API_USERNAME,
        'password' => SMS_API_PASSWORD
    ];
    
    $sms_sent = 0;
    $sms_failed = 0;
    $skipped = 0;
    // We no longer gate SMS by DB existence; send for any valid phone
    
    foreach ($data as $record) {
        if (empty($record['formNumber'])) {
            logMessage("Skipping record with missing formNumber");
            $skipped++;
            continue;
        }
        
        $formNumber = trim($record['formNumber']);
        $mobilePhone = isset($record['mobilePhone']) ? trim($record['mobilePhone']) : '';
        
        if (!isValidPhoneNumber($mobilePhone)) {
            logMessage("Skipping SMS for {$formNumber} - invalid/short phone number: '{$mobilePhone}'");
            $skipped++;
            continue;
        }
        
        try {
            $smsResult = sendSMS($formNumber, $mobilePhone, $smsCredentials);
            
            if ($smsResult['success']) {
                logMessage("SMS sent successfully to {$mobilePhone} for form {$formNumber} - Message ID: {$smsResult['message_id']}");
                
                logSMSActivity($mobilePhone, $smsResult['message_id'], $smsResult['omnimessage_id']);
                $sms_sent++;
                
            } else {
                logMessage("SMS failed for {$formNumber}: {$smsResult['error']}");
                
                insertFailedSMS($pdo, $formNumber, $mobilePhone, $smsResult['error'], $smsResult['http_code']);
                $sms_failed++;
            }
            
            usleep(SMS_DELAY_MICROSECONDS);
            
        } catch (Exception $e) {
            logMessage("Error processing form {$formNumber}: " . $e->getMessage());
            
            insertFailedSMS($pdo, $formNumber, $mobilePhone, $e->getMessage(), 0);
            $sms_failed++;
        }
    }
    
    logMessage("Processing completed - SMS Sent: {$sms_sent}, SMS Failed: {$sms_failed}, Skipped: {$skipped}");
    
} catch (Exception $e) {
    logMessage("Error: " . $e->getMessage());
    exit(1);
}

logMessage("Script execution finished");
?>
