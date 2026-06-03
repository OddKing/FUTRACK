<?php
/**
 * FUTRACK - Auto Deploy Webhook
 * GitHub envía un POST aquí cada vez que haces push.
 * Este script ejecuta git pull automáticamente.
 * 
 * URL del webhook: https://tu-dominio.com/api/webhook_deploy.php
 */

// Clave secreta para verificar que la petición viene de GitHub
$secret = 'futrack_deploy_secret_2026';

// Verificar método
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Solo POST']);
    exit;
}

// Verificar firma de GitHub
$payload = file_get_contents('php://input');
$signature = $_SERVER['HTTP_X_HUB_SIGNATURE_256'] ?? '';

if (!empty($secret)) {
    $expected = 'sha256=' . hash_hmac('sha256', $payload, $secret);
    if (!hash_equals($expected, $signature)) {
        http_response_code(403);
        echo json_encode(['error' => 'Firma inválida']);
        exit;
    }
}

// Ejecutar git pull en el directorio del proyecto
$projectDir = '/var/www/html';
$output = [];
$returnCode = 0;

exec("cd {$projectDir} && git pull origin main 2>&1", $output, $returnCode);

$log = implode("\n", $output);

// Registrar en un log
$logEntry = date('Y-m-d H:i:s') . " | Code: {$returnCode} | {$log}\n";
file_put_contents('/tmp/futrack_deploy.log', $logEntry, FILE_APPEND);

// Responder
if ($returnCode === 0) {
    echo json_encode([
        'status' => 'success',
        'message' => 'Deploy completado',
        'output' => $log
    ]);
} else {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Error en git pull',
        'output' => $log
    ]);
}
?>
