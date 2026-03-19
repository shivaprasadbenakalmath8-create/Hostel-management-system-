<?php
require_once '../config/config.php';

if (!isset($_SESSION['user_id'])) {
    Response::error('Unauthorized', 401);
}

class PaymentAPI {
    private $conn;
    private $db;
    
    public function __construct() {
        $this->db = new Database();
        $this->conn = $this->db->getConnection();
    }
    
    public function getAll() {
        $query = "SELECT p.*, s.full_name as student_name, s.reg_number
                  FROM payments p
                  JOIN students s ON p.student_id = s.student_id
                  ORDER BY p.payment_date DESC";
        $stmt = oci_parse($this->conn, $query);
        oci_execute($stmt);
        
        $payments = [];
        while ($row = oci_fetch_assoc($stmt)) {
            $payments[] = $row;
        }
        
        Response::success("Payments retrieved successfully", $payments);
    }
    
    public function getByStudent($studentId) {
        $query = "SELECT * FROM payments 
                  WHERE student_id = :student_id 
                  ORDER BY payment_date DESC";
        $stmt = oci_parse($this->conn, $query);
        oci_bind_by_name($stmt, ':student_id', $studentId);
        oci_execute($stmt);
        
        $payments = [];
        while ($row = oci_fetch_assoc($stmt)) {
            $payments[] = $row;
        }
        
        Response::success("Payments retrieved successfully", $payments);
    }
    
    public function getOne($id) {
        $query = "SELECT p.*, s.full_name as student_name, s.reg_number
                  FROM payments p
                  JOIN students s ON p.student_id = s.student_id
                  WHERE p.payment_id = :id";
        $stmt = oci_parse($this->conn, $query);
        oci_bind_by_name($stmt, ':id', $id);
        oci_execute($stmt);
        
        $payment = oci_fetch_assoc($stmt);
        if ($payment) {
            Response::success("Payment found", $payment);
        } else {
            Response::error("Payment not found", 404);
        }
    }
    
    public function getStats() {
        // Total collected
        $totalQuery = "SELECT SUM(amount) as total FROM payments WHERE status = 'completed'";
        $totalStmt = oci_parse($this->conn, $totalQuery);
        oci_execute($totalStmt);
        $totalRow = oci_fetch_assoc($totalStmt);
        
        // Monthly collection
        $monthlyQuery = "SELECT SUM(amount) as monthly FROM payments 
                         WHERE status = 'completed' 
                         AND EXTRACT(MONTH FROM payment_date) = EXTRACT(MONTH FROM SYSDATE)
                         AND EXTRACT(YEAR FROM payment_date) = EXTRACT(YEAR FROM SYSDATE)";
        $monthlyStmt = oci_parse($this->conn, $monthlyQuery);
        oci_execute($monthlyStmt);
        $monthlyRow = oci_fetch_assoc($monthlyStmt);
        
        // Total transactions
        $countQuery = "SELECT COUNT(*) as count FROM payments WHERE status = 'completed'";
        $countStmt = oci_parse($this->conn, $countQuery);
        oci_execute($countStmt);
        $countRow = oci_fetch_assoc($countStmt);
        
        $stats = [
            'total_collected' => $totalRow['TOTAL'] ?? 0,
            'monthly_collected' => $monthlyRow['MONTHLY'] ?? 0,
            'total_transactions' => $countRow['COUNT'] ?? 0
        ];
        
        Response::success("Payment stats retrieved", $stats);
    }
    
    public function create($data) {
        // Generate receipt number
        $receiptNumber = 'RCP-' . date('Ymd') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
        
        $query = "INSERT INTO payments (payment_id, student_id, amount, payment_type, 
                  payment_method, status, receipt_number, description) 
                  VALUES (seq_payments.NEXTVAL, :student_id, :amount, :payment_type, 
                  :payment_method, 'completed', :receipt_number, :description)";
        
        $stmt = oci_parse($this->conn, $query);
        oci_bind_by_name($stmt, ':student_id', $data['student_id']);
        oci_bind_by_name($stmt, ':amount', $data['amount']);
        oci_bind_by_name($stmt, ':payment_type', $data['payment_type']);
        oci_bind_by_name($stmt, ':payment_method', $data['payment_method']);
        oci_bind_by_name($stmt, ':receipt_number', $receiptNumber);
        oci_bind_by_name($stmt, ':description', $data['description']);
        
        $result = oci_execute($stmt);
        
        if ($result) {
            Response::success("Payment recorded successfully", ['receipt_number' => $receiptNumber]);
        } else {
            $error = oci_error($stmt);
            Response::error("Failed to record payment: " . $error['message']);
        }
    }
    
    public function getReceipt($id) {
        $query = "SELECT p.*, s.full_name, s.reg_number, s.course
                  FROM payments p
                  JOIN students s ON p.student_id = s.student_id
                  WHERE p.payment_id = :id";
        $stmt = oci_parse($this->conn, $query);
        oci_bind_by_name($stmt, ':id', $id);
        oci_execute($stmt);
        
        $receipt = oci_fetch_assoc($stmt);
        if ($receipt) {
            Response::success("Receipt retrieved", $receipt);
        } else {
            Response::error("Receipt not found", 404);
        }
    }
    
    public function filter($filters) {
        $query = "SELECT p.*, s.full_name as student_name, s.reg_number
                  FROM payments p
                  JOIN students s ON p.student_id = s.student_id
                  WHERE 1=1";
        
        $params = [];
        
        if (!empty($filters['from_date'])) {
            $query .= " AND p.payment_date >= TO_DATE(:from_date, 'YYYY-MM-DD')";
            $params[':from_date'] = $filters['from_date'];
        }
        
        if (!empty($filters['to_date'])) {
            $query .= " AND p.payment_date <= TO_DATE(:to_date, 'YYYY-MM-DD')";
            $params[':to_date'] = $filters['to_date'];
        }
        
        if (!empty($filters['payment_type'])) {
            $query .= " AND p.payment_type = :payment_type";
            $params[':payment_type'] = $filters['payment_type'];
        }
        
        if (!empty($filters['status'])) {
            $query .= " AND p.status = :status";
            $params[':status'] = $filters['status'];
        }
        
        $query .= " ORDER BY p.payment_date DESC";
        
        $stmt = oci_parse($this->conn, $query);
        
        foreach ($params as $key => $value) {
            oci_bind_by_name($stmt, $key, $params[$key]);
        }
        
        oci_execute($stmt);
        
        $payments = [];
        while ($row = oci_fetch_assoc($stmt)) {
            $payments[] = $row;
        }
        
        Response::success("Filtered payments retrieved", $payments);
    }
    
    public function __destruct() {
        if ($this->db) {
            $this->db->closeConnection();
        }
    }
}

// Handle requests
$api = new PaymentAPI();
$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        if (isset($_GET['id']) && isset($_GET['receipt'])) {
            $api->getReceipt($_GET['id']);
        } elseif (isset($_GET['id'])) {
            $api->getOne($_GET['id']);
        } elseif (isset($_GET['student_id'])) {
            $api->getByStudent($_GET['student_id']);
        } elseif (isset($_GET['stats'])) {
            $api->getStats();
        } else {
            $api->getAll();
        }
        break;
        
    case 'POST':
        $data = json_decode(file_get_contents('php://input'), true);
        if (isset($data['filters'])) {
            $api->filter($data['filters']);
        } else {
            $api->create($data);
        }
        break;
        
    default:
        Response::error("Method not allowed", 405);
}
?>
