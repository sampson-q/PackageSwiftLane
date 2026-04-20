<?php
class CustomerModel {
  protected $db;
  public function __construct($db) { $this->db = $db; }
  public function getList($page=1,$per=25) {
    $offset = ($page-1)*$per;
    // Use your existing querys.php functions or run SQL with Conexion
    $sql = "SELECT * FROM cdb_customers LIMIT $offset, $per";
    return $this->db->cdp_query($sql); // adapt to how Conexion returns rows
  }
  public function findById($id) {
    $sql = "SELECT * FROM cdb_customers WHERE id = " . (int)$id;
    return $this->db->cdp_query($sql); // format conversion may be needed
  }
}
