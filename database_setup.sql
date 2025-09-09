CREATE TABLE documents (
    id INT NOT NULL AUTO_INCREMENT,
    form_number VARCHAR(50) NOT NULL,
    status ENUM('received', 'approved', 'shipped') NOT NULL DEFAULT 'received',
    client VARCHAR(100) DEFAULT NULL,
    document_type_id VARCHAR(20) DEFAULT NULL,
    mobile_phone VARCHAR(20) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    PRIMARY KEY (id),
    UNIQUE KEY uk_form_doc_type (form_number, document_type_id),
    INDEX idx_form_number (form_number),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE IF NOT EXISTS failed_sms (
    id INT AUTO_INCREMENT PRIMARY KEY,
    form_number VARCHAR(255) NOT NULL,
    mobile_phone VARCHAR(20) NOT NULL,
    error_message TEXT,
    http_code INT,
    failed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    retry_count INT DEFAULT 0,
    resent_successfully TINYINT(1) DEFAULT 0,
    resent_at TIMESTAMP NULL,
    INDEX idx_form_number (form_number),
    INDEX idx_failed_at (failed_at),
    INDEX idx_resent_successfully (resent_successfully)
);