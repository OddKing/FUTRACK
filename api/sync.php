<?php
/**
 * Sync Endpoint
 * Receives JSON tracking data and saves it to DB
 */

require_once 'config.php';

// Permitir peticiones OPTIONS (CORS preflight) sin API KEY
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit;
}

// Verificación de Seguridad
$user_id = get_user_id($pdo);
if (!$user_id) {
    http_response_code(401);
    echo json_encode(['error' => 'Sesión no válida o expirada']);
    exit;
}

// Obtener datos crudos
$json = file_get_contents('php://input');
$data = json_decode($json, true);

if (!$data || !isset($data['match_id']) || !isset($data['points'])) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid data format']);
    exit;
}

$match_id = $data['match_id'];
$player_id = $data['player_id'] ?? null;
$points = $data['points'];

// Validar o asignar player_id
if (!$player_id) {
    // Buscar primer jugador del usuario
    $stmt = $pdo->prepare("SELECT id FROM players WHERE user_id = ? ORDER BY id ASC LIMIT 1");
    $stmt->execute([$user_id]);
    $player = $stmt->fetch();
    if ($player) {
        $player_id = $player['id'];
    } else {
        // Crear jugador por defecto si no existe
        $stmt_player = $pdo->prepare("INSERT INTO players (user_id, name, number, team) VALUES (?, ?, ?, ?)");
        $stmt_player->execute([$user_id, 'Yo (Principal)', 10, 'Mi Equipo']);
        $player_id = $pdo->lastInsertId();
    }
} else {
    // Verificar que el player_id pertenece al usuario
    $stmt = $pdo->prepare("SELECT id FROM players WHERE id = ? AND user_id = ?");
    $stmt->execute([$player_id, $user_id]);
    if (!$stmt->fetch()) {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'Jugador no válido o no pertenece al usuario']);
        exit;
    }
}

// Verificar o crear partido para este usuario
$stmt = $pdo->prepare("SELECT id FROM matches WHERE id = ? AND user_id = ?");
$stmt->execute([$match_id, $user_id]);
if (!$stmt->fetch()) {
    // Si el ID no existe o no es del usuario, creamos uno nuevo (o usamos un ID alto)
    $stmt = $pdo->prepare("INSERT INTO matches (name, date, user_id) VALUES (?, NOW(), ?)");
    $stmt->execute(["Partido Sincronizado", $user_id]);
    $match_id = $pdo->lastInsertId();
}

if (empty($points)) {
    echo json_encode(['status' => 'success', 'message' => 'No points to sync', 'match_id' => $match_id]);
    exit;
}

try {
    $pdo->beginTransaction();
    
    $chunkSize = 500;
    $chunks = array_chunk($points, $chunkSize);
    
    foreach ($chunks as $chunk) {
        $sql = "INSERT INTO tracking_data (match_id, player_id, lat, lng, speed, timestamp) VALUES ";
        $insertQuery = [];
        $insertData = [];
        
        foreach ($chunk as $p) {
            $insertQuery[] = "(?, ?, ?, ?, ?, ?)";
            $insertData[] = $match_id;
            $insertData[] = $player_id;
            $insertData[] = $p['lat'];
            $insertData[] = $p['lng'];
            $insertData[] = $p['speed'] ?? 0;
            $insertData[] = $p['timestamp'];
        }
        
        if (!empty($insertQuery)) {
            $sql .= implode(', ', $insertQuery);
            $stmt = $pdo->prepare($sql);
            $stmt->execute($insertData);
        }
    }
    
    $pdo->commit();
    echo json_encode(['status' => 'success', 'count' => count($points)]);
} catch (Exception $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>
