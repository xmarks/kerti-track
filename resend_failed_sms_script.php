<?php

require_once 'config.php';

function logMessage($message) {
    $log_file = LOG_DIR . '/resend_sms_log.txt';
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
        'omnimessage_id' => $omnimessage_id,
        'type' => 'resend'
    ]);
    file_put_contents($log_file, $logEntry . PHP_EOL, FILE_APPEND | LOCK_EX);
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
    logMessage("Starting failed SMS resend process");
    
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Get all failed SMS that haven't been successfully resent
    $stmt = $pdo->prepare("
        SELECT id, form_number, mobile_phone, error_message, http_code, failed_at, retry_count
        FROM failed_sms 
        WHERE resent_successfully = 0 
        ORDER BY failed_at ASC
    ");
    
    $stmt->execute();
    $failedSmsRecords = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($failedSmsRecords)) {
        logMessage("No failed SMS records to resend");
        exit(0);
    }
    
    logMessage("Found " . count($failedSmsRecords) . " failed SMS records to resend");
    
    $smsCredentials = [
        'url' => SMS_API_URL,
        'username' => SMS_API_USERNAME,
        'password' => SMS_API_PASSWORD
    ];
    
    $resent_successfully = 0;
    $resent_failed = 0;
    
    // Prepare statements for updating records
    $updateSuccessStmt = $pdo->prepare("
        UPDATE failed_sms 
        SET resent_successfully = 1, resent_at = NOW(), retry_count = retry_count + 1
        WHERE id = ?
    ");
    
    $updateFailedStmt = $pdo->prepare("
        UPDATE failed_sms 
        SET retry_count = retry_count + 1
        WHERE id = ?
    ");
    
    foreach ($failedSmsRecords as $record) {
        $id = $record['id'];
        $formNumber = $record['form_number'];
        $mobilePhone = $record['mobile_phone'];
        
        logMessage("Attempting to resend SMS for form {$formNumber} to {$mobilePhone} (ID: {$id})");
        
        try {
            $smsResult = sendSMS($formNumber, $mobilePhone, $smsCredentials);
            
            if ($smsResult['success']) {
                logMessage("SMS resent successfully to {$mobilePhone} for form {$formNumber} - Message ID: {$smsResult['message_id']}");
                
                logSMSActivity($mobilePhone, $smsResult['message_id'], $smsResult['omnimessage_id']);
                $updateSuccessStmt->execute([$id]);
                $resent_successfully++;
                
            } else {
                logMessage("SMS resend failed for {$formNumber} (ID: {$id}): {$smsResult['error']}");
                $updateFailedStmt->execute([$id]);
                $resent_failed++;
            }
            
            // Delay between SMS sends
            usleep(SMS_DELAY_MICROSECONDS);
            
        } catch (Exception $e) {
            logMessage("Error resending SMS for form {$formNumber} (ID: {$id}): " . $e->getMessage());
            $updateFailedStmt->execute([$id]);
            $resent_failed++;
        }
    }
    
    logMessage("Resend process completed - Successfully resent: {$resent_successfully}, Failed again: {$resent_failed}");
    
} catch (Exception $e) {
    logMessage("Error: " . $e->getMessage());
    exit(1);
}

logMessage("Resend script execution finished");
?>