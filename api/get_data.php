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

if ($action == 'get_user_info') {
    $stmt = $pdo->prepare("SELECT id, name, email, subscription_tier FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    echo json_encode($stmt->fetch(PDO::FETCH_ASSOC));
    exit;
}

if ($action == 'upgrade_subscription') {
    $tier = $_POST['tier'] ?? 'pro';
    if (!in_array($tier, ['pro', 'elite'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Nivel no válido']);
        exit;
    }
    
    $stmt = $pdo->prepare("UPDATE users SET subscription_tier = ? WHERE id = ?");
    $stmt->execute([$tier, $user_id]);
    echo json_encode(['status' => 'success', 'tier' => $tier]);
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
        // Asegurar que el partido pertenece al usuario (solo el creador puede eliminarlo)
        $stmt2 = $pdo->prepare("DELETE FROM matches WHERE id = ? AND user_id = ?");
        $stmt2->execute([$match_id, $user_id]);
        if ($stmt2->rowCount() > 0) {
            $pdo->commit();
            echo json_encode(['status' => 'success', 'message' => 'Partido eliminado correctamente']);
        } else {
            $pdo->rollBack();
            http_response_code(403);
            echo json_encode(['error' => 'No tienes permiso para eliminar este partido']);
        }
    } catch (Exception $e) {
        $pdo->rollBack();
        http_response_code(500);
        echo json_encode(['error' => 'Error al eliminar: ' . $e->getMessage()]);
    }
    exit;
}

if ($action == 'create_match') {
    $name = $_POST['name'] ?? 'Partido ' . date('d/m/Y H:i');
    $join_code = 'FUT-' . substr(str_shuffle("0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ"), 0, 4);
    
    // Validar limites según suscripción
    $stmt_user = $pdo->prepare("SELECT subscription_tier FROM users WHERE id = ?");
    $stmt_user->execute([$user_id]);
    $tier = $stmt_user->fetchColumn() ?: 'free';
    
    $stmt_recent = $pdo->prepare("SELECT COUNT(*) FROM match_participants WHERE user_id = ? AND joined_at >= DATE_SUB(NOW(), INTERVAL ? HOUR)");
    
    if ($tier === 'free') {
        $stmt_recent->execute([$user_id, 7 * 24]);
        if ($stmt_recent->fetchColumn() > 0) {
            http_response_code(403);
            echo json_encode(['error' => 'Cuenta Free limitada a 1 partido por semana.']);
            exit;
        }
    } elseif ($tier === 'pro') {
        $stmt_recent->execute([$user_id, 24]);
        if ($stmt_recent->fetchColumn() > 0) {
            http_response_code(403);
            echo json_encode(['error' => 'Cuenta Pro limitada a 1 partido diario.']);
            exit;
        }
    }
    
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("INSERT INTO matches (name, date, user_id, join_code) VALUES (?, NOW(), ?, ?)");
        $stmt->execute([$name, $user_id, $join_code]);
        $match_id = $pdo->lastInsertId();
        
        $stmt_part = $pdo->prepare("INSERT INTO match_participants (match_id, user_id) VALUES (?, ?)");
        $stmt_part->execute([$match_id, $user_id]);
        
        $pdo->commit();
        echo json_encode(['status' => 'success', 'match_id' => (int)$match_id, 'name' => $name, 'join_code' => $join_code]);
    } catch (Exception $e) {
        $pdo->rollBack();
        http_response_code(500);
        echo json_encode(['error' => 'Error al crear partido: ' . $e->getMessage()]);
    }
    exit;
}

if ($action == 'list_matches') {
    // Listar todos los partidos del usuario (los viejos) y en los que participa
    $stmt = $pdo->prepare("
        SELECT DISTINCT m.* 
        FROM matches m 
        LEFT JOIN match_participants mp ON m.id = mp.match_id 
        WHERE m.user_id = ? OR mp.user_id = ? 
        ORDER BY m.date DESC
    ");
    $stmt->execute([$user_id, $user_id]);
    echo json_encode($stmt->fetchAll());
    exit;
}

if ($action == 'join_match') {
    $join_code = $_POST['join_code'] ?? null;
    if (!$join_code) {
        http_response_code(400);
        echo json_encode(['error' => 'Código de partido requerido']);
        exit;
    }
    
    $stmt_user = $pdo->prepare("SELECT subscription_tier FROM users WHERE id = ?");
    $stmt_user->execute([$user_id]);
    $tier = $stmt_user->fetchColumn() ?: 'free';
    
    if ($tier === 'free') {
        http_response_code(403);
        echo json_encode(['error' => 'Los usuarios Free no pueden unirse a partidos. Sube a Pro o Elite.']);
        exit;
    }
    
    if ($tier === 'pro') {
        $stmt_recent = $pdo->prepare("SELECT COUNT(*) FROM match_participants WHERE user_id = ? AND joined_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)");
        $stmt_recent->execute([$user_id]);
        if ($stmt_recent->fetchColumn() > 0) {
            http_response_code(403);
            echo json_encode(['error' => 'Cuenta Pro limitada a 1 partido diario.']);
            exit;
        }
    }
    
    $stmt = $pdo->prepare("SELECT * FROM matches WHERE join_code = ? AND is_open = 1");
    $stmt->execute([$join_code]);
    $match = $stmt->fetch();
    
    if (!$match) {
        http_response_code(404);
        echo json_encode(['error' => 'Partido no encontrado o ya está cerrado']);
        exit;
    }
    
    // Check if already joined
    $stmt_check = $pdo->prepare("SELECT * FROM match_participants WHERE match_id = ? AND user_id = ?");
    $stmt_check->execute([$match['id'], $user_id]);
    if ($stmt_check->fetch()) {
        echo json_encode(['status' => 'success', 'match_id' => $match['id'], 'name' => $match['name'], 'message' => 'Ya estabas en este partido']);
        exit;
    }
    
    try {
        $stmt_insert = $pdo->prepare("INSERT INTO match_participants (match_id, user_id) VALUES (?, ?)");
        $stmt_insert->execute([$match['id'], $user_id]);
        echo json_encode(['status' => 'success', 'match_id' => $match['id'], 'name' => $match['name']]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Error al unirse al partido']);
    }
    exit;
}

if ($action == 'get_match_results') {
    $match_id = $_GET['match_id'] ?? null;
    if (!$match_id) {
        http_response_code(400);
        echo json_encode(['error' => 'ID de partido faltante']);
        exit;
    }
    
    // Verificar participación
    $stmt_check = $pdo->prepare("SELECT * FROM match_participants WHERE match_id = ? AND user_id = ?");
    $stmt_check->execute([$match_id, $user_id]);
    if (!$stmt_check->fetch()) {
        http_response_code(403);
        echo json_encode(['error' => 'No tienes acceso a los resultados de este partido']);
        exit;
    }
    
    // Calcular estadísticas: aproximación de distancia como suma de velocidades (asumiendo 1 punto/s) y velocidad máxima
    $stmt_stats = $pdo->prepare("
        SELECT p.id, p.name, p.user_id, u.name as user_name,
               MAX(t.speed) as max_speed,
               SUM(t.speed) as approx_distance_m,
               COUNT(t.id) as total_points
        FROM players p 
        JOIN tracking_data t ON p.id = t.player_id 
        JOIN users u ON p.user_id = u.id
        WHERE t.match_id = ? 
        GROUP BY p.id
        ORDER BY approx_distance_m DESC
    ");
    $stmt_stats->execute([$match_id]);
    $results = $stmt_stats->fetchAll();
    
    echo json_encode(['status' => 'success', 'results' => $results]);
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
    
    // Verificar que el partido es de un participante
    $stmt = $pdo->prepare("SELECT match_id FROM match_participants WHERE match_id = ? AND user_id = ?");
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
