<?php
// models/BaseModel.php
class BaseModel {
    protected $db;
    public function __construct($db = null) {
        $this->db = $db ?? $GLOBALS['db'];
    }
}
