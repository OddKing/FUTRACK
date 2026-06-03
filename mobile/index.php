<?php
/**
 * FUTRACK - Mobile Tracker (Support for Google Redirect)
 */

if (isset($_POST['credential'])) {
    $credential = $_POST['credential'];
    ?>
    <!DOCTYPE html>
    <html>
    <head><title>Procesando...</title></head>
    <body style="background:#050505; color:white; display:flex; align-items:center; justify-content:center; height:100vh; font-family:sans-serif;">
        <div id="statusContainer" style="text-align:center;">
            <div id="loader" style="border:4px solid #39FF14; border-top:4px solid transparent; border-radius:50%; width:40px; height:40px; animation:spin 1s linear infinite; margin:0 auto 20px auto;"></div>
            <p id="statusText">Verificando cuenta con Google...</p>
            <button id="btnManualAccess" style="display:none; margin-top:20px; background:#39FF14; color:black; border:none; padding:12px 25px; border-radius:30px; font-weight:700; cursor:pointer; font-family:inherit; text-transform:uppercase;">Acceder al Dashboard</button>
        </div>
        <script>
            const formData = new FormData();
            formData.append('credential', <?php echo json_encode($credential); ?>);
            
            const showManualBtn = () => {
                document.getElementById('loader').style.display = 'none';
                document.getElementById('statusText').innerText = "¡Verificación completa!";
                document.getElementById('btnManualAccess').style.display = 'inline-block';
            };

            document.getElementById('btnManualAccess').onclick = () => { window.location.href = 'index.php'; };

            fetch('../api/auth.php?action=google_login', { method: 'POST', body: formData })
                .then(r => r.json())
                .then(res => {
                    if (res.status === 'success') {
                        // Guardar en DOS sitios para máxima persistencia
                        localStorage.setItem('fut7_token', res.api_token);
                        sessionStorage.setItem('fut7_token', res.api_token);
                        localStorage.setItem('fut7_name', res.name);
                        
                        document.getElementById('statusText').innerText = "Cargando tu aplicación...";
                        
                        // Intentar redirección automática tras un pequeño delay para que el disco escriba
                        setTimeout(() => { window.location.href = 'index.php'; }, 500);
                        
                        // Fallback: mostrar botón si la redirección falla
                        setTimeout(showManualBtn, 3000);
                    } else {
                        alert('Error: ' + (res.error || 'No se pudo validar la sesión'));
                        window.location.href = 'index.php';
                    }
                })
                .catch(e => {
                    alert('Error de red: Por favor, asegúrate de estar conectado.');
                    window.location.href = 'index.php';
                });
        </script>
        <style>@keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }</style>
    </body>
    </html>
    <?php
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>FUTRACK - GPS</title>
    <link rel="manifest" href="manifest.json">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="theme-color" content="#050505">
    <link rel="icon" type="image/png" href="../assets/icon.png">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-color: #050505;
            --accent-green: #39FF14;
            --accent-red: #ff5252;
            --card-bg: rgba(255, 255, 255, 0.03);
            --text-color: #ffffff;
            --glow-green: 0 0 15px rgba(57, 255, 20, 0.4);
        }

        body {
            margin: 0;
            padding: 0;
            font-family: 'Outfit', sans-serif;
            background-color: var(--bg-color);
            color: var(--text-color);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            height: 100vh;
            overflow: hidden;
            touch-action: manipulation;
            background: radial-gradient(circle at center, #1a1a1a 0%, #050505 100%);
        }

        .container {
            width: 90%;
            max-width: 400px;
            text-align: center;
        }

        .logo-container {
            margin-bottom: 2rem;
        }

        .logo-img {
            max-width: 180px;
            filter: drop-shadow(var(--glow-green));
        }

        h1 {
            font-weight: 600;
            margin: 0.5rem 0 2rem 0;
            color: var(--accent-green);
            letter-spacing: 2px;
            text-shadow: var(--glow-green);
        }

        .stats-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: var(--card-bg);
            border-radius: 20px;
            padding: 1.5rem;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.05);
            transition: transform 0.3s ease;
        }

        .stat-value {
            font-size: 1.8rem;
            font-weight: 600;
            display: block;
            color: #fff;
        }

        .stat-label {
            font-size: 0.7rem;
            color: #666;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .controls {
            display: flex;
            flex-direction: column;
            gap: 1.2rem;
        }

        button {
            border: none;
            border-radius: 12px;
            padding: 1.2rem;
            font-size: 1.1rem;
            font-weight: 600;
            font-family: 'Outfit', sans-serif;
            cursor: pointer;
            transition: all 0.2s;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        button:active {
            transform: scale(0.96);
        }

        #btnStart {
            background-color: var(--accent-green);
            color: #000;
            box-shadow: var(--glow-green);
        }

        #btnStop {
            background-color: var(--accent-red);
            color: #fff;
            display: none;
            box-shadow: 0 0 15px rgba(255, 82, 82, 0.3);
        }

        #btnSync {
            background: transparent;
            border: 1px solid rgba(57, 255, 20, 0.3);
            color: var(--accent-green);
            margin-top: 1rem;
        }

        .status-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            display: inline-block;
            margin-right: 6px;
        }

        .online {
            background-color: var(--accent-green);
            box-shadow: 0 0 8px var(--accent-green);
        }

        .offline {
            background-color: #ffc107;
        }

        .gps-info {
            font-size: 0.75rem;
            color: #444;
            margin-top: 2rem;
        }

        #coords {
            color: #666;
            font-family: monospace;
        }

        .wake-badge {
            display: none;
            background: rgba(57, 255, 20, 0.1);
            color: var(--accent-green);
            padding: 6px 16px;
            border-radius: 30px;
            font-size: 0.75rem;
            margin-bottom: 1rem;
            border: 1px solid rgba(57, 255, 20, 0.2);
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0% {
                opacity: 0.5;
                transform: scale(0.98);
            }

            50% {
                opacity: 1;
                transform: scale(1);
            }

            100% {
                opacity: 0.5;
                transform: scale(0.98);
            }
        }

        .warning-text {
            color: #555;
            font-size: 0.7rem;
            margin-top: 1rem;
            font-weight: 300;
        }
    </style>
    <script src="https://accounts.google.com/gsi/client" async defer></script>
