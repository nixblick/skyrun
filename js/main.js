/**
 * /js/main.js
 * Version: 1.1.0
 * Main JavaScript functionality
 */

(function() {
    // Globale Variablen
    //const API_URL = 'api/';
    const API_URL = 'api.php';
    let config = { maxParticipants: 25, runDay: 4, runTime: '19:00', runFrequency: 'weekly' };
    
    // DOM-Elemente
    const upcomingRunsEl = document.getElementById('upcoming-runs');
    const runDateSelect = document.getElementById('run-date');
    const adminDateSelect = document.getElementById('admin-date-select');
    const waitlistDateSelect = document.getElementById('waitlist-date-select');
    const exportDateSelect = document.getElementById('export-date-select');
    const maxParticipantsInput = document.getElementById('max-participants');
    
    // Initialisierung
    async function init() {
        try {
            await loadConfig();
            await loadStations();
            await generateRunDates();
            updateStatistics();
            setupEventListeners();
        } catch (error) {
            console.error('Initialisierungsfehler:', error);
            window.skyrunApp.showStatus('Fehler beim Laden der Seite.', 'error');
        }
    }
    
    // Konfiguration laden
    async function loadConfig() {
        const response = await fetch(`${API_URL}?action=getConfig`);
        if (!response.ok) throw new Error('HTTP Fehler');
        const result = await response.json();
        if (result.success && result.config) {
            config.maxParticipants = parseInt(result.config.max_participants) || 25;

            if (maxParticipantsInput) maxParticipantsInput.value = config.maxParticipants;
        }
    }
    
    // Wachen laden
    async function loadStations() {
        try {
            const stationSelect = document.getElementById('station');
            if (!stationSelect) return;
            
            const response = await fetch(`${API_URL}?action=getStations`);
            if (!response.ok) throw new Error('HTTP Fehler');
            const result = await response.json();
            
            if (result.success && stationSelect) {
                stationSelect.innerHTML = ''; // Leere das Dropdown
                
                // Berufsfeuerwehr
                if (result.stations.BF && result.stations.BF.length > 0) {
                    const bfGroup = document.createElement('optgroup');
                    bfGroup.label = 'Berufsfeuerwehr (BF)';
                    result.stations.BF.forEach(station => {
                        const option = document.createElement('option');
                        option.value = `${station.code} - ${station.name}`;
                        option.textContent = `${station.code} - ${station.name}`;
                        bfGroup.appendChild(option);
                    });
                    stationSelect.appendChild(bfGroup);
                }
                
                // Freiwillige Feuerwehr
                if (result.stations.FF && result.stations.FF.length > 0) {
                    const ffGroup = document.createElement('optgroup');
                    ffGroup.label = 'Freiwillige Feuerwehr (FF)';
                    result.stations.FF.forEach(station => {
                        const option = document.createElement('option');
                        option.value = `${station.code} - ${station.name}`;
                        option.textContent = `${station.code} - ${station.name}`;
                        ffGroup.appendChild(option);
                    });
                    stationSelect.appendChild(ffGroup);
                }
                
                // Sonstige
                if (result.stations.Sonstige && result.stations.Sonstige.length > 0) {
                    const sonstigeGroup = document.createElement('optgroup');
                    sonstigeGroup.label = 'Zusätzliche';
                    result.stations.Sonstige.forEach(station => {
                        const option = document.createElement('option');
                        option.value = `${station.code} - ${station.name}`;
                        option.textContent = `${station.code} - ${station.name}`;
                        if (station.code === '50') {
                            option.selected = true; // Standard auf "Sonstige" setzen
                        }
                        sonstigeGroup.appendChild(option);
                    });
                    stationSelect.appendChild(sonstigeGroup);
                }
            }
        } catch (error) {
            console.error('Fehler beim Laden der Stationen:', error);
            // Fallback: Verwende vorhandene statische Optionen im HTML
        }
    }
    
    // Trainingstermine aus Datenbank laden
    async function generateRunDates() {
        try {
            const response = await fetch(`${API_URL}?action=getTrainingDates`);
            if (!response.ok) throw new Error('HTTP Fehler');
            const result = await response.json();

            // Dropdown-Menüs leeren
            [runDateSelect, adminDateSelect, waitlistDateSelect, exportDateSelect].forEach(select => {
                if (select) select.innerHTML = '';
            });

            if (result.success && result.dates && result.dates.length > 0) {
                const now = new Date();

                // Optionen hinzufügen
                result.dates.forEach(dateInfo => {
                    const [year, month, day] = dateInfo.date.split('-').map(Number);
                    const [hours, minutes] = dateInfo.time.split(':').map(Number);
                    const runDate = new Date(year, month - 1, day, hours, minutes);

                    const buildingLabel = dateInfo.building === 'Trianon' ? 'Trianon' : 'MesseTurm';
                    const dateStr = window.skyrunApp.formatDate(runDate) + ' \u2013 ' + dateInfo.time + ' Uhr \u2013 ' + buildingLabel;
                    const option = document.createElement('option');
                    option.value = dateInfo.date;
                    option.textContent = dateStr;

                    [runDateSelect, adminDateSelect, waitlistDateSelect, exportDateSelect].forEach(select => {
                        if (select) select.appendChild(option.cloneNode(true));
                    });
                });

                // Nächste Termine als Übersicht rendern (max. 3)
                renderUpcomingRuns(result.dates.slice(0, 3), now);
            } else {
                // Keine Termine
                if (upcomingRunsEl) upcomingRunsEl.innerHTML = '<p style="color:var(--text-secondary)">Keine Termine verf\u00fcgbar</p>';

                const emptyOption = document.createElement('option');
                emptyOption.value = '';
                emptyOption.textContent = 'Keine Termine verf\u00fcgbar';
                [runDateSelect, adminDateSelect, waitlistDateSelect, exportDateSelect].forEach(select => {
                    if (select) select.appendChild(emptyOption.cloneNode(true));
                });
            }
        } catch (error) {
            console.error('Fehler beim Laden der Trainingstermine:', error);
        }
    }

    // Nächste Termine rendern mit Stats
    async function renderUpcomingRuns(dates, now) {
        if (!upcomingRunsEl) return;
        upcomingRunsEl.innerHTML = '';

        var weekdays = ['So', 'Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa'];

        for (var i = 0; i < dates.length; i++) {
            var dateInfo = dates[i];
            var parts = dateInfo.date.split('-').map(Number);
            var runDate = new Date(parts[0], parts[1] - 1, parts[2]);
            var diffTime = runDate.getTime() - now.getTime();
            var daysUntil = Math.max(0, Math.ceil(diffTime / (1000 * 60 * 60 * 24)));
            var buildingLabel = dateInfo.building === 'Trianon' ? 'Trianon' : 'MesseTurm';
            var dayLabel = daysUntil === 0 ? 'Heute' : daysUntil === 1 ? 'Morgen' : 'in ' + daysUntil + ' Tagen';
            var wd = weekdays[runDate.getDay()];
            var dateStr = wd + ', ' + parts[2] + '.' + parts[1] + '. \u2013 ' + dateInfo.time + ' Uhr';

            // Stats laden
            var stats = { participants: '?', waitlist: '?', maxParticipants: config.maxParticipants };
            try {
                var resp = await fetch(API_URL + '?action=getStats&date=' + dateInfo.date);
                if (resp.ok) {
                    var r = await resp.json();
                    if (r.success) stats = r;
                }
            } catch (e) { /* ignore */ }

            var card = document.createElement('div');
            card.className = 'upcoming-run';
            card.innerHTML =
                '<div class="run-info">' +
                    '<span class="run-date">' + dateStr + '</span>' +
                    '<span class="run-building">' + buildingLabel + '</span>' +
                '</div>' +
                '<div class="run-stats">' +
                    '<div class="run-stat">' +
                        '<span class="run-stat-value">' + stats.participants + '/' + stats.maxParticipants + '</span>' +
                        '<span class="run-stat-label">Angemeldet</span>' +
                    '</div>' +
                    '<div class="run-stat">' +
                        '<span class="run-stat-value">' + stats.waitlist + '</span>' +
                        '<span class="run-stat-label">Warteliste</span>' +
                    '</div>' +
                    '<span class="run-days">' + dayLabel + '</span>' +
                '</div>';

            upcomingRunsEl.appendChild(card);
        }
    }
    
    // Statistiken aktualisieren (für Formular-Kontext)
    async function updateStatistics() {
        if (!runDateSelect) return;
        var selectedDate = runDateSelect.value;
        try {
            var response = await fetch(API_URL + '?action=getStats&date=' + selectedDate);
            if (!response.ok) return;
            var result = await response.json();
            if (result.success) {
                config.maxParticipants = result.maxParticipants;
            }
        } catch (e) { /* ignore */ }
    }
    
    // Event-Listener einrichten
    function setupEventListeners() {
        // Basisevents für die Navigation
        const adminLink = document.getElementById('show-admin');
        const adminPanel = document.querySelector('.admin-panel');
        
        if (adminLink && adminPanel) {
            adminLink.addEventListener('click', e => {
                e.preventDefault();
                adminPanel.classList.toggle('hidden');
                if (!adminPanel.classList.contains('hidden')) {
                    window.scrollTo({ top: adminPanel.offsetTop, behavior: 'smooth' });
                }
            });
        }
        
        // Modal-Events
        const confirmationModal = document.getElementById('confirmation-modal');
        const closeModalBtn = document.getElementById('close-modal-btn');
        const closeModalX = document.querySelector('.close-modal');
        
        if (closeModalBtn) closeModalBtn.addEventListener('click', () => confirmationModal.classList.add('hidden'));
        if (closeModalX) closeModalX.addEventListener('click', () => confirmationModal.classList.add('hidden'));
        if (confirmationModal) {
            confirmationModal.addEventListener('click', e => {
                if (e.target === confirmationModal) confirmationModal.classList.add('hidden');
            });
        }
        
        // Weitere Events werden in den entsprechenden Modulen eingerichtet
    }
    
    // Initialisierung starten
    init();
    
    // Erweitere das globale App-Objekt mit weiteren Funktionen
    window.skyrunApp = Object.assign(window.skyrunApp || {}, {
        API_URL,
        config,
        updateStatistics,
        loadStations,
        generateRunDates
    });
})();
