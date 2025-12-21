<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class User_model extends CI_Model
{
    private $table = 'Users'; // Sesuai database asli

    public function __construct()
    {
        parent::__construct();
        $this->load->database();
    }

    public function get_by_username($username)
    {
        $this->db->where('username', $username);
        $this->db->where('isActive', 1);
        $query = $this->db->get($this->table);
        return $query->row_array();
    }

    public function get_by_email($email)
    {
        $this->db->where('email', $email);
        $query = $this->db->get($this->table);
        return $query->row_array();
    }

    public function check_user_exists($username, $email)
    {
        $this->db->where('username', $username);
        $this->db->or_where('email', $email);
        $query = $this->db->get($this->table);
        return $query->num_rows() > 0;
    }

    public function create_user($data)
    {
        return $this->db->insert($this->table, $data);
    }

    public function get_insert_id()
    {
        return $this->db->insert_id();
    }

    public function update_refresh_token($user_id, $refresh_token, $expires)
    {
        $data = [
            'refresh_token' => $refresh_token,
            'refresh_token_expires' => $expires,
            'lastLogin' => date('Y-m-d H:i:s')
        ];
        
        $this->db->where('id', $user_id);
        return $this->db->update($this->table, $data);
    }

    public function get_by_refresh_token($refresh_token)
    {
        $this->db->where('refresh_token', $refresh_token);
        $this->db->where('refresh_token_expires >', date('Y-m-d H:i:s'));
        $query = $this->db->get($this->table);
        return $query->row_array();
    }

    public function clear_refresh_token($refresh_token)
    {
        $data = [
            'refresh_token' => null,
            'refresh_token_expires' => null
        ];
        
        $this->db->where('refresh_token', $refresh_token);
        return $this->db->update($this->table, $data);
    }
}