<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Alert_model extends CI_Model
{
    private $table = 'PrinterAlerts'; // Sesuai database asli

    public function __construct()
    {
        parent::__construct();
        $this->load->database();
    }

    public function get_all($limit = 50, $offset = 0)
    {
        $this->db->select('id, printerId, printerName, alertType, severity, message, isAcknowledged, acknowledgedAt, createdAt');
        $this->db->limit($limit, $offset);
        $this->db->order_by('createdAt', 'DESC');
        $query = $this->db->get($this->table);
        return $query->result_array();
    }

    public function get_stats()
    {
        $this->db->select('COUNT(*) as total, SUM(CASE WHEN isAcknowledged = 0 THEN 1 ELSE 0 END) as active, SUM(CASE WHEN isAcknowledged = 1 THEN 1 ELSE 0 END) as acknowledged');
        $query = $this->db->get($this->table);
        return $query->row_array();
    }

    public function acknowledge($id, $acknowledged_by)
    {
        $data = [
            'isAcknowledged' => 1,
            'acknowledgedBy' => $acknowledged_by,
            'acknowledgedAt' => date('Y-m-d H:i:s')
        ];
        
        $this->db->where('id', $id);
        return $this->db->update($this->table, $data);
    }

    public function delete($id)
    {
        $this->db->where('id', $id);
        return $this->db->delete($this->table);
    }

    public function get_by_printer($printer_id)
    {
        $this->db->select('id, printerId, printerName, alertType, severity, message, isAcknowledged, acknowledgedAt, createdAt');
        $this->db->where('printerId', $printer_id);
        $this->db->order_by('createdAt', 'DESC');
        $query = $this->db->get($this->table);
        return $query->result_array();
    }

    public function create($data)
    {
        // Generate UUID for id (sesuai database asli)
        if (!isset($data['id'])) {
            $data['id'] = $this->generate_uuid();
        }
        return $this->db->insert($this->table, $data);
    }

    private function generate_uuid()
    {
        return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }
}