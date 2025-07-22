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
    const runDateSelect = document.getElementById('run-date');
    const adminDateSelect = document.getElementById('admin-date-select');
    const waitlistDateSelect = document.getElementById('waitlist-date-select');
    const exportDateSelect = document.getElementById('export-date-select');
    const maxParticipantsInput = document.getElementById('max-participants');
    const runDaySelect = document.getElementById('run-day');
    const runTimeInput = document.getElementById('run-time');
    const runFrequencySelect = document.getElementById('run-frequency');
    
    // Initialisierung
    async function init() {
        try {
            await loadConfig();
            await loadStations();
            generateRunDates();
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
            config.runDay = parseInt(result.config.run_day) || 4;
            config.runTime = result.config.run_time || '19:00';
            config.runFrequency = result.config.run_frequency || 'weekly'; // Neue Config
            
            if (maxRegistrationsEl) maxRegistrationsEl.textContent = config.maxParticipants;
            if (maxParticipantsInput) maxParticipantsInput.value = config.maxParticipants;
            if (runDaySelect) runDaySelect.value = config.runDay;
            if (runTimeInput) runTimeInput.value = config.runTime;
            if (runFrequencySelect) runFrequencySelect.value = config.runFrequency;
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
    
    // Datumsliste generieren
    function generateRunDates() {
        const now = new Date();
        const runDates = [];
        
        // Hole Konfiguration für Frequenz
        const runFrequency = config.runFrequency || 'weekly'; // Default: wöchentlich
        
        if (runFrequency === 'monthly_first') {
            // Ersten Donnerstag der nächsten 6 Monate finden
            for (let monthOffset = 0; monthOffset < 6; monthOffset++) {
                const targetMonth = new Date(now.getFullYear(), now.getMonth() + monthOffset, 1);
                const firstDayOfMonth = targetMonth.getDay();
                
                // Ersten Donnerstag berechnen (Donnerstag = 4)
                let firstThursday = 1 + ((4 - firstDayOfMonth + 7) % 7);
                if (firstThursday === 1 && firstDayOfMonth === 4) {
                    // Falls der 1. ein Donnerstag ist
                    firstThursday = 1;
                }
                
                const runDate = new Date(targetMonth.getFullYear(), targetMonth.getMonth(), firstThursday);
                const [runHours, runMinutes] = config.runTime.split(':').map(Number);
                runDate.setHours(runHours, runMinutes, 0, 0);
                
                // Nur zukünftige Termine oder heute (falls noch nicht vorbei)
                if (runDate > now || (runDate.toDateString() === now.toDateString() && runDate > now)) {
                    runDates.push(runDate);
                }
            }
        } else {
            // Original wöchentliche Logik
            let nextRunDay = new Date(now);
            const daysToAdd = (config.runDay + 7 - now.getDay()) % 7;
            nextRunDay.setDate(now.getDate() + daysToAdd);

            const [runHours, runMinutes] = config.runTime.split(':').map(Number);
            const runDateTimeToday = new Date(now);
            runDateTimeToday.setHours(runHours, runMinutes, 0, 0);

            if (daysToAdd === 0 && now >= runDateTimeToday) {
                nextRunDay.setDate(nextRunDay.getDate() + 7);
            }

            for (let i = 0; i < 4; i++) {
                const runDate = new Date(nextRunDay);
                runDate.setDate(nextRunDay.getDate() + (i * 7));
                runDates.push(runDate);
            }
        }
        
        // Dropdown-Menüs leeren
        [runDateSelect, adminDateSelect, waitlistDateSelect, exportDateSelect].forEach(select => {
            if (select) select.innerHTML = '';
        });

        // Optionen hinzufügen
        runDates.forEach(runDate => {
            const dateStr = window.skyrunApp.formatDate(runDate);
            const dateValue = runDate.toISOString().split('T')[0];
            const option = document.createElement('option');
            option.value = dateValue;
            option.textContent = dateStr;
            [runDateSelect, adminDateSelect, waitlistDateSelect, exportDateSelect].forEach(select => {
                if (select) select.appendChild(option.cloneNode(true));
            });
        });

        // Tage bis zum nächsten Run
        if (daysToRunEl && runDates.length > 0) {
            const diffTime = runDates[0].getTime() - now.getTime();
            daysToRunEl.textContent = Math.max(0, Math.ceil(diffTime / (1000 * 60 * 60 * 24)));
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
