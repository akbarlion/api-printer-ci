<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Poller extends CI_Controller
{
    public function __construct()
    {
        parent::__construct();
        
        // Only allow CLI access
        if (!$this->input->is_cli_request()) {
            show_error('This script can only be accessed via command line.');
        }
        
        $this->load->library('Snmp_poller');
        $this->load->library('Alert_service');
    }

    public function poll_all()
    {
        echo "Starting printer polling...\n";
        $this->snmp_poller->poll_all_printers();
        echo "Polling completed.\n";
    }

    public function cleanup_alerts()
    {
        echo "Starting alert cleanup...\n";
        $this->alert_service->cleanup_old_alerts();
        echo "Alert cleanup completed.\n";
    }

    public function poll_single($printer_id)
    {
        if (!$printer_id) {
            echo "Error: Printer ID required\n";
            return;
        }

        $this->load->model('Printer_model');
        $printer = $this->Printer_model->get_by_id($printer_id);
        
        if (!$printer) {
            echo "Error: Printer not found\n";
            return;
        }

        echo "Polling printer: {$printer['name']}\n";
        $this->snmp_poller->poll_printer($printer);
        echo "Polling completed.\n";
    }
}