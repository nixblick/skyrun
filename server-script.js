document.addEventListener('DOMContentLoaded', function() {
    // Konstanten und Konfiguration
    const MAX_PARTICIPANTS = 25;
    const API_URL = 'api.php'; // Pfad zum PHP-Backend
    
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
    
    // Admin-Authentifizierung
    let adminAuthenticated = false;
    let adminPassword = '';
    
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
        if (form) {
            form.addEventListener('submit', handleRegistration);
        }
        
        // Admin-Bereich anzeigen
        if (adminLink) {
            adminLink.addEventListener('click', function(e) {
                e.preventDefault();
                adminPanel.classList.remove('hidden');
                window.scrollTo({
                    top: adminPanel.offsetTop,
                    behavior: 'smooth'
                });
            });
        }
        
        // Admin-Login
        if (adminLoginBtn) {
            adminLoginBtn.addEventListener('click', handleAdminLogin);
        }
        
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
        if (adminDateSelect) {
            adminDateSelect.addEventListener('change', updateParticipantsList);
        }
        
        if (waitlistDateSelect) {
            waitlistDateSelect.addEventListener('change', updateWaitlistTable);
        }
        
        // Daten exportieren
        if (exportCsvBtn) {
            exportCsvBtn.addEventListener('click', exportAsCSV);
        }
        
        if (exportJsonBtn) {
            exportJsonBtn.addEventListener('click', exportAsJSON);
        }
        
        // Daten importieren
        if (importBtn) {
            importBtn.addEventListener('click', importData);
        }
        
        // Modal schließen
        if (closeModalBtn) {
            closeModalBtn.addEventListener('click', function() {
                confirmationModal.classList.add('hidden');
            });
        }
        
        if (closeModalX) {
            closeModalX.addEventListener('click', function() {
                confirmationModal.classList.add('hidden');
            });
        }
        
        // Event-Listener für Datum-Änderung im Anmeldeformular
        if (runDateSelect) {
            runDateSelect.addEventListener('change', updateStatistics);
        }
        
        // Modal bei Klick außerhalb schließen
        window.addEventListener('click', function(event) {
            if (event.target === confirmationModal) {
                confirmationModal.classList.add('hidden');
            }
        });
    }
    
    async function handleRegistration(e) {
        e.preventDefault();
        
        const name = document.getElementById('name').value.trim();
        const email = document.getElementById('email').value.trim();
        const phone = document.getElementById('phone').value.trim();
        const date = document.getElementById('run-date').value;
        const acceptWaitlist = document.getElementById('waitlist').checked;
        
        // Formular-Daten erstellen
        const formData = new FormData();
        formData.append('action', 'register');
        formData.append('name', name);
        formData.append('email', email);
        formData.append('phone', phone);
        formData.append('date', date);
        formData.append('acceptWaitlist', acceptWaitlist);
        
        try {
            const response = await fetch(API_URL, {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            
            if (result.success) {
                // Formular zurücksetzen
                form.reset();
                
                // Statistiken aktualisieren
                updateStatistics();
                
                // Bestätigungsnachricht anzeigen
                let message;
                if (result.isWaitlisted) {
                    message = `Sie wurden erfolgreich auf die Warteliste für den ${formatDate(new Date(date))} gesetzt. Wir benachrichtigen Sie, falls ein Platz frei wird.`;
                } else {
                    message = `Ihre Anmeldung für den ${formatDate(new Date(date))} wurde erfolgreich registriert. Bitte seien Sie 15 Minuten vor Beginn vor Ort.`;
                }
                
                confirmationMessage.textContent = message;
                confirmationModal.classList.remove('hidden');
            } else {
                showStatus(result.message, 'error');
            }
        } catch (error) {
            showStatus('Ein Fehler ist aufgetreten. Bitte versuchen Sie es später erneut.', 'error');
            console.error('Fehler bei der Anmeldung:', error);
        }
    }
    
    function showStatus(message, type) {
        registrationStatus.textContent = message;
        registrationStatus.classList.remove('success', 'error', 'warning', 'hidden');
        registrationStatus.classList.add(type);
        
        // Nach 5 Sekunden ausblenden
        setTimeout(() => {
            registrationStatus.classList.add('hidden');
        }, 5000);
    }
    
    async function updateStatistics() {
        const selectedDate = runDateSelect.value;
        
        try {
            const response = await fetch(`${API_URL}?action=getStats&date=${selectedDate}`);
            const result = await response.json();
            
            if (result.success) {
                currentRegistrationsEl.textContent = result.participants;
                currentWaitlistEl.textContent = result.waitlist;
            }
        } catch (error) {
            console.error('Fehler beim Abrufen der Statistiken:', error);
        }
    }
    
    async function handleAdminLogin() {
        const password = adminPasswordInput.value;
        
        // Formular-Daten erstellen
        const formData = new FormData();
        formData.append('action', 'adminLogin');
        formData.append('password', password);
        
        try {
            const response = await fetch(API_URL, {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            
            if (result.success) {
                adminAuthenticated = true;
                adminPassword = password;
                adminContent.classList.remove('hidden');
                adminPasswordInput.value = '';
                
                // Initial den ersten Tab aktivieren und Daten laden
                updateParticipantsList();
            } else {
                alert('Falsches Passwort!');
            }
        } catch (error) {
            alert('Ein Fehler ist aufgetreten. Bitte versuchen Sie es später erneut.');
            console.error('Fehler beim Admin-Login:', error);
        }
    }
    
    async function updateParticipantsList() {
        if (!adminAuthenticated) return;
        
        const selectedDate = adminDateSelect.value;
        const participantsList = document.getElementById('participants-list');
        
        // Liste leeren
        participantsList.innerHTML = '';
        
        // Formular-Daten erstellen
        const formData = new FormData();
        formData.append('action', 'getParticipants');
        formData.append('password', adminPassword);
        formData.append('date', selectedDate);
        
        try {
            const response = await fetch(API_URL, {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            
            if (result.success) {
                const participants = result.participants;
                
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
                    });
                });
            }
        } catch (error) {
            console.error('Fehler beim Abrufen der Teilnehmer:', error);
        }
    }
    
    async function updateWaitlistTable() {
        if (!adminAuthenticated) return;
        
        const selectedDate = waitlistDateSelect.value;
        const waitlistTable = document.getElementById('waitlist-list');
        
        // Liste leeren
        waitlistTable.innerHTML = '';
        
        // Formular-Daten erstellen
        const formData = new FormData();
        formData.append('action', 'getParticipants');
        formData.append('password', adminPassword);
        formData.append('date', selectedDate);
        
        try {
            const response = await fetch(API_URL, {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            
            if (result.success) {
                const waitlist = result.waitlist;
                
                if (waitlist.length === 0) {
                    const noDataRow = document.createElement('tr');
                    noDataRow.innerHTML = `<td colspan="4">Keine Wartenden für dieses Datum</td>`;
                    waitlistTable.appendChild(noDataRow);
                    return;
                }
                
                // Wartende hinzufügen
                waitlist.forEach((person, index) => {
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
                    });
                });
                
                removeButtons.forEach(button => {
                    button.addEventListener('click', function() {
                        const email = this.dataset.email;
                        const date = this.dataset.date;
                        removeParticipant(email, date);
                    });
                });
            }
        } catch (error) {
            console.error('Fehler beim Abrufen der Warteliste:', error);
        }
    }
    
    async function removeParticipant(email, date) {
        if (!adminAuthenticated) return;
        
        if (!confirm(`Möchten Sie wirklich ${email} entfernen?`)) {
            return;
        }
        
        // Formular-Daten erstellen
        const formData = new FormData();
        formData.append('action', 'removeParticipant');
        formData.append('password', adminPassword);
        formData.append('date', date);
        formData.append('email', email);
        
        try {
            const response = await fetch(API_URL, {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            
            if (result.success) {
                updateParticipantsList();
                updateWaitlistTable();
                alert('Teilnehmer erfolgreich entfernt.');
            } else {
                alert('Fehler beim Entfernen des Teilnehmers: ' + result.message);
            }
        } catch (error) {
            alert('Ein Fehler ist aufgetreten. Bitte versuchen Sie es später erneut.');
            console.error('Fehler beim Entfernen des Teilnehmers:', error);
        }
    }
    
    async function promoteFromWaitlist(email, date) {
        if (!adminAuthenticated) return;
        
        // Formular-Daten erstellen
        const formData = new FormData();
        formData.append('action', 'promoteFromWaitlist');
        formData.append('password', adminPassword);
        formData.append('date', date);
        formData.append('email', email);
        
        try {
            const response = await fetch(API_URL, {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            
            if (result.success) {
                updateParticipantsList();
                updateWaitlistTable();
                alert('Teilnehmer erfolgreich hochgestuft.');
            } else {
                alert('Fehler beim Hochstufen des Teilnehmers: ' + result.message);
            }
        } catch (error) {
            alert('Ein Fehler ist aufgetreten. Bitte versuchen Sie es später erneut.');
            console.error('Fehler beim Hochstufen des Teilnehmers:', error);
        }
    }
    
    async function exportAsCSV() {
        if (!adminAuthenticated) return;
        
        const selectedDate = exportDateSelect.value;
        
        // Formular-Daten erstellen
        const formData = new FormData();
        formData.append('action', 'getParticipants');
        formData.append('password', adminPassword);
        formData.append('date', selectedDate);
        
        try {
            const response = await fetch(API_URL, {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            
            if (result.success) {
                const participants = result.participants;
                const waitlist = result.waitlist;
                
                if (participants.length === 0 && waitlist.length === 0) {
                    alert('Keine Daten für dieses Datum vorhanden.');
                    return;
                }
                
                // CSV-Header
                let csvContent = 'Name,E-Mail,Telefon,Status,Anmeldezeit\n';
                
                // Daten hinzufügen
                participants.forEach(reg => {
                    const phone = reg.phone || '';
                    const row = `"${reg.name}","${reg.email}","${phone}","Angemeldet","${reg.registrationTime}"\n`;
                    csvContent += row;
                });
                
                waitlist.forEach(reg => {
                    const phone = reg.phone || '';
                    const row = `"${reg.name}","${reg.email}","${phone}","Warteliste","${reg.registrationTime}"\n`;
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
        } catch (error) {
            alert('Ein Fehler ist aufgetreten. Bitte versuchen Sie es später erneut.');
            console.error('Fehler beim Exportieren der Daten:', error);
        }
    }
    
    async function exportAsJSON() {
        if (!adminAuthenticated) return;
        
        // Formular-Daten erstellen
        const formData = new FormData();
        formData.append('action', 'exportData');
        formData.append('password', adminPassword);
        
        try {
            const response = await fetch(API_URL, {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            
            if (result.success) {
                // Download initiieren
                const blob = new Blob([JSON.stringify(result.data, null, 2)], { type: 'application/json' });
                const url = URL.createObjectURL(blob);
                const link = document.createElement('a');
                link.setAttribute('href', url);
                link.setAttribute('download', `skyrun_export.json`);
                link.style.visibility = 'hidden';
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
            }
        } catch (error) {
            alert('Ein Fehler ist aufgetreten. Bitte versuchen Sie es später erneut.');
            console.error('Fehler beim Exportieren der Daten:', error);
        }
    }
    
    async function importData() {
        if (!adminAuthenticated) return;
        
        const fileInput = document.getElementById('import-json');
        const file = fileInput.files[0];
        
        if (!file) {
            alert('Bitte wählen Sie eine Datei aus.');
            return;
        }
        
        const reader = new FileReader();
        
        reader.onload = async function(e) {
            try {
                const jsonData = e.target.result;
                
                // Formular-Daten erstellen
                const formData = new FormData();
                formData.append('action', 'importData');
                formData.append('password', adminPassword);
                formData.append('data', jsonData);
                
                const response = await fetch(API_URL, {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    alert('Daten erfolgreich importiert!');
                    fileInput.value = '';
                    updateParticipantsList();
                    updateWaitlistTable();
                } else {
                    alert('Fehler beim Importieren der Daten: ' + result.message);
                }
            } catch (error) {
                alert('Fehler beim Lesen der Datei oder Import der Daten.');
                console.error('Fehler beim Importieren der Daten:', error);
            }
        };
        
        reader.readAsText(file);
    }
});