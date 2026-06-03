/**
 * FUTRACK - Viewer Logic (Maps, Charts, Stats)
 */

let map;
let heatmapLayer;
let pathLayer;
let pitchLayer;
let speedChart;
let currentPoints = [];

const heatmapConfig = {
    radius: 0.000025,
    maxOpacity: .8,
    scaleRadius: true,
    useLocalExtrema: true,
    latField: 'lat',
    lngField: 'lng',
    valueField: 'count'
};

window.initMap = () => {
    if (!document.getElementById('map')) return;
    map = L.map('map').setView([0, 0], 2);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap contributors',
        className: 'map-tiles'
    }).addTo(map);

    heatmapLayer = new HeatmapOverlay(heatmapConfig);
    pathLayer = L.polyline([], { color: '#39FF14', weight: 4, opacity: 0.7 });
    pitchLayer = L.rectangle([[0,0],[0,0]], { color: '#fff', weight: 1, fillOpacity: 0.05, dashArray: '5, 10' });
};

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

const smoothPoints = (points) => {
    const windowSize = 3;
    const smoothed = [];
    for (let i = 0; i < points.length; i++) {
        const start = Math.max(0, i - Math.floor(windowSize / 2));
        const end = Math.min(points.length, i + Math.floor(windowSize / 2) + 1);
        const subset = points.slice(start, end);
        const avgLat = subset.reduce((a, b) => a + b.lat, 0) / subset.length;
        const avgLng = subset.reduce((a, b) => a + b.lng, 0) / subset.length;
        smoothed.push({ ...points[i], lat: avgLat, lng: avgLng });
    }
    return smoothed;
};

window.processStats = () => {
    let totalDist = 0;
    let maxS = 0;
    for (let i = 0; i < currentPoints.length; i++) {
        const p = currentPoints[i];
        if (p.speed > maxS) maxS = p.speed;
        if (i > 0) {
            const prev = currentPoints[i - 1];
            const dist = getDistance(prev.lat, prev.lng, p.lat, p.lng);
            if (dist < 40) totalDist += dist;
        }
    }
    document.getElementById('totalPoints').innerText = currentPoints.length;
    document.getElementById('maxSpeed').innerText = (maxS * 3.6).toFixed(1) + ' km/h';
    if (totalDist > 1000) document.getElementById('totalDistance').innerText = (totalDist / 1000).toFixed(2) + ' km';
    else document.getElementById('totalDistance').innerText = Math.round(totalDist) + ' m';
};

window.renderChart = () => {
    const ctx = document.getElementById('speedChart').getContext('2d');
    if (speedChart) speedChart.destroy();
    const labels = currentPoints.map((p, i) => i);
    speedChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [{
                label: 'Velocidad (km/h)',
                data: currentPoints.map(p => (p.speed * 3.6).toFixed(1)),
                borderColor: '#39FF14',
                backgroundColor: 'rgba(57, 255, 20, 0.1)',
                fill: true, tension: 0.4, pointRadius: 0
            }]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                y: { beginAtZero: true, grid: { color: 'rgba(255,255,255,0.05)' }, ticks: { color: '#64748b' } },
                x: { display: false }
            }
        }
    });
};

window.renderData = () => {
    if (currentPoints.length === 0) return;
    const lats = currentPoints.map(p => p.lat);
    const lngs = currentPoints.map(p => p.lng);
    const centerLat = lats.reduce((a,b) => a+b)/lats.length;
    const centerLng = lngs.reduce((a,b) => a+b)/lngs.length;
    const latOffset = (18 / 2) / 111320; 
    const lngOffset = (36 / 2) / (111320 * Math.cos(centerLat * Math.PI / 180));
    const b = [[centerLat - latOffset, centerLng - lngOffset], [centerLat + latOffset, centerLng + lngOffset]];
    pitchLayer.setBounds(b);
    if (!map.hasLayer(pitchLayer)) pitchLayer.addTo(map);
    map.fitBounds(b, { padding: [50, 50] });
    map.removeLayer(heatmapLayer);
    heatmapLayer.setData({ max: 5, data: currentPoints });
    heatmapLayer.addTo(map);
    pathLayer.setLatLngs(currentPoints.map(p => [p.lat, p.lng]));
    pathLayer.addTo(map);
    window.toggleView('heatmap');
};

window.toggleView = (type) => {
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

window.setData = (data) => {
    const rawPoints = data.map(p => ({
        lat: parseFloat(p.lat),
        lng: parseFloat(p.lng),
        speed: parseFloat(p.speed || 0),
        timestamp: parseInt(p.timestamp),
        count: 1
    }));
    currentPoints = smoothPoints(rawPoints);
};
