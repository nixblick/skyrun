/**
 * /js/admin.js
 * Version: 1.1.0
 * Admin-related functionality
 */

(function() {
    // Auf skyrunApp zugreifen
    const { API_URL, escapeHTML, formatDate, downloadFile } = window.skyrunApp || {};
    
    // Admin-spezifische Variablen
    let adminAuthenticated = false;
    
    // DOM-Elemente für Admin
    const adminLoginBtn = document.getElementById('admin-login-btn');
    const adminUsernameInput = document.getElementById('admin-username');
    const adminPasswordInput = document.getElementById('admin-password');
    const adminContent = document.getElementById('admin-content');
    const adminDateSelect = document.getElementById('admin-date-select');
    const waitlistDateSelect = document.getElementById('waitlist-date-select');
    const exportDateSelect = document.getElementById('export-date-select');
    const exportCsvBtn = document.getElementById('export-csv-btn');
    const exportJsonBtn = document.getElementById('export-json-btn');
    const importJsonInput = document.getElementById('import-json');
    const importBtn = document.getElementById('import-btn');
    const settingsForm = document.getElementById('settings-form');
    const passwordForm = document.getElementById('password-form');
    const tabButtons = document.querySelectorAll('.tab-btn');
    
    // Admin-Login
    async function handleAdminLogin() {
        const username = adminUsernameInput.value.trim();
        const password = adminPasswordInput.value.trim();

        if (!username || !password) {
            alert('Bitte Benutzernamen und Passwort eingeben.');
            return;
        }

        adminLoginBtn.disabled = true;
        const formData = new FormData();
        formData.append('action', 'adminLogin');
        formData.append('username', username);
        formData.append('password', password);

        try {
            const response = await fetch(API_URL, { method: 'POST', body: formData });
            if (!response.ok) throw new Error('HTTP Fehler: ' + response.status);
            const result = await response.json();

            if (result.success) {
                adminAuthenticated = true;
                adminContent.classList.remove('hidden');
                adminUsernameInput.value = '';
                adminPasswordInput.value = '';
                document.querySelector('.admin-panel .form-group').classList.add('hidden');
                document.querySelector('.tab-btn[data-tab="participants"]').click();
                const logoutBtn = document.createElement('button');
                logoutBtn.textContent = 'Ausloggen';
                logoutBtn.id = 'admin-logout-btn';
                logoutBtn.addEventListener('click', handleAdminLogout);
                adminContent.insertBefore(logoutBtn, adminContent.firstChild);
            } else {
                alert(result.message || 'Login fehlgeschlagen.');
            }
        } catch (error) {
            alert('Login-Fehler: ' + error.message);
        } finally {
            adminLoginBtn.disabled = false;
        }
    }
    
    // Admin-Logout
    async function handleAdminLogout() {
        const formData = new FormData();
        formData.append('action', 'adminLogout');
        const response = await fetch(API_URL, { method: 'POST', body: formData });
        if (response.ok) {
            adminAuthenticated = false;
            adminContent.classList.add('hidden');
            document.querySelector('.admin-panel .form-group').classList.remove('hidden');
            document.getElementById('admin-logout-btn').remove();
            alert('Erfolgreich ausgeloggt.');
        }
    }
    
    // Tab-Wechsel
    function handleTabSwitch() {
        const tabName = this.dataset.tab;
        tabButtons.forEach(btn => btn.classList.remove('active'));
        document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
        this.classList.add('active');
        document.getElementById(`${tabName}-tab`).classList.add('active');
        if (adminAuthenticated) {
            if (tabName === 'participants') updateParticipantsList();
            else if (tabName === 'waitlist') updateWaitlistTable();
            else if (tabName === 'dates') loadTrainingDates();
            else if (tabName === 'peakbook') updatePeakBook();
        }
    }
    
    // Teilnehmerliste aktualisieren
    async function updateParticipantsList() {
        if (!adminAuthenticated) return;
        const selectedDate = adminDateSelect.value;
        const participantsListBody = document.getElementById('participants-list');
        participantsListBody.innerHTML = '<tr><td colspan="6">Lade Teilnehmer...</td></tr>';

        const formData = new FormData();
        formData.append('action', 'getParticipants');
        formData.append('date', selectedDate);

        try {
            const response = await fetch(API_URL, { method: 'POST', body: formData });
            if (!response.ok) {
                if (response.status === 401) handleAdminLogout();
                throw new Error('HTTP Fehler: ' + response.status);
            }
            const result = await response.json();

            participantsListBody.innerHTML = '';
            if (result.success) {
                if (result.participants.length === 0) {
                    participantsListBody.innerHTML = '<tr><td colspan="6">Keine Anmeldungen</td></tr>';
                } else {
                    result.participants.forEach(p => {
                        const row = participantsListBody.insertRow();
                        row.innerHTML = `
                            <td>${escapeHTML(p.name)}</td>
                            <td>${escapeHTML(p.email)}</td>
                            <td>${escapeHTML(p.phone || '-')}</td>
                            <td>${escapeHTML(p.station)}</td>
                            <td>${p.personCount}</td>
                            <td><button class="action-btn remove-btn" data-id="${p.id}" data-date="${selectedDate}">Entfernen</button></td>
                        `;
                        row.querySelector('.remove-btn').addEventListener('click', () => removeParticipant(p.id, selectedDate));
                    });
                }
                window.skyrunApp.config.maxParticipants = result.maxParticipants;
                document.getElementById('max-participants').value = window.skyrunApp.config.maxParticipants;
                document.getElementById('max-registrations').textContent = window.skyrunApp.config.maxParticipants;
            } else {
                participantsListBody.innerHTML = `<tr><td colspan="6">Fehler: ${result.message}</td></tr>`;
            }
        } catch (error) {
            participantsListBody.innerHTML = '<tr><td colspan="6">Netzwerkfehler</td></tr>';
            console.error('Fehler:', error);
        }
    }
    
    // Warteliste aktualisieren
    async function updateWaitlistTable() {
        if (!adminAuthenticated) return;
        const selectedDate = waitlistDateSelect.value;
        const waitlistTableBody = document.getElementById('waitlist-list');
        waitlistTableBody.innerHTML = '<tr><td colspan="6">Lade Warteliste...</td></tr>';

        const formData = new FormData();
        formData.append('action', 'getParticipants');
        formData.append('date', selectedDate);

        try {
            const response = await fetch(API_URL, { method: 'POST', body: formData });
            if (!response.ok) {
                if (response.status === 401) handleAdminLogout();
                throw new Error('HTTP Fehler');
            }
            const result = await response.json();

            waitlistTableBody.innerHTML = '';
            if (result.success) {
                if (result.waitlist.length === 0) {
                    waitlistTableBody.innerHTML = '<tr><td colspan="6">Keine Wartenden</td></tr>';
                } else {
                    result.waitlist.forEach(p => {
                        const row = waitlistTableBody.insertRow();
                        row.innerHTML = `
                            <td>${escapeHTML(p.name)}</td>
                            <td>${escapeHTML(p.email)}</td>
                            <td>${escapeHTML(p.phone || '-')}</td>
                            <td>${escapeHTML(p.station)}</td>
                            <td>${p.personCount}</td>
                            <td>
                                <button class="action-btn promote-btn" data-id="${p.id}" data-date="${selectedDate}">Hochstufen</button>
                                <button class="action-btn remove-btn" data-id="${p.id}" data-date="${selectedDate}">Entfernen</button>
                            </td>
                        `;
                        row.querySelector('.promote-btn').addEventListener('click', () => promoteFromWaitlist(p.id, selectedDate));
                        row.querySelector('.remove-btn').addEventListener('click', () => removeParticipant(p.id, selectedDate));
                    });
                }
            } else {
                waitlistTableBody.innerHTML = `<tr><td colspan="6">Fehler: ${result.message}</td></tr>`;
            }
        } catch (error) {
            waitlistTableBody.innerHTML = '<tr><td colspan="6">Netzwerkfehler</td></tr>';
        }
    }
    
    // Gipfelbuch aktualisieren
    async function updatePeakBook() {
        if (!adminAuthenticated) return;
        const peakBookListBody = document.getElementById('peakbook-list');
        peakBookListBody.innerHTML = '<tr><td colspan="2">Lade Gipfelbuch...</td></tr>';

        const buildingFilter = document.getElementById('peakbook-building-filter');
        const formData = new FormData();
        formData.append('action', 'getPeakBook');
        if (buildingFilter && buildingFilter.value) {
            formData.append('building', buildingFilter.value);
        }

        try {
            const response = await fetch(API_URL, { method: 'POST', body: formData });
            if (!response.ok) {
                if (response.status === 401) handleAdminLogout();
                throw new Error('HTTP Fehler');
            }
            const result = await response.json();

            peakBookListBody.innerHTML = '';
            if (result.success) {
                if (result.peakBook.length === 0) {
                    peakBookListBody.innerHTML = '<tr><td colspan="2">Keine Teilnahmen</td></tr>';
                } else {
                    result.peakBook.forEach(entry => {
                        const row = peakBookListBody.insertRow();
                        row.innerHTML = `
                            <td>${escapeHTML(entry.station)}</td>
                            <td>${entry.count}</td>
                        `;
                    });
                }
            } else {
                peakBookListBody.innerHTML = `<tr><td colspan="2">Fehler: ${result.message}</td></tr>`;
            }
        } catch (error) {
            peakBookListBody.innerHTML = '<tr><td colspan="2">Netzwerkfehler</td></tr>';
        }
    }
    
    // Teilnehmer entfernen
    async function removeParticipant(id, date) {
        if (!adminAuthenticated || !confirm(`Registrierung (ID: ${id}) entfernen?`)) return;

        const formData = new FormData();
        formData.append('action', 'removeParticipant');
        formData.append('id', id);
        formData.append('date', date);

        try {
            const response = await fetch(API_URL, { method: 'POST', body: formData });
            if (!response.ok) {
                if (response.status === 401) handleAdminLogout();
                throw new Error('HTTP Fehler');
            }
            const result = await response.json();

            if (result.success) {
                alert('Teilnehmer entfernt.');
                updateParticipantsList();
                updateWaitlistTable();
                window.skyrunApp.updateStatistics();
            } else {
                alert('Fehler: ' + result.message);
            }
        } catch (error) {
            alert('Entfernungsfehler.');
        }
    }
    
    // Teilnehmer von Warteliste hochstufen
    async function promoteFromWaitlist(id, date) {
        if (!adminAuthenticated || !confirm(`ID ${id} hochstufen?`)) return;

        const formData = new FormData();
        formData.append('action', 'promoteFromWaitlist');
        formData.append('id', id);
        formData.append('date', date);

        try {
            const response = await fetch(API_URL, { method: 'POST', body: formData });
            if (!response.ok) {
                if (response.status === 401) handleAdminLogout();
                throw new Error('HTTP Fehler');
            }
            const result = await response.json();

            if (result.success) {
                alert('Hochgestuft.');
                updateParticipantsList();
                updateWaitlistTable();
                window.skyrunApp.updateStatistics();
            } else {
                alert('Fehler: ' + result.message);
            }
        } catch (error) {
            alert('Hochstufungsfehler.');
        }
    }
    
    // Daten als CSV exportieren
    async function exportAsCSV() {
        if (!adminAuthenticated) return;
        const selectedDate = exportDateSelect.value;
        const formData = new FormData();
        formData.append('action', 'getParticipants');
        formData.append('date', selectedDate);

        const response = await fetch(API_URL, { method: 'POST', body: formData });
        if (!response.ok) {
            if (response.status === 401) handleAdminLogout();
            throw new Error('HTTP Fehler');
        }
        const result = await response.json();

        if (result.success) {
            const allRegistrations = [...result.participants, ...result.waitlist];
            if (allRegistrations.length === 0) {
                alert('Keine Daten zum Exportieren.');
                return;
            }
            let csvContent = '"Name","E-Mail","Telefon","Wache","Personen","Status","Anmeldezeit"\n';
            allRegistrations.forEach(reg => {
                csvContent += `"${reg.name.replace(/"/g, '""')}","${reg.email.replace(/"/g, '""')}","${(reg.phone || '').replace(/"/g, '""')}","${reg.station.replace(/"/g, '""')}","${reg.personCount}","${reg.waitlisted ? 'Warteliste' : 'Angemeldet'}","${reg.registrationTime}"\n`;
            });
            const blob = new Blob([`\uFEFF${csvContent}`], { type: 'text/csv;charset=utf-8;' });
            downloadFile(blob, `skyrun_${selectedDate}.csv`);
        }
    }
    
    // Daten als JSON exportieren
    async function exportAsJSON() {
        if (!adminAuthenticated) return;
        const formData = new FormData();
        formData.append('action', 'exportData');

        const response = await fetch(API_URL, { method: 'POST', body: formData });
        if (!response.ok) {
            if (response.status === 401) handleAdminLogout();
            throw new Error('HTTP Fehler');
        }
        const result = await response.json();

        if (result.success && Object.keys(result.data).length > 0) {
            const jsonData = JSON.stringify(result.data, null, 2);
            const blob = new Blob([jsonData], { type: 'application/json;charset=utf-8;' });
            const timestamp = new Date().toISOString().replace(/[:.]/g, '-');
            downloadFile(blob, `skyrun_export_${timestamp}.json`);
        } else {
            alert('Keine Daten zum Exportieren.');
        }
    }
    
    // Daten importieren
    async function importData() {
        if (!adminAuthenticated || !importJsonInput.files[0]) return;
        const file = importJsonInput.files[0];
        if (!confirm(`Daten aus '${file.name}' importieren?`)) return;

        const reader = new FileReader();
        reader.onload = async e => {
            const jsonData = e.target.result;
            const formData = new FormData();
            formData.append('action', 'importData');
            formData.append('data', jsonData);

            importBtn.disabled = true;
            try {
                const response = await fetch(API_URL, { method: 'POST', body: formData });
                if (!response.ok) {
                    if (response.status === 401) handleAdminLogout();
                    throw new Error('HTTP Fehler');
                }
                const result = await response.json();

                if (result.success) {
                    alert(result.message);
                    importJsonInput.value = '';
                    updateParticipantsList();
                    updateWaitlistTable();
                    window.skyrunApp.updateStatistics();
                } else {
                    alert('Importfehler: ' + result.message);
                }
            } catch (error) {
                alert('Importfehler.');
            } finally {
                importBtn.disabled = false;
            }
        };
        reader.readAsText(file);
    }
    
    // Einstellungen speichern
    async function saveSettings() {
        if (!adminAuthenticated) return;
        const maxParticipants = parseInt(document.getElementById('max-participants').value);

        if (isNaN(maxParticipants) || maxParticipants < 1) {
            alert('Ungültige Werte.');
            return;
        }

        const formData = new FormData();
        formData.append('action', 'updateConfig');
        formData.append('maxParticipants', maxParticipants);
        formData.append('runDay', 4); // Dummy-Wert für Kompatibilität
        formData.append('runTime', '19:00'); // Dummy-Wert für Kompatibilität

        const saveButton = document.getElementById('save-settings-btn');
        saveButton.disabled = true;

        try {
            const response = await fetch(API_URL, { method: 'POST', body: formData });
            if (!response.ok) {
                if (response.status === 401) handleAdminLogout();
                throw new Error('HTTP Fehler');
            }
            const result = await response.json();

            if (result.success) {
                alert('Einstellungen gespeichert.');
                window.skyrunApp.config.maxParticipants = maxParticipants;
                document.getElementById('max-registrations').textContent = maxParticipants;
                window.skyrunApp.updateStatistics();
            } else {
                alert('Fehler: ' + result.message);
            }
        } catch (error) {
            alert('Speicherfehler.');
        } finally {
            saveButton.disabled = false;
        }
    }

    // Trainingstermine laden und anzeigen
    async function loadTrainingDates() {
        if (!adminAuthenticated) return;
        const datesListBody = document.getElementById('dates-list');
        datesListBody.innerHTML = '<tr><td colspan="4">Lade Termine...</td></tr>';

        try {
            const response = await fetch(`${API_URL}?action=getTrainingDates`);
            if (!response.ok) throw new Error('HTTP Fehler');
            const result = await response.json();

            datesListBody.innerHTML = '';
            if (result.success && result.dates.length > 0) {
                result.dates.forEach(dateInfo => {
                    const row = datesListBody.insertRow();
                    const dateObj = new Date(dateInfo.date + 'T00:00:00');
                    const formattedDate = dateObj.toLocaleDateString('de-DE', { weekday: 'long', day: '2-digit', month: '2-digit', year: 'numeric' });
                    const buildingLabel = dateInfo.building === 'Trianon' ? 'Trianon Frankfurt' : 'MesseTurm Frankfurt';

                    row.innerHTML = `
                        <td>${formattedDate}</td>
                        <td>${dateInfo.time} Uhr</td>
                        <td>${escapeHTML(buildingLabel)}</td>
                        <td><button class="action-btn remove-btn" data-id="${dateInfo.id}">Löschen</button></td>
                    `;
                    row.querySelector('.remove-btn').addEventListener('click', () => deleteTrainingDate(dateInfo.id));
                });
            } else {
                datesListBody.innerHTML = '<tr><td colspan="4">Keine Termine vorhanden</td></tr>';
            }
        } catch (error) {
            datesListBody.innerHTML = '<tr><td colspan="4">Fehler beim Laden</td></tr>';
            console.error('Fehler:', error);
        }
    }

    // Neuen Trainingstermin hinzufügen
    async function addTrainingDate() {
        if (!adminAuthenticated) return;
        const dateInput     = document.getElementById('new-date');
        const timeInput     = document.getElementById('new-time');
        const buildingInput = document.getElementById('new-building');

        const date     = dateInput.value;
        const time     = timeInput.value;
        const building = buildingInput ? buildingInput.value : 'Messeturm';

        if (!date || !time) {
            alert('Bitte Datum und Uhrzeit angeben.');
            return;
        }

        const formData = new FormData();
        formData.append('action', 'addTrainingDate');
        formData.append('date', date);
        formData.append('time', time);
        formData.append('building', building);

        const addButton = document.getElementById('add-date-btn');
        addButton.disabled = true;

        try {
            const response = await fetch(API_URL, { method: 'POST', body: formData });
            if (!response.ok) {
                if (response.status === 401) handleAdminLogout();
                throw new Error('HTTP Fehler');
            }
            const result = await response.json();

            if (result.success) {
                alert('Termin hinzugefügt.');
                dateInput.value = '';
                loadTrainingDates();
                window.skyrunApp.generateRunDates();
            } else {
                alert('Fehler: ' + result.message);
            }
        } catch (error) {
            alert('Fehler beim Hinzufügen.');
        } finally {
            addButton.disabled = false;
        }
    }

    // Trainingstermin löschen
    async function deleteTrainingDate(id) {
        if (!adminAuthenticated || !confirm('Termin wirklich löschen?')) return;

        const formData = new FormData();
        formData.append('action', 'deleteTrainingDate');
        formData.append('id', id);

        try {
            const response = await fetch(API_URL, { method: 'POST', body: formData });
            if (!response.ok) {
                if (response.status === 401) handleAdminLogout();
                throw new Error('HTTP Fehler');
            }
            const result = await response.json();

            if (result.success) {
                alert('Termin gelöscht.');
                loadTrainingDates();
                window.skyrunApp.generateRunDates();
            } else {
                alert('Fehler: ' + result.message);
            }
        } catch (error) {
            alert('Fehler beim Löschen.');
        }
    }
    
    // Passwort ändern
    async function changePassword() {
        if (!adminAuthenticated) return;
        const currentPassword = document.getElementById('current-password').value;
        const newPassword = document.getElementById('new-password').value;
        const confirmPassword = document.getElementById('confirm-password').value;

        if (!currentPassword || !newPassword || newPassword !== confirmPassword || newPassword.length < 8) {
            alert('Passwörter müssen übereinstimmen und mind. 8 Zeichen haben.');
            return;
        }

        const formData = new FormData();
        formData.append('action', 'changePassword');
        formData.append('currentPassword', currentPassword);
        formData.append('newPassword', newPassword);

        const changeButton = document.getElementById('change-password-btn');
        changeButton.disabled = true;
        try {
            const response = await fetch(API_URL, { method: 'POST', body: formData });
            if (!response.ok) {
                if (response.status === 401) handleAdminLogout();
                throw new Error('HTTP Fehler');
            }
            const result = await response.json();

            if (result.success) {
                alert(result.message);
                document.getElementById('current-password').value = '';
                document.getElementById('new-password').value = '';
                document.getElementById('confirm-password').value = '';
            } else {
                alert('Fehler: ' + result.message);
            }
        } catch (error) {
            alert('Passwortänderungsfehler.');
        } finally {
            changeButton.disabled = false;
        }
    }
    
    // Event-Listener einrichten
    if (adminLoginBtn) adminLoginBtn.addEventListener('click', handleAdminLogin);
    if (adminUsernameInput && adminPasswordInput) {
        const loginOnEnter = e => { if (e.key === 'Enter') { e.preventDefault(); adminLoginBtn.click(); } };
        adminUsernameInput.addEventListener('keypress', loginOnEnter);
        adminPasswordInput.addEventListener('keypress', loginOnEnter);
    }
    
    tabButtons.forEach(button => button.addEventListener('click', handleTabSwitch));
    
    if (adminDateSelect) adminDateSelect.addEventListener('change', updateParticipantsList);
    if (waitlistDateSelect) waitlistDateSelect.addEventListener('change', updateWaitlistTable);
    
    if (exportCsvBtn) exportCsvBtn.addEventListener('click', exportAsCSV);
    if (exportJsonBtn) exportJsonBtn.addEventListener('click', exportAsJSON);
    if (importBtn) importBtn.addEventListener('click', importData);
    
    if (settingsForm) settingsForm.addEventListener('submit', e => { e.preventDefault(); saveSettings(); });
    if (passwordForm) passwordForm.addEventListener('submit', e => { e.preventDefault(); changePassword(); });

    // Termin-Verwaltung Event-Listener
    const addDateBtn = document.getElementById('add-date-btn');
    if (addDateBtn) addDateBtn.addEventListener('click', addTrainingDate);

    // Gipfelbuch Building-Filter
    const peakbookBuildingFilter = document.getElementById('peakbook-building-filter');
    if (peakbookBuildingFilter) peakbookBuildingFilter.addEventListener('change', updatePeakBook);
})();
