<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Printer_model extends CI_Model
{
    private $table = 'Printers'; // Sesuai database asli
    private $metrics_table = 'PrinterMetrics'; // Sesuai database asli

    public function __construct()
    {
        parent::__construct();
        $this->load->database();
    }

    public function get_all_active()
    {
        $this->db->where('isActive', 1);
        $this->db->order_by('name', 'ASC');
        $query = $this->db->get($this->table);
        return $query->result_array();
    }

    public function get_by_id($id)
    {
        $this->db->where('id', $id);
        $query = $this->db->get($this->table);
        return $query->row_array();
    }

    public function get_with_metrics($id)
    {
        $printer = $this->get_by_id($id);
        if ($printer) {
            $this->db->where('printerId', $id);
            $this->db->order_by('createdAt', 'DESC');
            $this->db->limit(10);
            $metrics = $this->db->get($this->metrics_table)->result_array();
            $printer['metrics'] = $metrics;
        }
        return $printer;
    }

    public function create($data)
    {
        // Generate UUID for id (sesuai database asli)
        if (!isset($data['id'])) {
            $data['id'] = $this->generate_uuid();
        }
        return $this->db->insert($this->table, $data);
    }

    public function get_insert_id()
    {
        return $this->db->insert_id();
    }

    public function update($id, $data)
    {
        $this->db->where('id', $id);
        return $this->db->update($this->table, $data);
    }

    public function soft_delete($id)
    {
        $data = ['isActive' => 0];
        $this->db->where('id', $id);
        return $this->db->update($this->table, $data);
    }

    public function exists($id)
    {
        $this->db->where('id', $id);
        return $this->db->count_all_results($this->table) > 0;
    }

    private function generate_uuid()
    {
        // Simple UUID v4 generator
        return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }
}