/**
 * FUTRACK - Core Tracker Logic
 */

const DB_NAME = 'FUTRACK_DB';
const STORE_NAME = 'tracking_points';
let db;
let watchId = null;
let startTime = null;
let timerInterval = null;
let wakeLock = null;
let pointsBuffer = [];

// Initialize IndexedDB
const initDB = () => {
    return new Promise((resolve, reject) => {
        const request = indexedDB.open(DB_NAME, 1);
        request.onupgradeneeded = (e) => {
            const db = e.target.result;
            if (!db.objectStoreNames.contains(STORE_NAME)) {
                db.createObjectStore(STORE_NAME, { autoIncrement: true });
            }
        };
        request.onsuccess = (e) => {
            db = e.target.result;
            resolve(db);
        };
        request.onerror = (e) => reject(e);
    });
};

// State Elements
const btnStart = document.getElementById('btnStart');
const btnStop = document.getElementById('btnStop');
const btnSync = document.getElementById('btnSync');
const pointsCountEl = document.getElementById('pointsCount');
const matchTimeEl = document.getElementById('matchTime');
const coordsEl = document.getElementById('coords');
const networkStatus = document.getElementById('networkStatus');
const networkText = document.getElementById('networkText');
const wakeStatus = document.getElementById('wakeStatus');

const updateNetworkStatus = () => {
    if (navigator.onLine) {
        networkStatus.className = 'status-dot online';
        networkText.innerText = 'Online';
    } else {
        networkStatus.className = 'status-dot offline';
        networkText.innerText = 'Offline';
    }
};

window.addEventListener('online', updateNetworkStatus);
window.addEventListener('offline', updateNetworkStatus);
updateNetworkStatus();

// Wake Lock Logic
const requestWakeLock = async () => {
    try {
        if ('wakeLock' in navigator) {
            wakeLock = await navigator.wakeLock.request('screen');
            wakeStatus.style.display = 'block';
            console.log('Wake Lock is active');
            
            wakeLock.addEventListener('release', () => {
                console.log('Wake Lock was released');
                wakeStatus.style.display = 'none';
            });
        }
    } catch (err) {
        console.error(`${err.name}, ${err.message}`);
    }
};

const releaseWakeLock = () => {
    if (wakeLock !== null) {
        wakeLock.release();
        wakeLock = null;
    }
};

// Re-acquire wake lock when tab becomes visible again
document.addEventListener('visibilitychange', async () => {
    if (wakeLock !== null && document.visibilityState === 'visible') {
        await requestWakeLock();
    }
});

// Capture Logic
// Capture Logic
const startTracking = () => {
    if (!window.isSecureContext) {
        alert('⚠️ ERROR DE SEGURIDAD: El GPS requiere HTTPS para funcionar en móviles. \n\nSi estás en local, asegúrate de usar https:// en tu URL de Cloudflare.');
        return;
    }

    if (!navigator.geolocation) {
        alert('Este dispositivo no soporta GPS.');
        return;
    }

    startTime = Date.now();
    btnStart.style.display = 'none';
    btnStop.style.display = 'block';
    coordsEl.innerText = 'Buscando señal satelital...';
    
    requestWakeLock();
    timerInterval = setInterval(updateTimer, 1000);

    watchId = navigator.geolocation.watchPosition(
        (pos) => {
            const point = {
                lat: pos.coords.latitude,
                lng: pos.coords.longitude,
                speed: pos.coords.speed,
                timestamp: pos.timestamp
            };
            
            if (db) savePoint(point);
            else console.warn('DB no lista, punto no guardado');
            
            coordsEl.innerHTML = `<span style="color:var(--accent-green)">📡 SEÑAL ACTIVA</span><br>${point.lat.toFixed(6)}, ${point.lng.toFixed(6)}`;
        },
        (err) => {
            console.error('GPS Error:', err);
            let msg = 'Error GPS: ';
            if (err.code === 1) msg = '📍 Permiso de ubicación denegado';
            else if (err.code === 2) msg = '⚠️ No se pudo obtener la ubicación (señal débil)';
            else if (err.code === 3) msg = '⏳ Tiempo de espera agotado (buscando señal...)';
            coordsEl.innerText = msg;
        },
        {
            enableHighAccuracy: true,
            timeout: 15000, 
            maximumAge: 0
        }
    );
};

