<?php
require_once '../config/config.php';

// Check authentication
if (!isset($_SESSION['user_id'])) {
    Response::error('Unauthorized', 401);
}

class HostelAPI {
    private $conn;
    private $db;
    
    public function __construct() {
        $this->db = new Database();
        $this->conn = $this->db->getConnection();
    }
    
    public function getAll() {
        $query = "SELECT * FROM hostels ORDER BY hostel_id";
        $stmt = oci_parse($this->conn, $query);
        oci_execute($stmt);
        
        $hostels = [];
        while ($row = oci_fetch_assoc($stmt)) {
            $hostels[] = $row;
        }
        
        Response::success("Hostels retrieved successfully", $hostels);
    }
    
    public function getOne($id) {
        $query = "SELECT * FROM hostels WHERE hostel_id = :id";
        $stmt = oci_parse($this->conn, $query);
        oci_bind_by_name($stmt, ':id', $id);
        oci_execute($stmt);
        
        $hostel = oci_fetch_assoc($stmt);
        if ($hostel) {
            Response::success("Hostel found", $hostel);
        } else {
            Response::error("Hostel not found", 404);
        }
    }
    
    public function create($data) {
        $query = "INSERT INTO hostels (hostel_id, hostel_name, total_rooms, warden_name, contact_number, address) 
                  VALUES (seq_hostels.NEXTVAL, :name, :rooms, :warden, :contact, :address)";
        
        $stmt = oci_parse($this->conn, $query);
        oci_bind_by_name($stmt, ':name', $data['hostel_name']);
        oci_bind_by_name($stmt, ':rooms', $data['total_rooms']);
        oci_bind_by_name($stmt, ':warden', $data['warden_name']);
        oci_bind_by_name($stmt, ':contact', $data['contact_number']);
        oci_bind_by_name($stmt, ':address', $data['address']);
        
        $result = oci_execute($stmt);
        
        if ($result) {
            Response::success("Hostel created successfully");
        } else {
            $error = oci_error($stmt);
            Response::error("Failed to create hostel: " . $error['message']);
        }
    }
    
    public function update($id, $data) {
        $query = "UPDATE hostels SET 
                  hostel_name = :name,
                  total_rooms = :rooms,
                  warden_name = :warden,
                  contact_number = :contact,
                  address = :address
                  WHERE hostel_id = :id";
        
        $stmt = oci_parse($this->conn, $query);
        oci_bind_by_name($stmt, ':id', $id);
        oci_bind_by_name($stmt, ':name', $data['hostel_name']);
        oci_bind_by_name($stmt, ':rooms', $data['total_rooms']);
        oci_bind_by_name($stmt, ':warden', $data['warden_name']);
        oci_bind_by_name($stmt, ':contact', $data['contact_number']);
        oci_bind_by_name($stmt, ':address', $data['address']);
        
        $result = oci_execute($stmt);
        
        if ($result) {
            Response::success("Hostel updated successfully");
        } else {
            $error = oci_error($stmt);
            Response::error("Failed to update hostel: " . $error['message']);
        }
    }
    
    public function delete($id) {
        // Check if hostel has rooms
        $checkQuery = "SELECT COUNT(*) as count FROM rooms WHERE hostel_id = :id";
        $checkStmt = oci_parse($this->conn, $checkQuery);
        oci_bind_by_name($checkStmt, ':id', $id);
        oci_execute($checkStmt);
        $row = oci_fetch_assoc($checkStmt);
        
        if ($row['COUNT'] > 0) {
            Response::error("Cannot delete hostel with existing rooms");
        }
        
        $query = "DELETE FROM hostels WHERE hostel_id = :id";
        $stmt = oci_parse($this->conn, $query);
        oci_bind_by_name($stmt, ':id', $id);
        
        $result = oci_execute($stmt);
        
        if ($result) {
            Response::success("Hostel deleted successfully");
        } else {
            $error = oci_error($stmt);
            Response::error("Failed to delete hostel: " . $error['message']);
        }
    }
    
    public function __destruct() {
        if ($this->db) {
            $this->db->closeConnection();
        }
    }
}

// Handle requests
$api = new HostelAPI();
$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        if (isset($_GET['id'])) {
            $api->getOne($_GET['id']);
        } else {
            $api->getAll();
        }
        break;
        
    case 'POST':
        $data = json_decode(file_get_contents('php://input'), true);
        $api->create($data);
        break;
        
    case 'PUT':
        if (isset($_GET['id'])) {
            $data = json_decode(file_get_contents('php://input'), true);
            $api->update($_GET['id'], $data);
        }
        break;
        
    case 'DELETE':
        if (isset($_GET['id'])) {
            $api->delete($_GET['id']);
        }
        break;
        
    default:
        Response::error("Method not allowed", 405);
}
?>
