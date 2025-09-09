<?php
require_once 'config.php';

/**
 * Daily status update script
 * Fetches current status for all documents from tracking API
 * Run this via cron every X hours or daily
 * 
 * Document Types: 1=Joint (ID+Passport), 2=ID Card, 3=Passport
 * SMS: Only sent for Albanian numbers (355 prefix) on new insertions
 */

$log_file = LOG_DIR . '/status_update_log.txt';

function logMessage($message) {
    global $log_file;
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($log_file, "[{$timestamp}] {$message}" . PHP_EOL, FILE_APPEND | LOCK_EX);
}


function determineStatus($client) {
    // Map client values to status based on real voucher tracking workflow
    $statusMap = [
        'PPPIS' => 'approved',
        'VERIF' => 'received',
        'IQC' => 'received',
        'PCPIS' => 'approved',
        'INV' => 'received',
        'REQ' => 'received',
        'MP_CMS_SVR' => 'approved',
        'CHECK' => 'received',
        'EXM' => 'received',
        'INVAPP' => 'received',
        'MPERSO_P' => 'approved',
        'MPERSO_C' => 'approved',
        null => 'shipped',
        '' => 'shipped'
    ];
    
    return isset($statusMap[$client]) ? $statusMap[$client] : 'received';
}

try {
    logMessage("Starting status update process - full table replacement");
    
    // Connect to database
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Create import table (identical structure to documents)
    logMessage("Creating import table...");
    $pdo->exec("DROP TABLE IF EXISTS documents_import");
    $pdo->exec("CREATE TABLE documents_import LIKE documents");
    
    // Fetch data from tracking API
    $apiUrl = API_TRACKING_ENDPOINT;
    
    if (function_exists('curl_init')) {
        // Use cURL if available
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => $apiUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTPHEADER => [
                "Content-Type: application/json",
                "Accept: application/json"
            ],
        ]);
        
        $response = curl_exec($curl);
        $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($curl);
        curl_close($curl);
        
        if ($curl_error) {
            logMessage("cURL Error: " . $curl_error . ". Trying fallback method...");
            $response = false;
        } else if ($http_code !== 200) {
            logMessage("API returned HTTP {$http_code}. Trying fallback method...");
            $response = false;
        }
    } else {
        $response = false;
    }
    
    // Fallback methods if cURL fails
    if ($response === false) {
        // Try file_get_contents
        $response = @file_get_contents($apiUrl);
        if ($response === false) {
            logMessage("HTTP access failed, running API directly...");
            // Include the API file directly as a fallback
            ob_start();
            include __DIR__ . '/api/v1/evoucher/tracking.php';
            $response = ob_get_clean();
        }
    }
    
    $data = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception("JSON decode error: " . json_last_error_msg());
    }
    
    if (!is_array($data) || empty($data)) {
        logMessage("No data received from API");
        exit(0);
    }
    
    logMessage("Received " . count($data) . " records from API");
    
    // Prepare insert statement for import table - we're replacing everything, no updates needed
    $insertStmt = $pdo->prepare("
        INSERT INTO documents_import (form_number, client, document_type_id, mobile_phone, status, created_at, updated_at) 
        VALUES (:form_number, :client, :document_type_id, :mobile_phone, :status, NOW(), NOW())
    ");
    
    $inserted = 0;
    $errors = 0;
    
    foreach ($data as $record) {
        if (empty($record['formNumber'])) {
            logMessage("Skipping record with empty formNumber");
            $errors++;
            continue;
        }
        
        try {
            $formNumber = trim($record['formNumber']);
            $client = isset($record['client']) ? trim($record['client']) : '';
            $documentTypeId = isset($record['documentTypeId']) ? (int)$record['documentTypeId'] : 1;
            $mobilePhone = isset($record['mobilePhone']) ? trim($record['mobilePhone']) : '';
            $status = determineStatus($client);
            
            // Insert all records into import table
            $insertStmt->execute([
                ':form_number' => $formNumber,
                ':client' => $client,
                ':document_type_id' => $documentTypeId,
                ':mobile_phone' => $mobilePhone,
                ':status' => $status
            ]);
            $inserted++;
            
        } catch (PDOException $e) {
            logMessage("Database error for form {$formNumber}: " . $e->getMessage());
            $errors++;
        }
    }
    
    // Perform atomic table swap if we have data and no critical errors
    if ($inserted > 0 && $errors == 0) {
        logMessage("Performing atomic table swap...");
        
        // Important: In MySQL, DDL statements (DROP/RENAME/ALTER) cause implicit commits,
        // so wrapping them in an explicit transaction leads to "There is no active transaction"
        // on commit/rollback. Use a single multi-table RENAME for atomicity without transactions.
        try {
            // Drop leftover backup if exists (from prior runs)
            $pdo->exec("DROP TABLE IF EXISTS documents_old");

            // Atomically swap: current -> old, import -> current
            // This single statement is atomic in MySQL, avoiding partial renames
            $pdo->exec("RENAME TABLE documents TO documents_old, documents_import TO documents");

            // Immediately drop the backup to avoid leaving stale copies between runs
            try {
                $pdo->exec("DROP TABLE IF EXISTS documents_old");
                logMessage("Table swap completed; backup documents_old dropped");
            } catch (Exception $dropE) {
                // Not critical if drop fails; log and continue
                logMessage("Table swap completed; failed to drop documents_old: " . $dropE->getMessage());
            }

        } catch (Exception $e) {
            logMessage("Table swap failed: " . $e->getMessage());
            // Preserve import table for debugging if it still exists under that name
            try { $pdo->exec("RENAME TABLE documents_import TO documents_import_failed"); } catch (Exception $inner) {}
            throw $e;
        }
        
    } else {
        logMessage("Skipping table swap - Inserted: {$inserted}, Errors: {$errors}");
        $pdo->exec("DROP TABLE documents_import");
    }
    
    logMessage("Status replacement completed - Inserted: {$inserted}, Errors: {$errors}");
    
    
} catch (Exception $e) {
    logMessage("Error: " . $e->getMessage());
    exit(1);
}

logMessage("Script execution finished");
?>
