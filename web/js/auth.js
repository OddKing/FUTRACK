/**
 * FUTRACK - Auth & Session Management
 */

window.toggleAuth = (type) => {
    const loginForm = document.getElementById('loginForm');
    const registerForm = document.getElementById('registerForm');
    const authError = document.getElementById('authError');
    authError.style.display = 'none';
    if (type === 'register') {
        loginForm.style.display = 'none';
        registerForm.style.display = 'block';
    } else {
        loginForm.style.display = 'block';
        registerForm.style.display = 'none';
    }
};

const handleAuthSuccess = (result) => {
    localStorage.setItem('fut7_token', result.api_token);
    localStorage.setItem('fut7_name', result.name);
    document.getElementById('loginOverlay').style.display = 'none';
    updateProfileUI();
    if (window.loadMatches) window.loadMatches();
};

const updateProfileUI = () => {
    const name = localStorage.getItem('fut7_name') || 'Usuario';
    const nameEl = document.getElementById('userNameEl');
    const initialEl = document.getElementById('userInitial');
    if (nameEl) nameEl.innerText = name;
    if (initialEl) initialEl.innerText = name.charAt(0).toUpperCase();
};

const login = async () => {
    const email = document.getElementById('loginEmail').value;
    const pass = document.getElementById('loginPass').value;
    const error = document.getElementById('authError');
    
    const formData = new FormData();
    formData.append('email', email);
    formData.append('password', pass);

    try {
        const response = await fetch('../api/auth.php?action=login', { method: 'POST', body: formData });
        const result = await response.json();
        if (result.status === 'success') handleAuthSuccess(result);
        else { error.innerText = result.error; error.style.display = 'block'; }
    } catch (err) { alert('Error de conexión'); }
};

const register = async () => {
    const name = document.getElementById('regName').value;
    const email = document.getElementById('regEmail').value;
    const pass = document.getElementById('regPass').value;
    const error = document.getElementById('authError');
    
    const formData = new FormData();
    formData.append('name', name);
    formData.append('email', email);
    formData.append('password', pass);

    try {
        const response = await fetch('../api/auth.php?action=register', { method: 'POST', body: formData });
        const result = await response.json();
        if (result.status === 'success') handleAuthSuccess(result);
        else { error.innerText = result.error; error.style.display = 'block'; }
    } catch (err) { alert('Error de conexión'); }
};

window.handleGoogleResponse = async (response) => {
    const formData = new FormData();
    formData.append('credential', response.credential);
    try {
        const res = await fetch('../api/auth.php?action=google_login', { method: 'POST', body: formData });
        const result = await res.json();
        if (result.status === 'success') {
            handleAuthSuccess(result);
        } else {
            // Mostrar error específico en el panel de error si existe, o un alert
            const authError = document.getElementById('authError');
            if (authError) {
                authError.innerText = "Error Google: " + (result.error || "Fallo desconocido");
                authError.style.display = 'block';
            } else {
                alert("Error Google: " + (result.error || "Fallo desconocido"));
            }
        }
    } catch (err) { 
        alert('Error de conexión con el servidor FUTRACK');
        console.error(err);
    }
};

window.logout = () => {
    localStorage.clear();
    location.reload();
};

window.getAuthHeader = () => ({ 'X-API-KEY': localStorage.getItem('fut7_token') });

// Bind events on load
document.addEventListener('DOMContentLoaded', () => {
    const btnLogin = document.getElementById('btnActionLogin');
    const btnReg = document.getElementById('btnActionRegister');
    if (btnLogin) btnLogin.onclick = login;
    if (btnReg) btnReg.onclick = register;

    if (localStorage.getItem('fut7_token')) {
        const overlay = document.getElementById('loginOverlay');
        if (overlay) overlay.style.display = 'none';
        updateProfileUI();
    }
});
