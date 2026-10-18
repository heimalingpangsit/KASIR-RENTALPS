<?php
require __DIR__ . '/../config.php';
boot(); $_SESSION = []; session_destroy();
header('Location: login.php');
