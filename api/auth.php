<?php
/**
 * FUTRACK - Authentication API
 * Handles Register, Login, and Google Auth
 */

require_once 'config.php';

// Permitir peticiones OPTIONS (CORS preflight)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit;
}

$action = $_GET['action'] ?? '';

if ($action === 'register') {
    $name = $_POST['name'] ?? '';
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';

    if (empty($name) || empty($email) || empty($password)) {
        http_response_code(400);
        echo json_encode(['error' => 'Todos los campos son obligatorios']);
        exit;
    }

    $hash = password_hash($password, PASSWORD_BCRYPT);
    $token = bin2hex(random_bytes(32));

    try {
        $stmt = $pdo->prepare("INSERT INTO users (name, email, password_hash, api_token) VALUES (?, ?, ?, ?)");
        $stmt->execute([$name, $email, $hash, $token]);
        $user_id = $pdo->lastInsertId();
        
        // Crear jugador por defecto
        $stmt_player = $pdo->prepare("INSERT INTO players (user_id, name, number, team) VALUES (?, ?, ?, ?)");
        $stmt_player->execute([$user_id, 'Yo (Principal)', 10, 'Mi Equipo']);
        
        echo json_encode(['status' => 'success', 'api_token' => $token, 'name' => $name]);
    } catch (PDOException $e) {
        http_response_code(400);
        if ($e->getCode() == 23000) {
            echo json_encode(['error' => 'El correo ya está registrado']);
        } else {
            echo json_encode(['error' => 'Error al registrar: ' . $e->getMessage()]);
        }
    }
    exit;
}

if ($action === 'login') {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';

    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password_hash'])) {
        $token = bin2hex(random_bytes(32));
        $pdo->prepare("UPDATE users SET api_token = ? WHERE id = ?")->execute([$token, $user['id']]);
        echo json_encode(['status' => 'success', 'api_token' => $token, 'name' => $user['name']]);
    } else {
        http_response_code(401);
        echo json_encode(['error' => 'Credenciales incorrectas']);
    }
    exit;
}

if ($action === 'google_login') {
    $id_token = $_POST['credential'] ?? '';
    
    if (empty($id_token)) {
        http_response_code(400);
        echo json_encode(['error' => 'No se recibió la credencial de Google']);
        exit;
    }

    // Verificar token con Google API usando CURL (más estable)
    $url = "https://oauth2.googleapis.com/tokeninfo?id_token=" . $id_token;
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error) {
        http_response_code(500);
        echo json_encode(['error' => 'Error de conexión con Google: ' . $error]);
        exit;
    }

    $payload = json_decode($response, true);

    if ($httpCode === 200 && isset($payload['email'])) {
        $email = $payload['email'];
        $name = $payload['name'] ?? 'Usuario Google';
        $google_id = $payload['sub'];

        // Buscar o crear usuario
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        $token = bin2hex(random_bytes(32));

        if ($user) {
            $pdo->prepare("UPDATE users SET api_token = ?, google_id = ? WHERE id = ?")
                ->execute([$token, $google_id, $user['id']]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO users (name, email, google_id, api_token) VALUES (?, ?, ?, ?)");
            $stmt->execute([$name, $email, $google_id, $token]);
            $user_id = $pdo->lastInsertId();
            
            // Crear jugador por defecto
            $stmt_player = $pdo->prepare("INSERT INTO players (user_id, name, number, team) VALUES (?, ?, ?, ?)");
            $stmt_player->execute([$user_id, 'Yo (Principal)', 10, 'Mi Equipo']);
        }

        echo json_encode(['status' => 'success', 'api_token' => $token, 'name' => $name]);
    } else {
        http_response_code(401);
        $errorMsg = $payload['error_description'] ?? 'Token de Google inválido (Expedido o Malformado)';
        echo json_encode(['error' => $errorMsg]);
    }
    exit;
}

http_response_code(400);
echo json_encode(['error' => 'Acción no válida']);
