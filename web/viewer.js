/**
 * Futbol 7 Tracker - Web Viewer Logic
 */

let map;
let heatmapLayer;
let pathLayer;
let speedChart;
let currentPoints = [];

// Haversine formula to calculate distance in meters
const getDistance = (lat1, lon1, lat2, lon2) => {
    const R = 6371e3;
    const p1 = lat1 * Math.PI / 180;
    const p2 = lat2 * Math.PI / 180;
    const dp = (lat2 - lat1) * Math.PI / 180;
    const dl = (lon2 - lon1) * Math.PI / 180;
    const a = Math.sin(dp / 2) * Math.sin(dp / 2) +
        Math.cos(p1) * Math.cos(p2) *
        Math.sin(dl / 2) * Math.sin(dl / 2);
    const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
    return R * c;
};

const heatmapConfig = {
    radius: 0.0001,
    maxOpacity: .8,
    scaleRadius: true,
    useLocalExtrema: true,
    latField: 'lat',
    lngField: 'lng',
    valueField: 'count'
};

const initMap = () => {
    map = L.map('map').setView([0, 0], 2);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap contributors',
        className: 'map-tiles'
    }).addTo(map);

    heatmapLayer = new HeatmapOverlay(heatmapConfig);
    pathLayer = L.polyline([], { color: '#39FF14', weight: 4 });
};

const loadMatches = async () => {
    try {
        const response = await fetch('../api/get_data.php?action=list_matches');
        const matches = await response.json();
        const list = document.getElementById('matchList');
        list.innerHTML = '';

        matches.forEach(m => {
            const li = document.createElement('li');
            li.innerHTML = `
                <div style="font-weight:600">${m.name}</div>
                <div style="font-size:0.8rem; color:#94a3b8">${m.date}</div>
            `;
            li.onclick = () => selectMatch(m, li);
            list.appendChild(li);
        });
    } catch (err) {
        console.error('Error loading matches:', err);
    }
};

const selectMatch = async (match, liElement) => {
    document.getElementById('matchTitle').innerText = match.name;
    document.querySelectorAll('#matchList li').forEach(li => li.classList.remove('active'));
    if (liElement) liElement.classList.add('active');
    
    try {
        const response = await fetch(`../api/get_data.php?action=get_tracking&match_id=${match.id}`);
        const data = await response.json();
        currentPoints = data.map(p => ({
            lat: parseFloat(p.lat),
            lng: parseFloat(p.lng),
            speed: parseFloat(p.speed || 0),
            timestamp: parseInt(p.timestamp),
            count: 1
        }));

        processStats();
        renderData();
        renderChart();
    } catch (err) {
        console.error('Error loading tracking data:', err);
    }
};

const processStats = () => {
    let totalDist = 0;
    let maxS = 0;

    for (let i = 0; i < currentPoints.length; i++) {
        const p = currentPoints[i];
        if (p.speed > maxS) maxS = p.speed;

        if (i > 0) {
            const prev = currentPoints[i - 1];
            const dist = getDistance(prev.lat, prev.lng, p.lat, p.lng);
            // Ignorar saltos irreales de GPS (> 50m en 2 seg)
            if (dist < 50) totalDist += dist;
        }
    }

    // Update DOM
    document.getElementById('totalPoints').innerText = currentPoints.length;
    document.getElementById('maxSpeed').innerText = (maxS * 3.6).toFixed(1) + ' km/h';
    
    if (totalDist > 1000) {
        document.getElementById('totalDistance').innerText = (totalDist / 1000).toFixed(2) + ' km';
    } else {
        document.getElementById('totalDistance').innerText = Math.round(totalDist) + ' m';
    }
};

const renderChart = () => {
    const ctx = document.getElementById('speedChart').getContext('2d');
    
    if (speedChart) speedChart.destroy();

    const labels = currentPoints.map((p, i) => i);
    const speeds = currentPoints.map(p => (p.speed * 3.6).toFixed(1));

    speedChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [{
                label: 'Velocidad (km/h)',
                data: speeds,
                borderColor: '#39FF14',
                backgroundColor: 'rgba(57, 255, 20, 0.1)',
                fill: true,
                tension: 0.4,
                pointRadius: 0
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: { color: 'rgba(255,255,255,0.05)' },
                    ticks: { color: '#94a3b8' }
                },
                x: {
                    display: false
                }
            }
        }
    });
};

const renderData = () => {
    if (currentPoints.length === 0) return;

    const bounds = L.latLngBounds(currentPoints.map(p => [p.lat, p.lng]));
    map.fitBounds(bounds);

    map.removeLayer(heatmapLayer);
    heatmapLayer.setData({ max: 5, data: currentPoints });
    heatmapLayer.addTo(map);

    pathLayer.setLatLngs(currentPoints.map(p => [p.lat, p.lng]));
    pathLayer.addTo(map);

    toggleView('heatmap');
};

const toggleView = (type) => {
    const btnHeat = document.getElementById('btnHeatmap');
    const btnPath = document.getElementById('btnPath');

    if (type === 'heatmap') {
        btnHeat.classList.add('active');
        btnPath.classList.remove('active');
        if (!map.hasLayer(heatmapLayer)) heatmapLayer.addTo(map);
        if (map.hasLayer(pathLayer)) map.removeLayer(pathLayer);
    } else {
        btnPath.classList.add('active');
        btnHeat.classList.remove('active');
        if (!map.hasLayer(pathLayer)) pathLayer.addTo(map);
        if (map.hasLayer(heatmapLayer)) map.removeLayer(heatmapLayer);
    }
};

document.getElementById('btnHeatmap').onclick = () => toggleView('heatmap');
document.getElementById('btnPath').onclick = () => toggleView('path');

initMap();
loadMatches();
