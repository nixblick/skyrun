document.addEventListener('DOMContentLoaded', function() {
    const API_URL = 'api.php';

    const form = document.getElementById('skyrun-form');
    const runDateSelect = document.getElementById('run-date');
    const personCountInput = document.getElementById('person-count');
    const registrationStatus = document.getElementById('registration-status');
    const currentRegistrationsEl = document.getElementById('current-registrations');
    const maxRegistrationsEl = document.getElementById('max-registrations');
    const currentWaitlistEl = document.getElementById('current-waitlist');
    const daysToRunEl = document.getElementById('days-to-run');
    const adminLink = document.getElementById('show-admin');
    const adminPanel = document.querySelector('.admin-panel');
    const adminUsernameInput = document.getElementById('admin-username');
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
    const settingsForm = document.getElementById('settings-form');
    const maxParticipantsInput = document.getElementById('max-participants');
    const runDaySelect = document.getElementById('run-day');
    const runTimeInput = document.getElementById('run-time');
    const passwordForm = document.getElementById('password-form');

    let adminAuthenticated = false;
    let config = { maxParticipants: 25, runDay: 4, runTime: '19:00' };

    init();

    async function init() {
        try {
            await loadConfig();
            generateRunDates();
            updateStatistics();
            setupEventListeners();
        } catch (error) {
            console.error('Initialisierungsfehler:', error);
            showStatus('Fehler beim Laden der Seite.', 'error');
        }
    }

    async function loadConfig() {
        const response = await fetch(`${API_URL}?action=getConfig`);
        if (!response.ok) throw new Error('HTTP Fehler');
        const result = await response.json();
        if (result.success && result.config) {
            config.maxParticipants = parseInt(result.config.max_participants) || 25;
            config.runDay = parseInt(result.config.run_day) || 4;
            config.runTime = result.config.run_time || '19:00';
            if (maxRegistrationsEl) maxRegistrationsEl.textContent = config.maxParticipants;
            if (maxParticipantsInput) maxParticipantsInput.value = config.maxParticipants;
            if (runDaySelect) runDaySelect.value = config.runDay;
            if (runTimeInput) runTimeInput.value = config.runTime;
        }
    }

    function generateRunDates() {
        const now = new Date();
        let nextRunDay = new Date(now);
        const daysToAdd = (config.runDay + 7 - now.getDay()) % 7;
        nextRunDay.setDate(now.getDate() + daysToAdd);

        const [runHours, runMinutes] = config.runTime.split(':').map(Number);
        const runDateTimeToday = new Date(now);
        runDateTimeToday.setHours(runHours, runMinutes, 0, 0);

        if (daysToAdd === 0 && now >= runDateTimeToday) nextRunDay.setDate(nextRunDay.getDate() + 7);

        [runDateSelect, adminDateSelect, waitlistDateSelect, exportDateSelect].forEach(select => {
            if (select) select.innerHTML = '';
        });

        for (let i = 0; i < 4; i++) {
            const runDate = new Date(nextRunDay);
            runDate.setDate(nextRunDay.getDate() + (i * 7));
            const dateStr = formatDate(runDate);
            const dateValue = runDate.toISOString().split('T')[0];
            const option = document.createElement('option');
            option.value = dateValue;
            option.textContent = dateStr;
            [runDateSelect, adminDateSelect, waitlistDateSelect, exportDateSelect].forEach(select => {
                if (select) select.appendChild(option.cloneNode(true));
            });
        }

        if (daysToRunEl) {
            const diffTime = nextRunDay.setHours(runHours, runMinutes, 0, 0) - now.getTime();
            daysToRunEl.textContent = Math.max(0, Math.ceil(diffTime / (1000 * 60 * 60 * 24)));
        }
    }

    function formatDate(date) {
        return date.toLocaleDateString('de-DE', { weekday: 'long', day: '2-digit', month: '2-digit', year: 'numeric' });
    }

    function setupEventListeners() {
        if (form) form.addEventListener('submit', handleRegistration);
        if (adminLink) adminLink.addEventListener('click', e => {
            e.preventDefault();
            adminPanel.classList.toggle('hidden');
            if (!adminPanel.classList.contains('hidden')) window.scrollTo({ top: adminPanel.offsetTop, behavior: 'smooth' });
        });
        if (adminLoginBtn) adminLoginBtn.addEventListener('click', handleAdminLogin);
        tabButtons.forEach(button => button.addEventListener('click', handleTabSwitch));
        if (adminDateSelect) adminDateSelect.addEventListener('change', updateParticipantsList);
        if (waitlistDateSelect) waitlistDateSelect.addEventListener('change', updateWaitlistTable);
        if (runDateSelect) runDateSelect.addEventListener('change', updateStatistics);
        if (exportCsvBtn) exportCsvBtn.addEventListener('click', exportAsCSV);
        if (exportJsonBtn) exportJsonBtn.addEventListener('click', exportAsJSON);
        if (importBtn) importBtn.addEventListener('click', importData);
        if (settingsForm) settingsForm.addEventListener('submit', e => { e.preventDefault(); saveSettings(); });
        if (passwordForm) passwordForm.addEventListener('submit', e => { e.preventDefault(); changePassword(); });
        if (closeModalBtn) closeModalBtn.addEventListener('click', () => confirmationModal.classList.add('hidden'));
        if (closeModalX) closeModalX.addEventListener('click', () => confirmationModal.classList.add('hidden'));
        if (confirmationModal) confirmationModal.addEventListener('click', e => {
            if (e.target === confirmationModal) confirmationModal.classList.add('hidden');
        });
        if (adminUsernameInput && adminPasswordInput) {
            const loginOnEnter = e => { if (e.key === 'Enter') { e.preventDefault(); adminLoginBtn.click(); } };
            adminUsernameInput.addEventListener('keypress', loginOnEnter);
            adminPasswordInput.addEventListener('keypress', loginOnEnter);
        }
    }

    function handleTabSwitch() {
        const tabName = this.dataset.tab;
        tabButtons.forEach(btn => btn.classList.remove('active'));
        tabContents.forEach(content => content.classList.remove('active'));
        this.classList.add('active');
        document.getElementById(`${tabName}-tab`).classList.add('active');
        if (adminAuthenticated) {
            if (tabName === 'participants') updateParticipantsList();
            else if (tabName === 'waitlist') updateWaitlistTable();
        }
    }

    async function handleRegistration(e) {
        e.preventDefault();
        const name = document.getElementById('name').value.trim();
        const email = document.getElementById('email').value.trim();
        const phone = document.getElementById('phone').value.trim();
        const personCount = parseInt(personCountInput.value);
        const date = runDateSelect.value;
        const acceptWaitlist = document.getElementById('waitlist').checked;

        if (!name || !email || !date || isNaN(personCount) || personCount < 1 || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
            showStatus('Bitte alle Pflichtfelder korrekt ausfüllen.', 'error');
            return;
        }

        const formData = new FormData();
        formData.append('action', 'register');
        formData.append('name', name);
        formData.append('email', email);
        formData.append('phone', phone);
        formData.append('date', date);
        formData.append('acceptWaitlist', acceptWaitlist);
        formData.append('personCount', personCount);

        const submitButton = document.getElementById('submit-btn');
        submitButton.disabled = true;

        try {
            const response = await fetch(API_URL, { method: 'POST', body: formData });
            if (!response.ok) throw new Error('HTTP Fehler');
            const result = await response.json();

            if (result.success) {
                form.reset();
                personCountInput.value = 1;
                updateStatistics();
                const personText = personCount === 1 ? 'Person' : 'Personen';
                const formattedDate = formatDate(new Date(date + 'T00:00:00'));
                confirmationMessage.textContent = result.isWaitlisted
                    ? `Sie wurden mit ${personCount} ${personText} auf die Warteliste für ${formattedDate} gesetzt.`
                    : `Ihre Anmeldung für ${personCount} ${personText} am ${formattedDate} wurde registriert.`;
                confirmationModal.classList.remove('hidden');
            } else {
                showStatus(result.message || 'Fehler bei der Anmeldung.', 'error');
            }
        } catch (error) {
            showStatus('Netzwerkfehler.', 'error');
            console.error('Anmeldefehler:', error);
        } finally {
            submitButton.disabled = false;
        }
    }

    function showStatus(message, type) {
        registrationStatus.textContent = message;
        registrationStatus.className = `registration-status ${type}`;
        registrationStatus.classList.remove('hidden');
    }

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
                // Logout-Button hinzufügen
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

    async function updateParticipantsList() {
        if (!adminAuthenticated) return;
        const selectedDate = adminDateSelect.value;
        const participantsListBody = document.getElementById('participants-list');
        participantsListBody.innerHTML = '<tr><td colspan="5">Lade Teilnehmer...</td></tr>';

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
                    participantsListBody.innerHTML = '<tr><td colspan="5">Keine Anmeldungen</td></tr>';
                } else {
                    result.participants.forEach(p => {
                        const row = participantsListBody.insertRow();
                        row.innerHTML = `
                            <td>${escapeHTML(p.name)}</td>
                            <td>${escapeHTML(p.email)}</td>
                            <td>${escapeHTML(p.phone || '-')}</td>
                            <td>${p.personCount}</td>
                            <td><button class="action-btn remove-btn" data-id="${p.id}" data-date="${selectedDate}">Entfernen</button></td>
                        `;
                        row.querySelector('.remove-btn').addEventListener('click', () => removeParticipant(p.id, selectedDate));
                    });
                }
                config.maxParticipants = result.maxParticipants;
                maxParticipantsInput.value = config.maxParticipants;
                maxRegistrationsEl.textContent = config.maxParticipants;
            } else {
                participantsListBody.innerHTML = `<tr><td colspan="5">Fehler: ${result.message}</td></tr>`;
            }
        } catch (error) {
            participantsListBody.innerHTML = '<tr><td colspan="5">Netzwerkfehler</td></tr>';
            console.error('Fehler:', error);
        }
    }

    async function updateWaitlistTable() {
        if (!adminAuthenticated) return;
        const selectedDate = waitlistDateSelect.value;
        const waitlistTableBody = document.getElementById('waitlist-list');
        waitlistTableBody.innerHTML = '<tr><td colspan="5">Lade Warteliste...</td></tr>';

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
                    waitlistTableBody.innerHTML = '<tr><td colspan="5">Keine Wartenden</td></tr>';
                } else {
                    result.waitlist.forEach(p => {
                        const row = waitlistTableBody.insertRow();
                        row.innerHTML = `
                            <td>${escapeHTML(p.name)}</td>
                            <td>${escapeHTML(p.email)}</td>
                            <td>${escapeHTML(p.phone || '-')}</td>
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
                waitlistTableBody.innerHTML = `<tr><td colspan="5">Fehler: ${result.message}</td></tr>`;
            }
        } catch (error) {
            waitlistTableBody.innerHTML = '<tr><td colspan="5">Netzwerkfehler</td></tr>';
        }
    }

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
                updateStatistics();
            } else {
                alert('Fehler: ' + result.message);
            }
        } catch (error) {
            alert('Entfernungsfehler.');
        }
    }

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
                updateStatistics();
            } else {
                alert('Fehler: ' + result.message);
            }
        } catch (error) {
            alert('Hochstufungsfehler.');
        }
    }

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
            let csvContent = '"Name","E-Mail","Telefon","Personen","Status","Anmeldezeit"\n';
            allRegistrations.forEach(reg => {
                csvContent += `"${reg.name.replace(/"/g, '""')}","${reg.email.replace(/"/g, '""')}","${(reg.phone || '').replace(/"/g, '""')}","${reg.personCount}","${reg.waitlisted ? 'Warteliste' : 'Angemeldet'}","${reg.registrationTime}"\n`;
            });
            const blob = new Blob([`\uFEFF${csvContent}`], { type: 'text/csv;charset=utf-8;' });
            downloadFile(blob, `skyrun_${selectedDate}.csv`);
        }
    }

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
                    updateStatistics();
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

    async function saveSettings() {
        if (!adminAuthenticated) return;
        const maxParticipants = parseInt(maxParticipantsInput.value);
        const runDay = parseInt(runDaySelect.value);
        const runTime = runTimeInput.value;

        if (isNaN(maxParticipants) || maxParticipants < 1 || isNaN(runDay) || !/^\d{2}:\d{2}$/.test(runTime)) {
            alert('Ungültige Werte.');
            return;
        }

        const formData = new FormData();
        formData.append('action', 'updateConfig');
        formData.append('maxParticipants', maxParticipants);
        formData.append('runDay', runDay);
        formData.append('runTime', runTime);

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
                config.maxParticipants = maxParticipants;
                config.runDay = runDay;
                config.runTime = runTime;
                maxRegistrationsEl.textContent = maxParticipants;
                generateRunDates();
                updateStatistics();
            } else {
                alert('Fehler: ' + result.message);
            }
        } catch (error) {
            alert('Speicherfehler.');
        } finally {
            saveButton.disabled = false;
        }
    }

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

    function downloadFile(blob, filename) {
        const url = URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.download = filename;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        URL.revokeObjectURL(url);
    }

    function escapeHTML(str) {
        if (typeof str !== 'string') return str;
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }
});