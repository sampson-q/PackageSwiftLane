<?php
// models/UserModel.php
require_once __DIR__ . '/BaseModel.php';
class UserModel extends BaseModel {
    public function findById(int $id) {
        $this->db->cdp_query("SELECT id, username, email, fname, lname, userlevel, active, lastlogin FROM cdb_users WHERE id = :id LIMIT 1");
        $this->db->bind(':id', $id);
        $this->db->cdp_execute();
        return $this->db->cdp_registro();
    }

    public function findByUsernameOrEmail(string $user) {
        $this->db->cdp_query("SELECT id, username, email, fname, lname, userlevel, active, lastlogin FROM cdb_users WHERE username = :u OR email = :u LIMIT 1");
        $this->db->bind(':u', $user);
        $this->db->cdp_execute();
        return $this->db->cdp_registro();
    }
}
