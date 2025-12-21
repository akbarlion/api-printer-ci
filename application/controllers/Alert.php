<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH . '/libraries/JWTExceptionWithPayloadInterface.php';
require_once APPPATH . '/libraries/JWT.php';
require_once APPPATH . '/libraries/ExpiredException.php';
require_once APPPATH . '/libraries/BeforeValidException.php';
require_once APPPATH . '/libraries/SignatureInvalidException.php';
require_once APPPATH . '/libraries/JWK.php';
require_once APPPATH . '/libraries/Key.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class Alert extends CI_Controller
{
    private $jwt_key = '7f8a9b2c4d6e1f3a5b7c9d0e2f4a6b8c1d3e5f7a9b2c4d6e8f0a1b3c5d7e9f2a4b6c8d0e1f3a5b7c9d0e2f4a6b8c';

    public function __construct()
    {
        parent::__construct();
        $this->load->model('Alert_model');
        $this->load->helper('url');
        header('Content-Type: application/json');
    }

    public function test()
    {
        if ($this->input->method() !== 'get') {
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'Method not allowed']);
            return;
        }

        try {
            $this->db->select('COUNT(*) as count');
            $query = $this->db->get('PrinterAlerts'); // Sesuai table asli
            $result = $query->row_array();
            
            echo json_encode([
                'success' => true,
                'count' => (int)$result['count']
            ]);
        } catch (Exception $e) {
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }

    private function verify_token()
    {
        $headers = $this->input->request_headers();
        $token = null;

        if (isset($headers['Authorization'])) {
            $auth = $headers['Authorization'];
            if (strpos($auth, 'Bearer ') === 0) {
                $token = substr($auth, 7);
            }
        }

        if (!$token) {
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'Token required']);
            exit;
        }

        try {
            $decoded = JWT::decode($token, new Key($this->jwt_key, 'HS256'));
            return $decoded;
        } catch (Exception $e) {
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'Invalid token']);
            exit;
        }
    }

    public function index()
    {
        if ($this->input->method() !== 'get') {
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'Method not allowed']);
            return;
        }

        try {
            $limit = $this->input->get('limit') ?: 50;
            $offset = $this->input->get('offset') ?: 0;
            $status = $this->input->get('status');
            $severity = $this->input->get('severity');
            $printer = $this->input->get('printer');

            $alerts = $this->Alert_model->get_all((int)$limit, (int)$offset);

            // Filter by status
            if ($status) {
                $alerts = array_filter($alerts, function($alert) use ($status) {
                    if ($status === 'active') return !$alert['isAcknowledged']; // Sesuai column asli
                    if ($status === 'acknowledged') return $alert['isAcknowledged'];
                    return true;
                });
            }

            // Filter by severity
            if ($severity) {
                $alerts = array_filter($alerts, function($alert) use ($severity) {
                    return $alert['severity'] === $severity;
                });
            }

            // Filter by printer
            if ($printer) {
                $alerts = array_filter($alerts, function($alert) use ($printer) {
                    return stripos($alert['printerName'], $printer) !== false || // Sesuai column asli
                           $alert['printerId'] === $printer; // Sesuai column asli
                });
            }

            echo json_encode([
                'success' => true,
                'data' => array_values($alerts),
                'total' => count($alerts)
            ]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }

    public function stats()
    {
        if ($this->input->method() !== 'get') {
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'Method not allowed']);
            return;
        }

        try {
            $stats = $this->Alert_model->get_stats();
            
            echo json_encode([
                'success' => true,
                'data' => $stats
            ]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }

    public function acknowledge($id)
    {
        if ($this->input->method() !== 'put') {
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'Method not allowed']);
            return;
        }

        $user = $this->verify_token();

        try {
            $success = $this->Alert_model->acknowledge($id, $user->username);
            
            if (!$success) {
                http_response_code(404);
                echo json_encode([
                    'success' => false,
                    'error' => 'Alert not found'
                ]);
                return;
            }
            
            echo json_encode([
                'success' => true,
                'message' => 'Alert acknowledged successfully'
            ]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }

    public function delete($id)
    {
        if ($this->input->method() !== 'delete') {
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'Method not allowed']);
            return;
        }

        $user = $this->verify_token();

        if ($user->role !== 'admin') {
            http_response_code(403);
            echo json_encode([
                'success' => false,
                'error' => 'Admin access required'
            ]);
            return;
        }

        try {
            $success = $this->Alert_model->delete($id);
            
            if (!$success) {
                http_response_code(404);
                echo json_encode([
                    'success' => false,
                    'error' => 'Alert not found'
                ]);
                return;
            }
            
            echo json_encode([
                'success' => true,
                'message' => 'Alert deleted successfully'
            ]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }

    public function by_printer($printer_id)
    {
        if ($this->input->method() !== 'get') {
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'Method not allowed']);
            return;
        }

        try {
            $alerts = $this->Alert_model->get_by_printer($printer_id);
            
            echo json_encode([
                'success' => true,
                'data' => $alerts
            ]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }
}