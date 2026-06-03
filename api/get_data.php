<?php
/**
 * Data Retrieval API
 * Fetch matches or specific tracking data
 */

require_once 'config.php';

// Permitir peticiones OPTIONS (CORS preflight) sin API KEY
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit;
}

$action = $_GET['action'] ?? 'list_matches';

// Verificación de Usuario
$user_id = get_user_id($pdo);
if (!$user_id && $action !== 'login') {
    http_response_code(401);
    echo json_encode(['error' => 'Sesión no válida o expirada']);
    exit;
}

if ($action == 'delete_match') {
    $match_id = $_GET['match_id'] ?? null;
    if (!$match_id) {
        http_response_code(400);
        echo json_encode(['error' => 'ID de partido faltante']);
        exit;
    }
    
    $pdo->beginTransaction();
    try {
        // Asegurar que el partido pertenece al usuario
        $stmt1 = $pdo->prepare("DELETE FROM tracking_data WHERE match_id = ? AND match_id IN (SELECT id FROM matches WHERE user_id = ?)");
        $stmt1->execute([$match_id, $user_id]);
        $stmt2 = $pdo->prepare("DELETE FROM matches WHERE id = ? AND user_id = ?");
        $stmt2->execute([$match_id, $user_id]);
        $pdo->commit();
        echo json_encode(['status' => 'success', 'message' => 'Partido eliminado correctamente']);
    } catch (Exception $e) {
        $pdo->rollBack();
        http_response_code(500);
        echo json_encode(['error' => 'Error al eliminar: ' . $e->getMessage()]);
    }
    exit;
}

if ($action == 'list_matches') {
    $stmt = $pdo->prepare("SELECT * FROM matches WHERE user_id = ? ORDER BY date DESC");
    $stmt->execute([$user_id]);
    echo json_encode($stmt->fetchAll());
    exit;
}

if ($action == 'get_players') {
    $stmt = $pdo->prepare("SELECT * FROM players WHERE user_id = ?");
    $stmt->execute([$user_id]);
    echo json_encode($stmt->fetchAll());
    exit;
}

if ($action == 'get_tracking') {
    $match_id = $_GET['match_id'] ?? null;
    $player_id = $_GET['player_id'] ?? null;
    
    if (!$match_id) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing match_id']);
        exit;
    }
    
    // Verificar que el partido pertenece al usuario
    $stmt = $pdo->prepare("SELECT id FROM matches WHERE id = ? AND user_id = ?");
    $stmt->execute([$match_id, $user_id]);
    if (!$stmt->fetch()) {
        http_response_code(403);
        echo json_encode(['error' => 'Acceso denegado']);
        exit;
    }

    $query = "SELECT lat, lng, speed, timestamp FROM tracking_data WHERE match_id = ?";
    $params = [$match_id];
    
    if ($player_id) {
        $query .= " AND player_id = ?";
        $params[] = $player_id;
    }
    
    $query .= " ORDER BY timestamp ASC";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    echo json_encode($stmt->fetchAll());
    exit;
}

http_response_code(400);
echo json_encode(['error' => 'Invalid action']);
?>