const stopTracking = () => {
    if (watchId) navigator.geolocation.clearWatch(watchId);
    clearInterval(timerInterval);
    btnStart.style.display = 'block';
    btnStop.style.display = 'none';
    coordsEl.innerText = 'Rastreador detenido.';
    watchId = null;
    releaseWakeLock();
};

const savePoint = (point) => {
    try {
        const tx = db.transaction(STORE_NAME, 'readwrite');
        tx.objectStore(STORE_NAME).add(point);
        tx.oncomplete = () => {
            updatePointsCount();
        };
    } catch (e) { console.error('Error guardando en BD:', e); }
};

const updatePointsCount = () => {
    if (!db) return;
    const tx = db.transaction(STORE_NAME, 'readonly');
    const request = tx.objectStore(STORE_NAME).count();
    request.onsuccess = () => {
        pointsCountEl.innerText = request.result;
    };
};

const updateTimer = () => {
    const diff = Date.now() - startTime;
    const mins = Math.floor(diff / 60000);
    const secs = Math.floor((diff % 60000) / 1000);
    matchTimeEl.innerText = `${mins.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`;
};

const syncData = async () => {
    if (!navigator.onLine) {
        alert('Sin conexión a internet');
        return;
    }
    if (!db) {
        alert('Base de datos no disponible');
        return;
    }

    const tx = db.transaction(STORE_NAME, 'readonly');
    const store = tx.objectStore(STORE_NAME);
    const points = [];
    
    store.openCursor().onsuccess = (e) => {
        const cursor = e.target.result;
        if (cursor) {
            points.push(cursor.value);
            cursor.continue();
        } else {
            sendToServer(points);
        }
    };
};

const clearDatabase = () => {
    const tx = db.transaction(STORE_NAME, 'readwrite');
    tx.objectStore(STORE_NAME).clear();
    tx.oncomplete = () => {
        updatePointsCount();
    };
};

const loadPlayers = async () => {
    const token = localStorage.getItem('fut7_token');
    const select = document.getElementById('playerSelect');
    if (!token || !select) return;

    try {
        const response = await fetch('../api/get_data.php?action=get_players', {
            headers: { 'X-API-KEY': token }
        });
        const players = await response.json();
        
        select.innerHTML = '';
        if (players.error) {
            select.innerHTML = '<option value="">Error de sesión</option>';
            return;
        }
        if (players.length === 0) {
            select.innerHTML = '<option value="">Predeterminado</option>';
            return;
        }
        
        players.forEach(p => {
            const opt = document.createElement('option');
            opt.value = p.id;
            opt.textContent = p.name + (p.number ? ` (#${p.number})` : '');
            select.appendChild(opt);
        });
    } catch (err) {
        select.innerHTML = '<option value="">Error cargando</option>';
    }
};

// Auth & Session
const login = async () => {
    const email = document.getElementById('loginEmail').value;
    const pass = document.getElementById('loginPass').value;
    const error = document.getElementById('loginError');
    
    const formData = new FormData();
    formData.append('email', email);
    formData.append('password', pass);

    try {
        const response = await fetch('../api/auth.php?action=login', { method: 'POST', body: formData });
        const result = await response.json();
        if (result.status === 'success') {
            localStorage.setItem('fut7_token', result.api_token);
            document.getElementById('authOverlay').style.display = 'none';
            loadPlayers();
        } else {
            error.innerText = result.error;
            error.style.display = 'block';
        }
    } catch (err) { alert('Error de conexión'); }
};

