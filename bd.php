<?php
// Configuración de acceso a la base de datos
$host = 'localhost';
$db   = 'epiz_32740026_r_user';
$user = 'root';
$pass = '';
$charset = 'utf8';

date_default_timezone_set('America/Santiago');

$fecha_actual = date('Y-m-d');
$datetime_actual = date('Y-m-d H:i:s');

$fecha_manana = date('d-m-Y', strtotime('+1 day'));


include 'funciones.php';


// MÉTODO 1: CONEXIÓN PDO
try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=$charset", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    // echo "Conexión PDO exitosa.";
} catch (PDOException $e) {
    echo "Error PDO: " . $e->getMessage();
}

// MÉTODO 2: MYSQLI ORIENTADO A OBJETOS
$mysqli_obj = new mysqli($host, $user, $pass, $db);
if ($mysqli_obj->connect_error) {
    echo "Error MySQLi (OOP): " . $mysqli_obj->connect_error;
    exit;
}
$mysqli_obj->set_charset("utf8");
// echo "Conexión MySQLi (OOP) exitosa.";

// MÉTODO 3: MYSQLI PROCEDIMENTAL
$mysqli_proc = mysqli_connect($host, $user, $pass, $db);
if (!$mysqli_proc) {
    echo "Error MySQLi (procedimental): " . mysqli_connect_error();
    exit;
}
mysqli_set_charset($mysqli_proc, "utf8");
// echo "Conexión MySQLi (procedimental) exitosa.";


// Obtener inicio de la semana actual (lunes 00:00:00)
$current_week_start = (new DateTime('monday this week', new DateTimeZone('America/Santiago')))
    ->setTime(0, 0, 0)
    ->format('Y-m-d H:i:s');

// Obtener fin de la semana actual (domingo 23:59:59)
$current_week_end = (new DateTime('sunday this week', new DateTimeZone('America/Santiago')))
    ->setTime(23, 59, 59)
    ->format('Y-m-d H:i:s');
