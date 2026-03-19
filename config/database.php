<?php
class Database {
    private $host = 'localhost';
    private $port = '1521';
    private $sid = 'XE';
    private $username = 'hostel_admin';
    private $password = 'password';
    private $connection;
    
    public function getConnection() {
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
    
    public function closeConnection() {
        if ($this->connection) {
            oci_close($this->connection);
        }
    }
}
?>
