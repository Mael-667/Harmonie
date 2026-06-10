<?php

class User{
    public $authenticated = false;
    public $conn;
    public $pseudo;
    public $id;
    public $token;
    public $avatar; 
    public $channelId;
    public $serverId;

    public function __construct($conn) {
        $this->conn = $conn;
    }
}