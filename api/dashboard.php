<?php
require_once '../config/config.php';

if (!isset($_SESSION['user_id'])) {
    Response::error('Unauthorized', 401);
}

class DashboardAPI {
    private $conn;
    private $db;
    
    public function __construct() {
        $this->db = new Database();
        $this->conn = $this->db->getConnection();
    }
    
    public function getStats() {
        $stats = [];
        
        // Total hostels
        $hostelQuery = "SELECT COUNT(*) as count FROM hostels";
        $hostelStmt = oci_parse($this->conn, $hostelQuery);
        oci_execute($hostelStmt);
        $hostelRow = oci_fetch_assoc($hostelStmt);
        $stats['total_hostels'] = $hostelRow['COUNT'];
        
        // Total rooms
        $roomQuery = "SELECT COUNT(*) as count FROM rooms";
        $roomStmt = oci_parse($this->conn, $roomQuery);
        oci_execute($roomStmt);
        $roomRow = oci_fetch_assoc($roomStmt);
        $stats['total_rooms'] = $roomRow['COUNT'];
        
        // Available rooms
        $availQuery = "SELECT COUNT(*) as count FROM rooms WHERE status = 'available'";
        $availStmt = oci_parse($this->conn, $availQuery);
        oci_execute($availStmt);
        $availRow = oci_fetch_assoc($availStmt);
        $stats['available_rooms'] = $availRow['COUNT'];
        
        // Total students
        $studentQuery = "SELECT COUNT(*) as count FROM students";
        $studentStmt = oci_parse($this->conn, $studentQuery);
        oci_execute($studentStmt);
        $studentRow = oci_fetch_assoc($studentStmt);
        $stats['total_students'] = $studentRow['COUNT'];
        
        // Active students (with allocations)
        $activeQuery = "SELECT COUNT(DISTINCT student_id) as count FROM allocations WHERE status = 'active'";
        $activeStmt = oci_parse($this->conn, $activeQuery);
        oci_execute($activeStmt);
        $activeRow = oci_fetch_assoc($activeStmt);
        $stats['active_students'] = $activeRow['COUNT'];
        
        // Monthly revenue
        $revenueQuery = "SELECT SUM(amount) as total FROM payments 
                         WHERE status = 'completed' 
                         AND EXTRACT(MONTH FROM payment_date) = EXTRACT(MONTH FROM SYSDATE)
                         AND EXTRACT(YEAR FROM payment_date) = EXTRACT(YEAR FROM SYSDATE)";
        $revenueStmt = oci_parse($this->conn, $revenueQuery);
        oci_execute($revenueStmt);
        $revenueRow = oci_fetch_assoc($revenueStmt);
        $stats['monthly_revenue'] = $revenueRow['TOTAL'] ?? 0;
        
        // Total staff
        $staffQuery = "SELECT COUNT(*) as count FROM staff";
        $staffStmt = oci_parse($this->conn, $staffQuery);
        oci_execute($staffStmt);
        $staffRow = oci_fetch_assoc($staffStmt);
        $stats['total_staff'] = $staffRow['COUNT'];
        
        Response::success("Dashboard stats retrieved", $stats);
    }
    
    public function getRecentAllocations($limit = 5) {
        $query = "SELECT a.*, s.full_name as student_name, r.room_number, h.hostel_name
                  FROM allocations a
                  JOIN students s ON a.student_id = s.student_id
                  JOIN rooms r ON a.room_id = r.room_id
                  JOIN hostels h ON r.hostel_id = h.hostel_id
                  WHERE ROWNUM <= :limit
                  ORDER BY a.allocation_date DESC";
        
        $stmt = oci_parse($this->conn, $query);
        oci_bind_by_name($stmt, ':limit', $limit);
        oci_execute($stmt);
        
        $allocations = [];
        while ($row = oci_fetch_assoc($stmt)) {
            $allocations[] = $row;
        }
        
        Response::success("Recent allocations retrieved", $allocations);
    }
    
    public function getRecentPayments($limit = 5) {
        $query = "SELECT p.*, s.full_name as student_name
                  FROM payments p
                  JOIN students s ON p.student_id = s.student_id
                  WHERE ROWNUM <= :limit
                  ORDER BY p.payment_date DESC";
        
        $stmt = oci_parse($this->conn, $query);
        oci_bind_by_name($stmt, ':limit', $limit);
        oci_execute($stmt);
        
        $payments = [];
        while ($row = oci_fetch_assoc($stmt)) {
            $payments[] = $row;
        }
        
        Response::success("Recent payments retrieved", $payments);
    }
    
    public function getOccupancyData() {
        $query = "SELECT status, COUNT(*) as count 
                  FROM rooms 
                  GROUP BY status";
        $stmt = oci_parse($this->conn, $query);
        oci_execute($stmt);
        
        $data = [];
        while ($row = oci_fetch_assoc($stmt)) {
            $data[$row['STATUS']] = $row['COUNT'];
        }
        
        Response::success("Occupancy data retrieved", $data);
    }
    
    public function getRevenueTrend($months = 6) {
        $query = "SELECT TO_CHAR(payment_date, 'YYYY-MM') as month, 
                         SUM(amount) as total
                  FROM payments
                  WHERE status = 'completed'
                  AND payment_date >= ADD_MONTHS(SYSDATE, -:months)
                  GROUP BY TO_CHAR(payment_date, 'YYYY-MM')
                  ORDER BY month";
        
        $stmt = oci_parse($this->conn, $query);
        oci_bind_by_name($stmt, ':months', $months);
        oci_execute($stmt);
        
        $trend = [];
        while ($row = oci_fetch_assoc($stmt)) {
            $trend[] = [
                'month' => $row['MONTH'],
                'total' => $row['TOTAL']
            ];
        }
        
        Response::success("Revenue trend retrieved", $trend);
    }
    
    public function __destruct() {
        if ($this->db) {
            $this->db->closeConnection();
        }
    }
}

// Handle requests
$api = new DashboardAPI();
$method = $_SERVER['REQUEST_METHOD'];

if ($method !== 'GET') {
    Response::error("Method not allowed", 405);
}

if (isset($_GET['recent_allocations'])) {
    $api->getRecentAllocations($_GET['limit'] ?? 5);
} elseif (isset($_GET['recent_payments'])) {
    $api->getRecentPayments($_GET['limit'] ?? 5);
} elseif (isset($_GET['occupancy'])) {
    $api->getOccupancyData();
} elseif (isset($_GET['revenue_trend'])) {
    $api->getRevenueTrend($_GET['months'] ?? 6);
} else {
    $api->getStats();
}
?>