window.handleGoogleResponse = async (response) => {
    const formData = new FormData();
    formData.append('credential', response.credential);
    try {
        const res = await fetch('../api/auth.php?action=google_login', { method: 'POST', body: formData });
        const result = await res.json();
        if (result.status === 'success') {
            localStorage.setItem('fut7_token', result.api_token);
            document.getElementById('authOverlay').style.display = 'none';
            loadPlayers();
        } else {
            alert("Error Google: " + (result.error || "Fallo en verificación"));
        }
    } catch (err) { alert('Error de conexión con el servidor FUTRACK'); }
};

// Init
const handleConsent = () => {
    const consent = localStorage.getItem('fut7_consent');
    const overlay = document.getElementById('consentOverlay');
    if (!overlay) return;

    if (!consent) overlay.style.display = 'flex';
    
    // El botón de aceptar se vincula en el DOMContentLoaded
};

// Init & Event Binding
document.addEventListener('DOMContentLoaded', () => {
    console.log("FUTRACK Mobile Initializing...");
    
    // 1. PRIORITY: Check for session immediately
    const token = localStorage.getItem('fut7_token') || sessionStorage.getItem('fut7_token');
    
    if (token && token !== 'undefined' && token !== 'null') {
        console.log("Sesión detectada, ocultando overlay...");
        // Asegurar que el token esté en ambos sitios
        if (!localStorage.getItem('fut7_token')) localStorage.setItem('fut7_token', token);
        
        const authOverlay = document.getElementById('authOverlay');
        if (authOverlay) authOverlay.style.display = 'none';
        
        loadPlayers();
    }

    // 2. Safely bind UI elements
    const bind = (id, event, func) => {
        const el = document.getElementById(id);
        if (el) el.addEventListener(event, func);
        else console.warn(`Elemento ${id} no encontrado`);
    };

    bind('btnActionLogin', 'click', login);
    bind('btnStart', 'click', startTracking);
    bind('btnStop', 'click', stopTracking);
    bind('btnSync', 'click', syncData);
    
    const btnAccept = document.getElementById('btnAcceptConsent');
    if (btnAccept) {
        btnAccept.onclick = () => {
            localStorage.setItem('fut7_consent', 'true');
            document.getElementById('consentOverlay').style.display = 'none';
        };
    }

    handleConsent();
    
    // 3. Initialize background processes
    initDB().then(() => {
        updatePointsCount();
    }).catch(err => {
        console.error('Error con base de datos móvil:', err);
        if (coordsEl) coordsEl.innerText = 'Modo sin guardado local.';
    });
});

const clearDatabase = () => {
    if (!db) return;
    const tx = db.transaction(STORE_NAME, 'readwrite');
    tx.objectStore(STORE_NAME).clear();
    tx.oncomplete = () => {
        updatePointsCount();
    };
};

// Update sendToServer to include user token
const sendToServer = async (points) => {
    if (points.length === 0) {
        alert('No hay datos para sincronizar');
        return;
    }

    btnSync.innerText = 'Sincronizando...';
    btnSync.disabled = true;

    const playerSelect = document.getElementById('playerSelect');
    const playerId = playerSelect ? playerSelect.value : null;

    const payload = {
        match_id: 1, 
        player_id: playerId,
        points: points
    };

    try {
        const response = await fetch('../api/sync.php', {
            method: 'POST',
            body: JSON.stringify(payload),
            headers: { 
                'Content-Type': 'application/json',
                'X-API-KEY': localStorage.getItem('fut7_token')
            }
        });

        const result = await response.json();
        if (result.status === 'success') {
            alert(`¡Sincronización exitosa! Datos guardados.`);
            clearDatabase();
        } else {
            alert('Error: ' + (result.error || 'Fallo de sincronización'));
        }
    } catch (err) {
        alert('Error conectando al servidor');
    } finally {
        btnSync.innerText = 'SINCRONIZAR DATOS';
        btnSync.disabled = false;
    }
};
