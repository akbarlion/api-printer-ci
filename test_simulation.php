<?php
// Define BASEPATH to satisfy the check in Snmp_service
define('BASEPATH', __DIR__);

// Load the library
require_once 'application/libraries/Snmp_service.php';

$snmp = new Snmp_service();
$snmp->set_simulation(true); // Enable simulation mode

echo "--- Simulating Printer Metrics ---\n";
try {
    $metrics = $snmp->get_printer_metrics('127.0.0.1');
    echo json_encode($metrics, JSON_PRETTY_PRINT) . "\n\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

echo "--- Simulating Printer Details ---\n";
try {
    $details = $snmp->get_printer_details('127.0.0.1');
    echo json_encode($details, JSON_PRETTY_PRINT) . "\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
