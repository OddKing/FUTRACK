/**
 * FUTRACK - Main Dashboard Controller
 */

window.loadMatches = async () => {
    try {
        const response = await fetch('../api/get_data.php?action=list_matches', { headers: window.getAuthHeader() });
        if (response.status === 401) return window.logout();
        const matches = await response.json();
        const list = document.getElementById('matchList');
        if (!list) return;
        list.innerHTML = '';

        matches.forEach(m => {
            const li = document.createElement('li');
            li.innerHTML = `
                <div style="display:flex; justify-content:space-between; align-items:center;">
                    <div>
                        <div style="font-weight:600">${m.name}</div>
                        <div style="font-size:0.8rem; color:#64748b">${m.date}</div>
                    </div>
                    <button class="btnDelete" data-id="${m.id}" style="background:none; border:none; color:#ff5252; cursor:pointer; padding:5px;">×</button>
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
        const response = await fetch(`../api/get_data.php?action=get_tracking&match_id=${match.id}`, { headers: window.getAuthHeader() });
        const data = await response.json();
        
        if (window.setData && window.processStats && window.renderData && window.renderChart) {
            window.setData(data);
            window.processStats();
            window.renderData();
            window.renderChart();
        }
    } catch (err) { console.error('Error al cargar datos del partido:', err); }
};

// Main Init
document.addEventListener('DOMContentLoaded', () => {
    if (typeof initMap === 'function') initMap();
    
    const btnHeat = document.getElementById('btnHeatmap');
    const btnPath = document.getElementById('btnPath');
    if (btnHeat) btnHeat.onclick = () => window.toggleView('heatmap');
    if (btnPath) btnPath.onclick = () => window.toggleView('path');

    if (localStorage.getItem('fut7_token')) {
        window.loadMatches();
    }
});
