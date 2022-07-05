<?php
ob_start();

try {

$con = new PDO("mysql:dbname=doodle;host=localhost", "root", "root");
$con->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_WARNING);
echo 'a';

}
catch(PDOException $e) {
   echo  "connection failed: " . $e->getMessage();
}
?>