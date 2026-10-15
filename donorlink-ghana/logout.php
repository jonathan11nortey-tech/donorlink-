<?php

require_once "config/session.php";

// Remove all session variables
$_SESSION = [];

// Destroy the session
session_destroy();

// Return to login
header("Location: login.php");
exit;