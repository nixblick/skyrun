document.addEventListener('DOMContentLoaded', function() {
    // Konstanten und Konfiguration
    const MAX_PARTICIPANTS = 25;
    const ADMIN_PASSWORD = 'skyrun2025'; // In einer echten Anwendung sollte das sicherer sein
    const STORAGE_KEY = 'skyrun_registrations';
    
    // DOM-Elemente
    const form = document.getElementById('skyrun-form');
    const runDateSelect = document.getElementById('run-date');
    const registrationStatus = document.getElementById('registration-status');
    const currentRegistrationsEl = document.getElementById('current-registrations');
    const currentWaitlistEl = document.getElementById('current-waitlist');
    const daysToRunEl = document.getElementById('days-to-run');
    const adminLink = document.getElementById('show-admin');
    const adminPanel = document.querySelector('.admin-panel');
    const adminPasswordInput = document.getElementById('admin-password');
    const adminLoginBtn = document.getElementById('admin-login-btn');
    const adminContent = document.getElementById('admin-content');
    const tabButtons = document.querySelectorAll('.tab-btn');
    const tabContents = document.querySelectorAll('.tab-content');
    const adminDateSelect = document.getElementById('admin-date-select');
    const waitlistDateSelect = document.getElementById('waitlist-date-select');
    const exportDateSelect = document.getElementById('export-date-select');
    const exportCsvBtn = document.getElementById('export-csv-btn');
    const exportJsonBtn = document.getElementById('export-json-btn');
    const importJsonInput = document.getElementById('import-json');
    const importBtn = document.getElementById('import-btn');
    const confirmationModal = document.getElementById('confirmation-modal');
    const confirmationMessage = document.getElementById('confirmation-message');
    const closeModalBtn = document.getElementById('close-modal-btn');
    const closeModalX = document.querySelector('.close-modal');
    
    // Datenstrukturen
    let registrations = loadRegistrations();
    
    // Initialisierung
    init();
    
    // Funktionen
    function init() {
        generateRunDates();
        updateStatistics();
        setupEventListeners();
    }
    
    function generateRunDates() {
        // Nächsten 4 Donnerstage generieren
        const today = new Date();
        let nextThursday = new Date(today);
        
        // Auf den nächsten Donnerstag setzen (Donnerstag hat Index 4)
        nextThursday.setDate(today.getDate() + ((4 + 7 - today.getDay()) % 7));
        
        // Datum-Selektoren leeren
        runDateSelect.innerHTML = '';
        adminDateSelect.innerHTML = '';
        waitlistDateSelect.innerHTML = '';
        exportDateSelect.innerHTML = '';
        
        // Die nächsten 4 Donnerstage hinzufügen
        for (let i = 0; i < 4; i++) {
            const runDate = new Date(nextThursday);
            runDate.setDate(nextThursday.getDate() + (i * 7));
            
            const dateStr = formatDate(runDate);
            const dateValue = runDate.toISOString().split('T')[0];
            
            // Zum Anmeldeformular hinzufügen
            const option = document.createElement('option');
            option.value = dateValue;
            option.textContent = dateStr;
            runDateSelect.appendChild(option);
            
            // Zu den Admin-Selektoren hinzufügen
            const adminOption = option.cloneNode(true);
            const waitlistOption = option.cloneNode(true);
            const exportOption = option.cloneNode(true);
            
            adminDateSelect.appendChild(adminOption);
            waitlistDateSelect.appendChild(waitlistOption);
            exportDateSelect.appendChild(exportOption);
        }
        
        // Tage bis zum nächsten Run berechnen
        const daysDiff = Math.ceil((nextThursday - today) / (1000 * 60 * 60 * 24));
        daysToRunEl.textContent = daysDiff;
    }
    
    function formatDate(date) {
        return date.toLocaleDateString('de-DE', { 
            weekday: 'long', 
            day: '2-digit', 
            month: '2-digit', 
            year: 'numeric' 
        });
    }
    
    function setupEventListeners() {
        // Anmeldeformular absenden
        form.addEventListener('submit', handleRegistration);
        
        // Admin-Bereich anzeigen
        adminLink.addEventListener('click', function(e) {
            e.preventDefault();
            adminPanel.classList.remove('hidden');
            window.scrollTo({
                top: adminPanel.offsetTop,
                behavior: 'smooth'
            });
        });
        
        // Admin-Login
        adminLoginBtn.addEventListener('click', handleAdminLogin);
        
        // Tab-Navigation
        tabButtons.forEach(button => {
            button.addEventListener('click', function() {
                const tabName = this.dataset.tab;
                
                // Active-Klasse von allen Tabs entfernen
                tabButtons.forEach(btn => btn.classList.remove('active'));
                tabContents.forEach(content => content.classList.remove('active'));
                
                // Active-Klasse zum ausgewählten Tab hinzufügen
                this.classList.add('active');
                document.getElementById(`${tabName}-tab`).classList.add('active');
                
                // Wenn der Teilnehmer-Tab ausgewählt ist, Liste aktualisieren
                if (tabName === 'participants') {
                    updateParticipantsList();
                } else if (tabName === 'waitlist') {
                    updateWaitlistTable();
                }
            });
        });
        
        // Datum in Admin-Bereich ändern
        adminDateSelect.addEventListener('change', updateParticipantsList);
        waitlistDateSelect.addEventListener('change', updateWaitlistTable);
        
        // Daten exportieren
        exportCsvBtn.addEventListener('click', exportAsCSV);
        exportJsonBtn.addEventListener('click', exportAsJSON);
        
        // Daten importieren
        importBtn.addEventListener('click', importData);
        
        // Modal schließen
        closeModalBtn.addEventListener('click', function() {
            confirmationModal.classList.add('hidden');
        });
        
        closeModalX.addEventListener('click', function() {
            confirmationModal.classList.add('hidden');
        });
        
        // Event-Listener für Datum-Änderung im Anmeldeformular
        runDateSelect.addEventListener('change', updateStatistics);
    }
    
    function handleRegistration(e) {
        e.preventDefault();
        
        const name = document.getElementById('name').value.trim();
        const email = document.getElementById('email').value.trim();
        const phone = document.getElementById('phone').value.trim();
        const date = document.getElementById('run-date').value;
        const acceptWaitlist = document.getElementById('waitlist').checked;
        
        // Überprüfen, ob die E-Mail bereits registriert ist
        if (isEmailRegistered(email, date)) {
            showStatus('Diese E-Mail-Adresse ist für dieses Datum bereits registriert.', 'error');
            return;
        }
        
        // Anzahl der Teilnehmer für das ausgewählte Datum zählen
        const participantsCount = countParticipants(date);
        
        // Entscheiden, ob der Teilnehmer in die Hauptliste oder Warteliste kommt
        const isWaitlisted = participantsCount >= MAX_PARTICIPANTS;
        
        if (isWaitlisted && !acceptWaitlist) {
            showStatus(`Für dieses Datum sind bereits alle ${MAX_PARTICIPANTS} Plätze belegt. Bitte wählen Sie ein anderes Datum oder aktivieren Sie die Warteliste-Option.`, 'warning');
            return;
        }
        
        // Neue Registrierung erstellen
        const registration = {
            name,
            email,
            phone,
            date,
            waitlisted: isWaitlisted,
            registrationTime: new Date().toISOString()
        };
        
        // Zur Liste der Registrierungen hinzufügen
        if (!registrations[date]) {
            registrations[date] = [];
        }
        
        registrations[date].push(registration);
        saveRegistrations();
        
        // Statistiken aktualisieren
        updateStatistics();
        
        // Formular zurücksetzen
        form.reset();
        
        // Bestätigungsnachricht anzeigen
        let message;
        if (isWaitlisted) {
            message = `Sie wurden erfolgreich auf die Warteliste für den ${formatDate(new Date(date))} gesetzt. Wir benachrichtigen Sie, falls ein Platz frei wird.`;
        } else {
            message = `Ihre Anmeldung für den ${formatDate(new Date(date))} wurde erfolgreich registriert. Bitte seien Sie 15 Minuten vor Beginn vor Ort.`;
        }
        
        confirmationMessage.textContent = message;
        confirmationModal.classList.remove('hidden');
    }
    
    function isEmailRegistered(email, date) {
        if (!registrations[date]) return false;
        
        return registrations[date].some(reg => reg.email === email);
    }
    
    function countParticipants(date) {
        if (!registrations[date]) return 0;
        
        return registrations[date].filter(reg => !reg.waitlisted).length;
    }
    
    function countWaitlisted(date) {
        if (!registrations[date]) return 0;
        
        return registrations[date].filter(reg => reg.waitlisted).length;
    }
    
    function showStatus(message, type) {
        registrationStatus.textContent = message;
        registrationStatus.className = type;
        registrationStatus.classList.remove('hidden');
        
        // Nach 5 Sekunden ausblenden
        setTimeout(() => {
            registrationStatus.classList.add('hidden');
        }, 5000);
    }
    
    function updateStatistics() {
        const selectedDate = runDateSelect.value;
        const participantsCount = countParticipants(selectedDate);
        const waitlistCount = countWaitlisted(selectedDate);
        
        currentRegistrationsEl.textContent = participantsCount;
        currentWaitlistEl.textContent = waitlistCount;
    }
    
    function handleAdminLogin() {
        const password = adminPasswordInput.value;
        
        if (password === ADMIN_PASSWORD) {
            adminContent.classList.remove('hidden');
            adminPasswordInput.value = '';
            
            // Initial den ersten Tab aktivieren und Daten laden
            updateParticipantsList();
        } else {
            alert('Falsches Passwort!');
        }
    }
    
    function updateParticipantsList() {
        const selectedDate = adminDateSelect.value;
        const participantsList = document.getElementById('participants-list');
        
        // Liste leeren
        participantsList.innerHTML = '';
        
        // Keine Registrierungen für dieses Datum
        if (!registrations[selectedDate]) {
            const noDataRow = document.createElement('tr');
            noDataRow.innerHTML = `<td colspan="4">Keine Anmeldungen für dieses Datum</td>`;
            participantsList.appendChild(noDataRow);
            return;
        }
        
        // Nur nicht-waitlisted Teilnehmer anzeigen, sortiert nach Anmeldezeit
        const participants = registrations[selectedDate]
            .filter(reg => !reg.waitlisted)
            .sort((a, b) => new Date(a.registrationTime) - new Date(b.registrationTime));
        
        if (participants.length === 0) {
            const noDataRow = document.createElement('tr');
            noDataRow.innerHTML = `<td colspan="4">Keine Anmeldungen für dieses Datum</td>`;
            participantsList.appendChild(noDataRow);
            return;
        }
        
        // Teilnehmer hinzufügen
        participants.forEach((participant, index) => {
            const row = document.createElement('tr');
            row.innerHTML = `
                <td>${participant.name}</td>
                <td>${participant.email}</td>
                <td>${participant.phone || '-'}</td>
                <td>
                    <button class="action-btn remove-btn" data-email="${participant.email}" data-date="${selectedDate}">Entfernen</button>
                </td>
            `;
            
            participantsList.appendChild(row);
        });
        
        // Event-Listener für Aktionsbuttons hinzufügen
        const removeButtons = participantsList.querySelectorAll('.remove-btn');
        removeButtons.forEach(button => {
            button.addEventListener('click', function() {
                const email = this.dataset.email;
                const date = this.dataset.date;
                removeParticipant(email, date);
                updateParticipantsList();
                updateWaitlistTable();
                updateStatistics();
            });
        });
    }
    
    function updateWaitlistTable() {
        const selectedDate = waitlistDateSelect.value;
        const waitlistTable = document.getElementById('waitlist-list');
        
        // Liste leeren
        waitlistTable.innerHTML = '';
        
        // Keine Registrierungen für dieses Datum
        if (!registrations[selectedDate]) {
            const noDataRow = document.createElement('tr');
            noDataRow.innerHTML = `<td colspan="4">Keine Wartenden für dieses Datum</td>`;
            waitlistTable.appendChild(noDataRow);
            return;
        }
        
        // Nur waitlisted Teilnehmer anzeigen, sortiert nach Anmeldezeit
        const waitlisted = registrations[selectedDate]
            .filter(reg => reg.waitlisted)
            .sort((a, b) => new Date(a.registrationTime) - new Date(b.registrationTime));
        
        if (waitlisted.length === 0) {
            const noDataRow = document.createElement('tr');
            noDataRow.innerHTML = `<td colspan="4">Keine Wartenden für dieses Datum</td>`;
            waitlistTable.appendChild(noDataRow);
            return;
        }
        
        // Wartende hinzufügen
        waitlisted.forEach((person, index) => {
            const row = document.createElement('tr');
            row.innerHTML = `
                <td>${person.name}</td>
                <td>${person.email}</td>
                <td>${person.phone || '-'}</td>
                <td>
                    <button class="action-btn promote-btn" data-email="${person.email}" data-date="${selectedDate}">Hochstufen</button>
                    <button class="action-btn remove-btn" data-email="${person.email}" data-date="${selectedDate}">Entfernen</button>
                </td>
            `;
            
            waitlistTable.appendChild(row);
        });
        
        // Event-Listener für Aktionsbuttons hinzufügen
        const promoteButtons = waitlistTable.querySelectorAll('.promote-btn');
        const removeButtons = waitlistTable.querySelectorAll('.remove-btn');
        
        promoteButtons.forEach(button => {
            button.addEventListener('click', function() {
                const email = this.dataset.email;
                const date = this.dataset.date;
                promoteFromWaitlist(email, date);
                updateParticipantsList();
                updateWaitlistTable();
                updateStatistics();
            });
        });
        
        removeButtons.forEach(button => {
            button.addEventListener('click', function() {
                const email = this.dataset.email;
                const date = this.dataset.date;
                removeParticipant(email, date);
                updateWaitlistTable();
                updateStatistics();
            });
        });
    }
    
    function removeParticipant(email, date) {
        if (!registrations[date]) return;
        
        // Teilnehmer aus der Liste entfernen
        registrations[date] = registrations[date].filter(reg => reg.email !== email);
        
        // Falls es sich um einen regulären Teilnehmer handelte, den ersten von der Warteliste hochstufen
        promoteNextFromWaitlist(date);
        
        saveRegistrations();
    }
    
    function promoteFromWaitlist(email, date) {
        if (!registrations[date]) return;
        
        // Person in der Liste finden
        const personIndex = registrations[date].findIndex(reg => reg.email === email && reg.waitlisted);
        
        if (personIndex !== -1) {
            // Von Warteliste entfernen
            registrations[date][personIndex].waitlisted = false;
            saveRegistrations();
        }
    }
    
    function promoteNextFromWaitlist(date) {
        if (!registrations[date]) return;
        
        // Reguläre Teilnehmer zählen
        const participantsCount = countParticipants(date);
        
        // Wenn noch Platz ist und es Wartende gibt
        if (participantsCount < MAX_PARTICIPANTS) {
            // Ersten Wartenden finden
            const waitingIndex = registrations[date].findIndex(reg => reg.waitlisted);
            
            if (waitingIndex !== -1) {
                // Von Warteliste entfernen
                registrations[date][waitingIndex].waitlisted = false;
                saveRegistrations();
            }
        }
    }
    
    function exportAsCSV() {
        const selectedDate = exportDateSelect.value;
        
        if (!registrations[selectedDate] || registrations[selectedDate].length === 0) {
            alert('Keine Daten für dieses Datum vorhanden.');
            return;
        }
        
        // CSV-Header
        let csvContent = 'Name,E-Mail,Telefon,Status,Anmeldezeit\n';
        
        // Daten hinzufügen
        registrations[selectedDate].forEach(reg => {
            const status = reg.waitlisted ? 'Warteliste' : 'Angemeldet';
            const phone = reg.phone || '';
            const row = `"${reg.name}","${reg.email}","${phone}","${status}","${reg.registrationTime}"\n`;
            csvContent += row;
        });
        
        // Download initiieren
        const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
        const url = URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.setAttribute('href', url);
        link.setAttribute('download', `skyrun_${selectedDate}.csv`);
        link.style.visibility = 'hidden';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }
    
    function exportAsJSON() {
        const selectedDate = exportDateSelect.value;
        
        if (!registrations[selectedDate] || registrations[selectedDate].length === 0) {
            alert('Keine Daten für dieses Datum vorhanden.');
            return;
        }
        
        // Nur das ausgewählte Datum exportieren
        const exportData = {
            [selectedDate]: registrations[selectedDate]
        };
        
        // Download initiieren
        const blob = new Blob([JSON.stringify(exportData, null, 2)], { type: 'application/json' });
        const url = URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.setAttribute('href', url);
        link.setAttribute('download', `skyrun_${selectedDate}.json`);
        link.style.visibility = 'hidden';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }
    
    function importData() {
        const fileInput = document.getElementById('import-json');
        const file = fileInput.files[0];
        
        if (!file) {
            alert('Bitte wählen Sie eine Datei aus.');
            return;
        }
        
        const reader = new FileReader();
        
        reader.onload = function(e) {
            try {
                const importedData = JSON.parse(e.target.result);
                
                // Daten zur vorhandenen Registrierung hinzufügen
                for (const date in importedData) {
                    if (!registrations[date]) {
                        registrations[date] = [];
                    }
                    
                    // Duplikate vermeiden
                    importedData[date].forEach(importReg => {
                        const isDuplicate = registrations[date].some(reg => reg.email === importReg.email);
                        
                        if (!isDuplicate) {
                            registrations[date].push(importReg);
                        }
                    });
                }
                
                saveRegistrations();
                updateParticipantsList();
                updateWaitlistTable();
                updateStatistics();
                
                alert('Daten erfolgreich importiert!');
                fileInput.value = '';
            } catch (error) {
                alert('Fehler beim Importieren der Daten: ' + error.message);
            }
        };
        
        reader.readAsText(file);
    }
    
    function loadRegistrations() {
        const storedData = localStorage.getItem(STORAGE_KEY);
        return storedData ? JSON.parse(storedData) : {};
    }
    
    function saveRegistrations() {
        localStorage.setItem(STORAGE_KEY, JSON.stringify(registrations));
    }
});