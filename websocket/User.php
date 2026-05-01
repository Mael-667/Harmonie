<?php

class User{
    public $authenticated = false;
    public $conn;

    public function __construct($conn) {
        $this->conn = $conn;
    }
}