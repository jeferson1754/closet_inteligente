<?php
include 'bd.php';

$sql = "SELECT * FROM prendas";
$result = $mysqli_obj->query($sql);

$prendas = [];
while ($row = $result->fetch_assoc()) {
    $prendas[] = $row;
}

echo json_encode($prendas, JSON_UNESCAPED_UNICODE);
