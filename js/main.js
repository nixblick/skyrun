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
    const currentRegistrationsEl = document.getElementById('current-registrations');
    const maxRegistrationsEl = document.getElementById('max-registrations');
    const currentWaitlistEl = document.getElementById('current-waitlist');
    const daysToRunEl = document.getElementById('days-to-run');
    const nextRunDateEl = document.getElementById('next-run-date');
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

            if (maxRegistrationsEl) maxRegistrationsEl.textContent = config.maxParticipants;
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

                    const dateStr = window.skyrunApp.formatDate(runDate) + ' - ' + dateInfo.time + ' Uhr';
                    const option = document.createElement('option');
                    option.value = dateInfo.date;
                    option.textContent = dateStr;

                    [runDateSelect, adminDateSelect, waitlistDateSelect, exportDateSelect].forEach(select => {
                        if (select) select.appendChild(option.cloneNode(true));
                    });
                });

                // Tage bis zum nächsten Run + Datum anzeigen
                if (result.dates.length > 0) {
                    const nextDate = result.dates[0];
                    const [year, month, day] = nextDate.date.split('-').map(Number);
                    const nextRunDate = new Date(year, month - 1, day);

                    const diffTime = nextRunDate.getTime() - now.getTime();
                    const daysUntil = Math.max(0, Math.ceil(diffTime / (1000 * 60 * 60 * 24)));

                    if (daysToRunEl) daysToRunEl.textContent = daysUntil;
                    if (nextRunDateEl) nextRunDateEl.textContent = `${day}.${month}.`;
                }
            } else {
                // Keine Termine vorhanden
                if (daysToRunEl) daysToRunEl.textContent = '-';
                if (nextRunDateEl) nextRunDateEl.textContent = '';

                // Leere Option anzeigen
                const emptyOption = document.createElement('option');
                emptyOption.value = '';
                emptyOption.textContent = 'Keine Termine verfügbar';
                [runDateSelect, adminDateSelect, waitlistDateSelect, exportDateSelect].forEach(select => {
                    if (select) select.appendChild(emptyOption.cloneNode(true));
                });
            }
        } catch (error) {
            console.error('Fehler beim Laden der Trainingstermine:', error);
        }
    }
    
    // Statistiken aktualisieren
    async function updateStatistics() {
        if (!runDateSelect) return;
        const selectedDate = runDateSelect.value;
        const response = await fetch(`${API_URL}?action=getStats&date=${selectedDate}`);
        if (!response.ok) throw new Error('HTTP Fehler');
        const result = await response.json();
        if (result.success) {
            currentRegistrationsEl.textContent = result.participants;
            maxRegistrationsEl.textContent = result.maxParticipants;
            currentWaitlistEl.textContent = result.waitlist;
            config.maxParticipants = result.maxParticipants;
        }
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
