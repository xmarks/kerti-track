<?php
/**
 * Document Type Definitions
 * 
 * Document Types:
 * 1 = Joint Application (ID Card + Passport combined)
 * 2 = ID Card only
 * 3 = Passport only
 * 
 * Note: Residence permit tracking is not provided by this system
 */

function getDocumentTypeName($typeId) {
    $types = [
        1 => 'Joint Application (ID Card + Passport)',
        2 => 'ID Card',
        3 => 'Passport'
    ];
    
    return $types[$typeId] ?? 'Unknown Document Type';
}

function getDocumentTypeShortName($typeId) {
    $types = [
        1 => 'Joint',
        2 => 'ID Card', 
        3 => 'Passport'
    ];
    
    return $types[$typeId] ?? 'Unknown';
}

function getDocumentTypeTranslations($lang = 'sq') {
    $translations = [
        'sq' => [
            1 => 'Aplikim i Përbashkët (Letërnjoftim + Pasaportë)',
            2 => 'Letërnjoftim',
            3 => 'Pasaportë'
        ],
        'en' => [
            1 => 'Joint Application (ID Card + Passport)',
            2 => 'ID Card',
            3 => 'Passport'
        ]
    ];
    
    return $translations[$lang] ?? $translations['en'];
}

function isValidDocumentType($typeId) {
    return in_array((int)$typeId, [1, 2, 3]);
}

function getAvailableDocumentTypes() {
    return [
        1 => 'Joint Application (ID Card + Passport)',
        2 => 'ID Card', 
        3 => 'Passport'
    ];
}

?>