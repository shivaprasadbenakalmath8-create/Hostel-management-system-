<?php
// Database configuration for Oracle 10g
class Database {
    private $host = 'localhost';
    private $port = '1521';
    private $sid = 'XE'; // Oracle SID
    private $username = 'hostel_admin';
    private $password = 'password';
    private $connection;
    
    public function connect() {
        $this->connection = oci_connect(
            $this->username,
            $this->password,
            "{$this->host}:{$this->port}/{$this->sid}"
        );
        
        if (!$this->connection) {
            $error = oci_error();
            throw new Exception("Database connection failed: " . $error['message']);
        }
        
        return $this->connection;
    }
    
    public function disconnect() {
        if ($this->connection) {
            oci_close($this->connection);
        }
    }
}

// API Response handler
class Response {
    public static function json($data, $status = 200) {
        http_response_code($status);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }
    
    public static function success($message, $data = null) {
        self::json([
            'success' => true,
            'message' => $message,
            'data' => $data
        ]);
    }
    
    public static function error($message, $status = 400) {
        self::json([
            'success' => false,
            'message' => $message
        ], $status);
    }
}
?>
