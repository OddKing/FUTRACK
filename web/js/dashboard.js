/**
 * FUTRACK - Main Dashboard Controller
 */

let currentUserTier = 'free';

// ─── Tab Switching ───────────────────────────────
window.switchTab = (tab) => {
    document.getElementById('tabStats').classList.toggle('active', tab === 'stats');
    document.getElementById('tabPlans').classList.toggle('active', tab === 'plans');
    document.getElementById('panelStats').style.display = tab === 'stats' ? 'block' : 'none';
    document.getElementById('panelPlans').style.display = tab === 'plans' ? 'block' : 'none';
    document.getElementById('matchListSection').style.display = tab === 'stats' ? 'block' : 'none';
    if (tab === 'plans') renderPlansUI();
};

// ─── Load User Info & Tier ───────────────────────
const loadUserInfo = async () => {
    try {
        const res = await fetch('../api/get_data.php?action=get_user_info', { headers: window.getAuthHeader() });
        if (res.status === 401) return window.logout();
        const info = await res.json();
        currentUserTier = info.subscription_tier || 'free';
        const tierMap = { free: 'FREE', pro: '⭐ PRO', elite: '🏆 ELITE' };
        const tierEl = document.getElementById('tierValue');
        if (tierEl) {
            tierEl.textContent = tierMap[currentUserTier] || 'FREE';
            tierEl.style.color = currentUserTier === 'elite' ? '#ffb300' : currentUserTier === 'pro' ? '#39FF14' : '#64748b';
        }
    } catch (e) { console.error('Error al cargar info de usuario', e); }
};

// ─── Match List ──────────────────────────────────
window.loadMatches = async () => {
    try {
        const response = await fetch('../api/get_data.php?action=list_matches', { headers: window.getAuthHeader() });
        if (response.status === 401) return window.logout();
        const matches = await response.json();
        const list = document.getElementById('matchList');
        if (!list) return;
        list.innerHTML = '';

        if (!matches || matches.length === 0) {
            list.innerHTML = '<li style="color:#64748b; font-size:0.85rem;">No tienes partidos aún.</li>';
            return;
        }

        matches.forEach(m => {
            const li = document.createElement('li');
            const code = m.join_code ? `<span style="font-size:0.7rem; color:#39FF14; opacity:0.7;">${m.join_code}</span>` : '';
            li.innerHTML = `
                <div style="display:flex; justify-content:space-between; align-items:center;">
                    <div>
                        <div style="font-weight:600; font-size:0.9rem;">${m.name}</div>
                        <div style="font-size:0.75rem; color:#64748b; margin-top:3px;">${new Date(m.date).toLocaleDateString('es-CL')} ${code}</div>
                    </div>
                    <button class="btnDelete" data-id="${m.id}" style="background:none; border:none; color:#ff5252; cursor:pointer; padding:5px; font-size:1rem;">×</button>
                </div>
            `;
            li.onclick = (e) => {
                if (e.target.classList.contains('btnDelete')) deleteMatch(e.target.dataset.id);
                else selectMatch(m, li);
            };
            list.appendChild(li);
        });
    } catch (err) { console.error('Error al cargar partidos:', err); }
};

const deleteMatch = async (id) => {
    if (!confirm('¿Eliminar este partido y todos sus datos permanentemente?')) return;
    try {
        const response = await fetch(`../api/get_data.php?action=delete_match&match_id=${id}`, { headers: window.getAuthHeader() });
        const result = await response.json();
        if (result.status === 'success') window.loadMatches();
        else alert(result.error);
    } catch (err) { alert('Error al eliminar'); }
};

