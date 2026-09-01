<?php
// Forward legacy requests to home.php
$queryString = !empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '';
header("Location: home.php" . $queryString);
exit();