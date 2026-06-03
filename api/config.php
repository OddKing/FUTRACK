<?php
/**
 * Database Configuration
 * Futbol 7 Tracker
 */

// Restringir CORS a dominios conocidos
$allowed_origins = [
    'https://tracker.mueblesbarguay.cl',
    'http://localhost',
    'http://localhost:8081',
    'exp://192.168.100.4:8081',
];
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (in_array($origin, $allowed_origins)) {
    header("Access-Control-Allow-Origin: $origin");
} else {
    header("Access-Control-Allow-Origin: https://tracker.mueblesbarguay.cl");
}
header("Access-Control-Allow-Methods: POST, GET, OPTIONS, DELETE");
header("Access-Control-Allow-Headers: Content-Type, X-API-KEY");
header("Content-Type: application/json; charset=UTF-8");

// Seguridad FUTRACK
// La contraseña del dashboard se lee desde una variable de entorno (definida en docker-compose.yml)
define('DASHBOARD_PASS', getenv('DASHBOARD_PASS') ?: 'cambiar_esta_clave_en_produccion');
define('GOOGLE_CLIENT_ID', '82080578361-f73c7afmgpkf4t8aiak16f3vb9ba1jvj.apps.googleusercontent.com');
define('MAX_TRACKING_POINTS', 10000); // Límite de puntos GPS por request (anti-DoS)

function get_auth_key() {
    // 1. Intentar con getallheaders (Apache/Nginx/Modern PHP)
    if (function_exists('getallheaders')) {
        $headers = getallheaders();
        foreach ($headers as $name => $value) {
            if (strcasecmp($name, 'X-API-KEY') === 0) return $value;
        }
    }
    // 2. Intentar con $_SERVER (Caso común en PHP built-in server)
    $server_keys = ['HTTP_X_API_KEY', 'X_API_KEY', 'X_Api_Key'];
    foreach ($server_keys as $key) {
        if (isset($_SERVER[$key])) return $_SERVER[$key];
    }
    return '';
}

function get_user_id($pdo) {
    $token = get_auth_key();
    if (empty($token)) return null;
    
    $stmt = $pdo->prepare("SELECT id FROM users WHERE api_token = ?");
    $stmt->execute([$token]);
    $user = $stmt->fetch();
    return $user ? $user['id'] : null;
}

// Leer configuración de variables de entorno (para Docker) o usar defaults (para XAMPP local)
$host = getenv('DB_HOST') ?: 'localhost';
$db   = getenv('DB_NAME') ?: 'futbol7_tracker';
$user = getenv('DB_USER') ?: 'root';
$pass = getenv('DB_PASS') !== false ? getenv('DB_PASS') : '';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
     $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
     http_response_code(500);
     echo json_encode([
         'status' => 'error', 
         'message' => 'Error de conexión BD: ' . $e->getMessage()
     ]);
     exit;
}
?>
