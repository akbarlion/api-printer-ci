<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Snmp_poller
{
    private $CI;

    public function __construct()
    {
        $this->CI =& get_instance();
        $this->CI->load->database();
        $this->CI->load->model('Printer_model');
        $this->CI->load->library('Snmp_service');
        $this->CI->load->library('Hp_web_service');
        $this->CI->load->library('Alert_service');
    }

    public function poll_printer($printer)
    {
        try {
            $metrics = null;
            
            try {
                // Try SNMP first
                $metrics = $this->CI->snmp_service->get_printer_metrics($printer['ip_address']);
                
                // If SNMP returns all 0% or null, try HP Web
                $has_valid_data = ($metrics['blackLevel'] ?? 0) > 0 || 
                                 ($metrics['cyanLevel'] ?? 0) > 0 || 
                                 ($metrics['magentaLevel'] ?? 0) > 0 || 
                                 ($metrics['yellowLevel'] ?? 0) > 0;
                
                if (!$has_valid_data && stripos($printer['model'] ?? '', 'hp') !== false) {
                    log_message('info', "SNMP returned 0% for {$printer['name']}, trying HP Web...");
                    $hp_result = $this->CI->hp_web_service->get_ink_levels($printer['ip_address']);
                    if ($hp_result['success']) {
                        $metrics = array_merge($metrics, $hp_result['data']);
                        log_message('info', "HP Web successful for {$printer['name']}");
                    }
                }
            } catch (Exception $snmp_error) {
                // If SNMP fails completely, try HP Web
                log_message('info', "SNMP failed for {$printer['name']}, trying HP Web...");
                $hp_result = $this->CI->hp_web_service->get_ink_levels($printer['ip_address']);
                if ($hp_result['success']) {
                    $metrics = $hp_result['data'];
                    log_message('info', "HP Web fallback successful for {$printer['name']}");
                } else {
                    throw $snmp_error; // Re-throw original SNMP error
                }
            }
            
            // Save metrics to database
            $metrics_data = [
                'printer_id' => $printer['id'],
                'cyan_level' => $metrics['cyanLevel'],
                'magenta_level' => $metrics['magentaLevel'],
                'yellow_level' => $metrics['yellowLevel'],
                'black_level' => $metrics['blackLevel'],
                'toner_level' => $metrics['tonerLevel'],
                'paper_tray_status' => $metrics['paperTrayStatus'] ?? null,
                'page_counter' => $metrics['pageCounter'] ?? 0,
                'device_status' => $metrics['deviceStatus'] ?? null,
                'printer_type' => $metrics['printerType'] ?? null,
                'created_at' => date('Y-m-d H:i:s')
            ];
            
            $this->CI->db->insert('printer_metrics', $metrics_data);
            
            // Check for alerts
            $this->CI->alert_service->check_and_create_alerts($printer['id'], $printer['name'], $metrics);
            
            // Update printer status
            $this->CI->Printer_model->update($printer['id'], [
                'status' => 'online',
                'last_polled' => date('Y-m-d H:i:s')
            ]);
            
            log_message('info', "Polled printer {$printer['name']} successfully");
            
        } catch (Exception $e) {
            // Update printer as offline
            $this->CI->Printer_model->update($printer['id'], [
                'status' => 'offline',
                'last_polled' => date('Y-m-d H:i:s')
            ]);
            
            // Create offline alert
            $this->CI->alert_service->create_offline_alert($printer['id'], $printer['name']);
            
            log_message('error', "Failed to poll printer {$printer['name']}: " . $e->getMessage());
        }
    }

    public function poll_all_printers()
    {
        try {
            $printers = $this->CI->Printer_model->get_all_active();
            
            log_message('info', "Starting polling for " . count($printers) . " printers");
            
            foreach ($printers as $printer) {
                $this->poll_printer($printer);
            }
        } catch (Exception $e) {
            log_message('error', 'Error in polling cycle: ' . $e->getMessage());
        }
    }

    public function start_polling_scheduler()
    {
        // This would be called from a cron job or background process
        // For CI3, we'll create a CLI controller instead
        log_message('info', 'SNMP polling scheduler would start here');
        log_message('info', 'Create a cron job to call: php index.php cli/poller poll_all');
        log_message('info', 'Create a cron job to call: php index.php cli/poller cleanup_alerts');
    }
}