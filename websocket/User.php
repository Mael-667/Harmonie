<?php

class User{
    public $authenticated = false;
    public $conn;
    public $pseudo;
    public $id;
    public $token;
    public $avatar; 

    public function __construct($conn) {
        $this->conn = $conn;
    }
}