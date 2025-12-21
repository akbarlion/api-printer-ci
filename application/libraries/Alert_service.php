<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Alert_service
{
    private $CI;

    public function __construct()
    {
        $this->CI =& get_instance();
        $this->CI->load->database();
        $this->CI->load->model('Alert_model');
    }

    public function check_and_create_alerts($printer_id, $printer_name, $metrics)
    {
        try {
            // Check toner/ink levels
            if (isset($metrics['tonerLevel']) && $metrics['tonerLevel'] !== null && $metrics['tonerLevel'] < 20) {
                $this->create_alert([
                    'printer_id' => $printer_id,
                    'printer_name' => $printer_name,
                    'alert_type' => 'toner_low',
                    'severity' => $metrics['tonerLevel'] < 10 ? 'critical' : 'high',
                    'message' => "Toner level is {$metrics['tonerLevel']}%"
                ]);
            }

            // Check individual ink levels
            $ink_levels = [
                ['color' => 'Black', 'level' => $metrics['blackLevel'] ?? null],
                ['color' => 'Cyan', 'level' => $metrics['cyanLevel'] ?? null],
                ['color' => 'Magenta', 'level' => $metrics['magentaLevel'] ?? null],
                ['color' => 'Yellow', 'level' => $metrics['yellowLevel'] ?? null]
            ];

            foreach ($ink_levels as $ink) {
                if ($ink['level'] !== null && $ink['level'] < 20) {
                    $this->create_alert([
                        'printer_id' => $printer_id,
                        'printer_name' => $printer_name,
                        'alert_type' => 'toner_low',
                        'severity' => $ink['level'] < 10 ? 'critical' : 'high',
                        'message' => "{$ink['color']} ink level is {$ink['level']}%"
                    ]);
                }
            }

            // Check paper tray status
            if (isset($metrics['paperTrayStatus']) && 
                stripos($metrics['paperTrayStatus'], 'empty') !== false) {
                $this->create_alert([
                    'printer_id' => $printer_id,
                    'printer_name' => $printer_name,
                    'alert_type' => 'paper_empty',
                    'severity' => 'medium',
                    'message' => 'Paper tray is empty'
                ]);
            }

            // Check device status
            if (isset($metrics['deviceStatus']) && 
                stripos($metrics['deviceStatus'], 'error') !== false) {
                $this->create_alert([
                    'printer_id' => $printer_id,
                    'printer_name' => $printer_name,
                    'alert_type' => 'error',
                    'severity' => 'high',
                    'message' => "Device error: {$metrics['deviceStatus']}"
                ]);
            }

        } catch (Exception $e) {
            log_message('error', 'Error creating alerts: ' . $e->getMessage());
        }
    }

    public function create_offline_alert($printer_id, $printer_name)
    {
        try {
            $this->create_alert([
                'printer_id' => $printer_id,
                'printer_name' => $printer_name,
                'alert_type' => 'offline',
                'severity' => 'critical',
                'message' => 'Printer is offline'
            ]);
        } catch (Exception $e) {
            log_message('error', 'Error creating offline alert: ' . $e->getMessage());
        }
    }

    public function create_alert($alert_data)
    {
        try {
            // Check if similar alert already exists and is not acknowledged
            $this->CI->db->select('id');
            $this->CI->db->where('printer_id', $alert_data['printer_id']);
            $this->CI->db->where('alert_type', $alert_data['alert_type']);
            $this->CI->db->where('is_acknowledged', 0);
            $this->CI->db->where('created_at >', date('Y-m-d H:i:s', strtotime('-1 hour')));
            $existing = $this->CI->db->get('alerts')->result_array();

            // Don't create duplicate alerts within 1 hour
            if (count($existing) > 0) {
                return null;
            }

            // Create new alert
            $alert_data['created_at'] = date('Y-m-d H:i:s');
            $this->CI->db->insert('alerts', $alert_data);
            return $this->CI->db->insert_id();

        } catch (Exception $e) {
            log_message('error', 'Error creating alert: ' . $e->getMessage());
            throw $e;
        }
    }

    public function cleanup_old_alerts()
    {
        try {
            // Delete acknowledged alerts older than 30 days
            $this->CI->db->where('is_acknowledged', 1);
            $this->CI->db->where('acknowledged_at <', date('Y-m-d H:i:s', strtotime('-30 days')));
            $this->CI->db->delete('alerts');

            log_message('info', 'Old alerts cleaned up');
        } catch (Exception $e) {
            log_message('error', 'Error cleaning up old alerts: ' . $e->getMessage());
        }
    }
}