</head>

<body>
    <!-- Login Multi-usuario (Email + Google) -->
    <div id="authOverlay" style="position:fixed; top:0; left:0; width:100%; height:100%; background:var(--bg-color); z-index:11000; display:flex; flex-direction:column; align-items:center; justify-content:center; padding:20px;">
        <img src="../assets/logo-mobile.png" style="max-width:180px; margin-bottom:40px;">
        
        <div id="loginForm" style="width:100%; text-align:center;">
            <h2 style="margin-bottom:30px;">Inicia Sesión</h2>
            
            <div id="g_id_onload"
                 data-client_id="82080578361-f73c7afmgpkf4t8aiak16f3vb9ba1jvj.apps.googleusercontent.com"
                 data-context="signin"
                 data-ux_mode="redirect"
                 data-login_uri="https://relying-hughes-nutten-summary.trycloudflare.com/mobile/index.php"
                 data-auto_prompt="false">
            </div>
            <div class="g_id_signin" data-type="standard" data-shape="rectangular" data-theme="outline" data-text="signin_with" data-size="large" data-logo_alignment="left" style="margin-bottom:20px; display:inline-block;"></div>

            <div style="margin:20px 0; color:#475569; font-size:0.8rem;">o con tu correo</div>
            
            <input type="email" id="loginEmail" placeholder="Email" style="width:100%; padding:15px; border-radius:12px; border:1px solid rgba(255,255,255,0.05); background:var(--card-bg); color:white; margin-bottom:15px; outline:none;">
            <input type="password" id="loginPass" placeholder="Contraseña" style="width:100%; padding:15px; border-radius:12px; border:1px solid rgba(255,255,255,0.05); background:var(--card-bg); color:white; margin-bottom:25px; outline:none;">
            
            <button id="btnActionLogin" style="width:100%; background:var(--accent-green); color:black; padding:15px; border-radius:12px; font-weight:700; border:none; margin-bottom:20px;">ENTRAR</button>
            <p id="loginError" style="color:var(--accent-red); font-size:0.8rem; display:none;">Error al iniciar sesión</p>
            
            <p style="color:#64748b; font-size:0.9rem; margin-top:20px;">¿No tienes cuenta? Regístrate en el Dashboard Web.</p>
        </div>
    </div>

    <!-- Consentimentio Legal -->
    <div id="consentOverlay" style="position:fixed; top:0; left:0; width:100%; height:100%; background:var(--bg-color); z-index:10000; display:none; flex-direction:column; align-items:center; justify-content:center; padding:20px; text-align:center;">
        <img src="../assets/icon.png" style="max-width:80px; margin-bottom:20px;">
        <h2 style="color:var(--accent-green); margin-bottom:15px;">Privacidad y Datos</h2>
        <div style="background:var(--card-bg); padding:20px; border-radius:15px; border:1px solid rgba(255,255,255,0.05); font-size:0.9rem; line-height:1.5; margin-bottom:25px; max-height:300px; overflow-y:auto;">
            <p>Para funcionar, <b>FUTRACK</b> necesita recolectar tu ubicación GPS y velocidad.</p>
            <p>De acuerdo a la <b>Ley 19.628 (Chile)</b>, tus datos son usados solo para analítica deportiva.</p>
        </div>
        <button id="btnAcceptConsent" style="width:100%; background:var(--accent-green); color:black; padding:15px; border-radius:12px; font-weight:600; border:none;">ACEPTAR Y CONTINUAR</button>
    </div>
    <div class="container">
        <div class="logo-container">
            <img src="../assets/logo-mobile.png" alt="FUTRACK" class="logo-img" onerror="this.style.display='none'">
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <span id="pointsCount" class="stat-value">0</span>
                <span class="stat-label">Puntos</span>
            </div>
            <div class="stat-card">
                <span id="matchTime" class="stat-value">00:00</span>
                <span class="stat-label">Tiempo</span>
            </div>
        </div>

        <div class="controls">
            <div id="wakeStatus" class="wake-badge">⚡ PANTALLA ACTIVA</div>
            
            <select id="playerSelect" style="background:var(--card-bg); color:white; border:1px solid rgba(255,255,255,0.1); padding:15px; border-radius:12px; font-family:'Outfit', sans-serif; font-size:1rem; margin-bottom: 5px; width: 100%; outline: none; text-align: center;">
                <option value="">Cargando jugadores...</option>
            </select>

            <button id="btnStart">INICIAR PARTIDO</button>
            <button id="btnStop">DETENER PARTIDO</button>
            <button id="btnSync">SINCRONIZAR DATOS</button>
            <div class="warning-text">⚠️ Mantén la pantalla encendida para el GPS</div>
        </div>

        <div class="gps-info">
            <span id="networkStatus" class="status-dot"></span> <span id="networkText">Offline</span>
            <br><br>
            <span id="coords">Esperando señal GPS...</span>
        </div>
    </div>

    <script src="app.js"></script>
    <script>
        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.register('sw.js');
        }
    </script>
</body>

</html>