const selectMatch = async (match, liElement) => {
    document.getElementById('matchTitle').innerText = match.name;
    document.querySelectorAll('#matchList li').forEach(li => li.classList.remove('active'));
    if (liElement) liElement.classList.add('active');

    try {
        // Load tracking data (individual)
        const response = await fetch(`../api/get_data.php?action=get_tracking&match_id=${match.id}`, { headers: window.getAuthHeader() });
        const data = await response.json();

        if (window.setData && window.processStats && window.renderData && window.renderChart) {
            window.setData(data);
            window.processStats();
            window.renderData();
            window.renderChart();
        }

        // Load comparison podium
        loadMatchResults(match.id, match.name);
    } catch (err) { console.error('Error al cargar datos del partido:', err); }
};

// ─── Podium / Comparison ────────────────────────
const loadMatchResults = async (matchId, matchName) => {
    const card = document.getElementById('podiumCard');
    const list = document.getElementById('podiumList');
    if (!card || !list) return;

    try {
        const res = await fetch(`../api/get_data.php?action=get_match_results&match_id=${matchId}`, { headers: window.getAuthHeader() });
        const data = await res.json();

        if (!data.results || data.results.length <= 1) {
            card.style.display = 'none';
            return;
        }

        card.style.display = 'block';
        document.getElementById('podiumMatchName').textContent = matchName;
        list.innerHTML = '';

        const medals = ['🥇', '🥈', '🥉'];
        data.results.forEach((p, i) => {
            const km = ((p.approx_distance_m || 0) / 1000).toFixed(2);
            const kmh = ((p.max_speed || 0) * 3.6).toFixed(1);
            const row = document.createElement('div');
            row.className = 'podium-row';
            row.innerHTML = `
                <div class="podium-rank">${medals[i] || `${i + 1}º`}</div>
                <div class="podium-info">
                    <div class="podium-name">${p.name}</div>
                    <div class="podium-sub">@${p.user_name}</div>
                </div>
                <div class="podium-stat">
                    <div class="podium-km">${km} km</div>
                    <div class="podium-speed">⚡ ${kmh} km/h</div>
                </div>
            `;
            list.appendChild(row);
        });
    } catch (e) {
        card.style.display = 'none';
    }
};

// ─── Plans UI ────────────────────────────────────
const renderPlansUI = () => {
    const tier = currentUserTier;

    // Show/hide current badges and buttons
    ['Free', 'Pro', 'Elite'].forEach(t => {
        const badge = document.getElementById(`badge${t}`);
        const btn = document.getElementById(`btnUpgrade${t}`);
        const isCurrent = tier === t.toLowerCase();
        if (badge) badge.style.display = isCurrent ? 'block' : 'none';
        if (btn) {
            btn.style.display = isCurrent ? 'none' : 'block';
            // Disable downgrade
            if (tier === 'elite' && t === 'Pro') btn.style.display = 'none';
        }
    });
};

window.upgradePlan = async (newTier) => {
    if (!confirm(`¿Confirmas la suscripción al plan ${newTier.toUpperCase()}?`)) return;
    try {
        const formData = new FormData();
        formData.append('tier', newTier);
        const res = await fetch('../api/get_data.php?action=upgrade_subscription', {
            method: 'POST',
            headers: window.getAuthHeader(),
            body: formData
        });
        const result = await res.json();
        if (result.status === 'success') {
            currentUserTier = newTier;
            await loadUserInfo();
            renderPlansUI();
            alert(`¡Suscripción actualizada a ${newTier.toUpperCase()} con éxito!`);
        } else {
            alert(result.error || 'Error al actualizar la suscripción');
        }
    } catch (e) {
        alert('Error de conexión');
    }
};

// ─── Init ─────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
    if (typeof initMap === 'function') initMap();

    const btnHeat = document.getElementById('btnHeatmap');
    const btnPath = document.getElementById('btnPath');
    if (btnHeat) btnHeat.onclick = () => window.toggleView('heatmap');
    if (btnPath) btnPath.onclick = () => window.toggleView('path');

    if (localStorage.getItem('fut7_token')) {
        loadUserInfo();
        window.loadMatches();
    }
});
