document.addEventListener('DOMContentLoaded', function() {
    const API_URL = 'api.php';

    // DOM-Elemente
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
    let tempAdminUser = '';
    let tempAdminPass = '';

    let config = {
        maxParticipants: 25,
        runDay: 4,
        runTime: '19:00'
    };

    init();

    async function init() {
        try {
            await loadConfig();
            generateRunDates();
            updateStatistics();
            setupEventListeners();
        } catch (error) {
            console.error('Fehler bei der Initialisierung:', error);
            showStatus('Fehler beim Laden der Seite. Bitte versuchen Sie es später erneut.', 'error');
        }
    }

    async function loadConfig() {
        try {
            const response = await fetch(`${API_URL}?action=getConfig`);
            if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
            const result = await response.json();

            if (result.success && result.config) {
                config.maxParticipants = parseInt(result.config.max_participants) || config.maxParticipants;
                config.runDay = parseInt(result.config.run_day) || 4;
                config.runTime = result.config.run_time || config.runTime;

                if (maxRegistrationsEl) maxRegistrationsEl.textContent = config.maxParticipants;
                if (maxParticipantsInput) maxParticipantsInput.value = config.maxParticipants;
                if (runDaySelect) runDaySelect.value = config.runDay;
                if (runTimeInput) runTimeInput.value = config.runTime;
            } else {
                showStatus('Fehler beim Laden der Konfiguration.', 'warning');
            }
        } catch (error) {
            console.error('Netzwerkfehler beim Laden der Konfiguration:', error);
            showStatus('Netzwerkfehler beim Laden der Konfiguration.', 'error');
        }
    }

    function generateRunDates() {
        const now = new Date();
        let nextRunDay = new Date(now);
        const currentDay = now.getDay();
        const daysToAdd = (config.runDay + 7 - currentDay) % 7;
        nextRunDay.setDate(now.getDate() + daysToAdd);

        const [runHours, runMinutes] = config.runTime.split(':').map(Number);
        const runDateTimeToday = new Date(now);
        runDateTimeToday.setHours(runHours, runMinutes, 0, 0);

        if (daysToAdd === 0 && now.getTime() >= runDateTimeToday.getTime()) {
            nextRunDay.setDate(nextRunDay.getDate() + 7);
        }

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
        return date.toLocaleDateString('de-DE', {
            weekday: 'long',
            day: '2-digit',
            month: '2-digit',
            year: 'numeric'
        });
    }

    function setupEventListeners() {
        if (form) form.addEventListener('submit', handleRegistration);
        if (adminLink) adminLink.addEventListener('click', e => {
            e.preventDefault();
            adminPanel?.classList.toggle('hidden');
            if (!adminPanel?.classList.contains('hidden')) {
                window.scrollTo({ top: adminPanel.offsetTop, behavior: 'smooth' });
            }
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
        if (closeModalBtn) closeModalBtn.addEventListener('click', () => confirmationModal?.classList.add('hidden'));
        if (closeModalX) closeModalX.addEventListener('click', () => confirmationModal?.classList.add('hidden'));
        if (confirmationModal) confirmationModal.addEventListener('click', e => {
            if (e.target === confirmationModal) confirmationModal.classList.add('hidden');
        });
        if (adminUsernameInput && adminPasswordInput) {
            const loginOnEnter = e => { if (e.key === 'Enter') { e.preventDefault(); adminLoginBtn?.click(); } };
            adminUsernameInput.addEventListener('keypress', loginOnEnter);
            adminPasswordInput.addEventListener('keypress', loginOnEnter);
        }
    }

    function handleTabSwitch() {
        const tabName = this.dataset.tab;
        tabButtons.forEach(btn => btn.classList.remove('active'));
        tabContents.forEach(content => content.classList.remove('active'));
        this.classList.add('active');
        document.getElementById(`${tabName}-tab`)?.classList.add('active');
        if (adminAuthenticated) {
            if (tabName === 'participants') updateParticipantsList();
            else if (tabName === 'waitlist') updateWaitlistTable();
        }
    }

    async function handleRegistration(e) {
        e.preventDefault();
        const name = document.getElementById('name')?.value.trim() || '';
        const email = document.getElementById('email')?.value.trim() || '';
        const phone = document.getElementById('phone')?.value.trim() || '';
        const personCount = parseInt(personCountInput?.value || '1');
        const date = runDateSelect?.value || '';
        const acceptWaitlist = document.getElementById('waitlist')?.checked || false;

        if (!name || !email || !date || isNaN(personCount) || personCount < 1) {
            showStatus('Bitte alle Pflichtfelder korrekt ausfüllen.', 'error');
            return;
        }
        if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
            showStatus('Bitte geben Sie eine gültige E-Mail-Adresse ein.', 'error');
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
        if (submitButton) submitButton.disabled = true;

        try {
            const response = await fetch(API_URL, { method: 'POST', body: formData });
            if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
            const result = await response.json();

            if (result.success) {
                form?.reset();
                if (personCountInput) personCountInput.value = 1;
                updateStatistics();
                const personText = personCount === 1 ? 'Person' : 'Personen';
                const formattedDate = formatDate(new Date(date + 'T00:00:00'));
                const message = result.isWaitlisted
                    ? `Sie wurden mit ${personCount} ${personText} auf die Warteliste für ${formattedDate} gesetzt.`
                    : `Ihre Anmeldung für ${personCount} ${personText} am ${formattedDate} wurde registriert.`;
                if (confirmationMessage) confirmationMessage.textContent = message;
                confirmationModal?.classList.remove('hidden');
                closeModalBtn?.focus();
            } else {
                showStatus(result.message || 'Ein unbekannter Fehler ist aufgetreten.', 'error');
            }
        } catch (error) {
            showStatus('Netzwerkfehler. Bitte später erneut versuchen.', 'error');
            console.error('Fehler bei der Anmeldung:', error);
        } finally {
            if (submitButton) submitButton.disabled = false;
        }
    }

    function showStatus(message, type) {
        if (!registrationStatus) return;
        registrationStatus.textContent = message;
        registrationStatus.className = `registration-status ${type}`;
        registrationStatus.classList.remove('hidden');
    }

    async function updateStatistics() {
        if (!runDateSelect || !currentRegistrationsEl || !maxRegistrationsEl || !currentWaitlistEl) return;
        const selectedDate = runDateSelect.value;
        if (!selectedDate) return;

        try {
            const response = await fetch(`${API_URL}?action=getStats&date=${selectedDate}`);
            if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
            const result = await response.json();

            if (result.success) {
                currentRegistrationsEl.textContent = result.participants;
                maxRegistrationsEl.textContent = result.maxParticipants;
                currentWaitlistEl.textContent = result.waitlist;
                config.maxParticipants = result.maxParticipants;
            } else {
                currentRegistrationsEl.textContent = '?';
                currentWaitlistEl.textContent = '?';
            }
        } catch (error) {
            console.error('Netzwerkfehler beim Abrufen der Statistiken:', error);
            currentRegistrationsEl.textContent = 'E';
            currentWaitlistEl.textContent = 'E';
        }
    }

    async function handleAdminLogin() {
        if (!adminUsernameInput || !adminPasswordInput || !adminLoginBtn || !adminContent) return;
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

        console.log('Admin-Login: Sende Daten:', { username, password });

        try {
            const response = await fetch(API_URL, { method: 'POST', body: formData });
            if (response.status === 401) {
                const errorResult = await response.json().catch(() => ({ message: 'Falscher Benutzername oder Passwort!' }));
                console.log('Admin-Login: Fehler 401:', errorResult);
                alert(errorResult.message);
                adminAuthenticated = false;
                tempAdminUser = '';
                tempAdminPass = '';
                if (adminPasswordInput) adminPasswordInput.value = '';
                if (adminPasswordInput) adminPasswordInput.focus();
                return;
            }
            if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);

            const result = await response.json();
            console.log('Admin-Login: Antwort:', result);

            if (result.success) {
                adminAuthenticated = true;
                tempAdminUser = username;
                tempAdminPass = password;
                console.log('Admin-Login erfolgreich: Credentials gespeichert:', { tempAdminUser, tempAdminPass });
                if (adminContent) adminContent.classList.remove('hidden');
                if (adminUsernameInput) adminUsernameInput.value = '';
                if (adminPasswordInput) adminPasswordInput.value = '';
                document.querySelector('.admin-panel .form-group')?.classList.add('hidden');
                document.querySelector('.tab-btn[data-tab="participants"]')?.click();
            } else {
                console.log('Admin-Login: Fehlgeschlagen:', result);
                alert(result.message || 'Falscher Benutzername oder Passwort!');
                adminAuthenticated = false;
                tempAdminUser = '';
                tempAdminPass = '';
                if (adminPasswordInput) adminPasswordInput.value = '';
                if (adminPasswordInput) adminPasswordInput.focus();
            }
        } catch (error) {
            console.error('Admin-Login: Fehler:', error);
            alert('Ein Fehler ist beim Login aufgetreten. Bitte später erneut versuchen.');
            adminAuthenticated = false;
            tempAdminUser = '';
            tempAdminPass = '';
        } finally {
            if (adminLoginBtn) adminLoginBtn.disabled = false;
        }
    }

    async function updateParticipantsList() {
        if (!adminAuthenticated || !adminDateSelect) return;
        const selectedDate = adminDateSelect.value;
        const participantsListBody = document.getElementById('participants-list');
        if (!participantsListBody) return;

        participantsListBody.innerHTML = '<tr><td colspan="5">Lade Teilnehmer...</td></tr>';
        const formData = new FormData();
        formData.append('action', 'getParticipants');
        formData.append('username', tempAdminUser);
        formData.append('password', tempAdminPass);
        formData.append('date', selectedDate);

        console.log('updateParticipantsList: Sende Daten:', {
            action: 'getParticipants',
            username: tempAdminUser,
            password: tempAdminPass,
            date: selectedDate
        });

        try {
            const response = await fetch(API_URL, { method: 'POST', body: formData });
            if (!response.ok) {
                const errorText = await response.text();
                console.error('updateParticipantsList: HTTP Fehler:', response.status, errorText);
                throw handleAuthError(response.status);
            }
            const result = await response.json();
            console.log('updateParticipantsList: Antwort:', result);

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
                        row.querySelector('.remove-btn')?.addEventListener('click', () => removeParticipant(p.id, selectedDate));
                    });
                }
                if (result.maxParticipants) {
                    config.maxParticipants = parseInt(result.maxParticipants);
                    if (maxParticipantsInput) maxParticipantsInput.value = config.maxParticipants;
                    if (maxRegistrationsEl) maxRegistrationsEl.textContent = config.maxParticipants;
                }
            } else {
                console.log('updateParticipantsList: Fehler in Antwort:', result);
                participantsListBody.innerHTML = `<tr><td colspan="5">Fehler: ${result.message || 'Unbekannt'}</td></tr>`;
                if (result.message === 'Nicht autorisiert.') handleLogout();
            }
        } catch (error) {
            console.error('updateParticipantsList: Fehler:', error);
            participantsListBody.innerHTML = '<tr><td colspan="5">Netzwerkfehler</td></tr>';
        }
    }

    async function updateWaitlistTable() {
        if (!adminAuthenticated || !waitlistDateSelect) return;
        const selectedDate = waitlistDateSelect.value;
        const waitlistTableBody = document.getElementById('waitlist-list');
        if (!waitlistTableBody) return;

        waitlistTableBody.innerHTML = '<tr><td colspan="5">Lade Warteliste...</td></tr>';
        const formData = new FormData();
        formData.append('action', 'getParticipants');
        formData.append('username', tempAdminUser);
        formData.append('password', tempAdminPass);
        formData.append('date', selectedDate);

        console.log('updateWaitlistTable: Sende Daten:', {
            action: 'getParticipants',
            username: tempAdminUser,
            password: tempAdminPass,
            date: selectedDate
        });

        try {
            const response = await fetch(API_URL, { method: 'POST', body: formData });
            if (!response.ok) throw handleAuthError(response.status);
            const result = await response.json();
            console.log('updateWaitlistTable: Antwort:', result);

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
                        row.querySelector('.promote-btn')?.addEventListener('click', () => promoteFromWaitlist(p.id, selectedDate));
                        row.querySelector('.remove-btn')?.addEventListener('click', () => removeParticipant(p.id, selectedDate));
                    });
                }
                if (result.maxParticipants) {
                    config.maxParticipants = parseInt(result.maxParticipants);
                    if (maxParticipantsInput) maxParticipantsInput.value = config.maxParticipants;
                    if (maxRegistrationsEl) maxRegistrationsEl.textContent = config.maxParticipants;
                }
            } else {
                waitlistTableBody.innerHTML = `<tr><td colspan="5">Fehler: ${result.message || 'Unbekannt'}</td></tr>`;
                if (result.message === 'Nicht autorisiert.') handleLogout();
            }
        } catch (error) {
            console.error('Fehler beim Abrufen der Warteliste:', error);
            waitlistTableBody.innerHTML = '<tr><td colspan="5">Netzwerkfehler</td></tr>';
        }
    }

    async function removeParticipant(id, date) {
        if (!adminAuthenticated || !confirm(`Möchten Sie diese Registrierung (ID: ${id}) entfernen?`)) return;

        const formData = new FormData();
        formData.append('action', 'removeParticipant');
        formData.append('username', tempAdminUser);
        formData.append('password', tempAdminPass);
        formData.append('id', id);
        formData.append('date', date);

        try {
            const response = await fetch(API_URL, { method: 'POST', body: formData });
            if (!response.ok) throw handleAuthError(response.status);
            const result = await response.json();

            if (result.success) {
                alert('Teilnehmer erfolgreich entfernt.');
                updateParticipantsList();
                updateWaitlistTable();
                updateStatistics();
            } else {
                alert('Fehler beim Entfernen: ' + (result.message || 'Unbekannter Fehler'));
                if (result.message === 'Nicht autorisiert.') handleLogout();
            }
        } catch (error) {
            alert('Fehler beim Entfernen. Bitte später erneut versuchen.');
            console.error('Fehler beim Entfernen:', error);
        }
    }

    async function promoteFromWaitlist(id, date) {
        if (!adminAuthenticated || !confirm(`Möchten Sie diese Person/Gruppe (ID: ${id}) hochstufen?`)) return;

        const formData = new FormData();
        formData.append('action', 'promoteFromWaitlist');
        formData.append('username', tempAdminUser);
        formData.append('password', tempAdminPass);
        formData.append('id', id);
        formData.append('date', date);

        try {
            const response = await fetch(API_URL, { method: 'POST', body: formData });
            if (!response.ok) throw handleAuthError(response.status);
            const result = await response.json();

            if (result.success) {
                alert('Teilnehmer erfolgreich hochgestuft.');
                updateParticipantsList();
                updateWaitlistTable();
                updateStatistics();
            } else {
                alert('Fehler beim Hochstufen: ' + (result.message || 'Unbekannter Fehler'));
                if (result.message === 'Nicht autorisiert.') handleLogout();
            }
        } catch (error) {
            alert('Fehler beim Hochstufen. Bitte später erneut versuchen.');
            console.error('Fehler beim Hochstufen:', error);
        }
    }

    async function exportAsCSV() {
        if (!adminAuthenticated || !exportDateSelect) return;
        const selectedDate = exportDateSelect.value;

        const formData = new FormData();
        formData.append('action', 'getParticipants');
        formData.append('username', tempAdminUser);
        formData.append('password', tempAdminPass);
        formData.append('date', selectedDate);

        try {
            const response = await fetch(API_URL, { method: 'POST', body: formData });
            if (!response.ok) throw handleAuthError(response.status);
            const result = await response.json();

            if (result.success) {
                const allRegistrations = [...result.participants, ...result.waitlist];
                if (allRegistrations.length === 0) {
                    alert('Keine Daten zum Exportieren.');
                    return;
                }

                let csvContent = '"Name","E-Mail","Telefon","Personen","Status","Anmeldezeit"\n';
                allRegistrations.forEach(reg => {
                    const row = `"${reg.name.replace(/"/g, '""')}","${reg.email.replace(/"/g, '""')}","${(reg.phone || '').replace(/"/g, '""')}","${reg.personCount}","${reg.waitlisted ? 'Warteliste' : 'Angemeldet'}","${reg.registrationTime}"\n`;
                    csvContent += row;
                });

                const blob = new Blob([`\uFEFF${csvContent}`], { type: 'text/csv;charset=utf-8;' });
                downloadFile(blob, `skyrun_${selectedDate}.csv`);
            } else {
                alert('Fehler beim Abrufen der Exportdaten: ' + (result.message || 'Unbekannter Fehler'));
                if (result.message === 'Nicht autorisiert.') handleLogout();
            }
        } catch (error) {
            alert('Fehler beim Exportieren. Bitte später erneut versuchen.');
            console.error('Fehler beim Exportieren (CSV):', error);
        }
    }

    async function exportAsJSON() {
        if (!adminAuthenticated) return;

        const formData = new FormData();
        formData.append('action', 'exportData');
        formData.append('username', tempAdminUser);
        formData.append('password', tempAdminPass);

        try {
            const response = await fetch(API_URL, { method: 'POST', body: formData });
            if (!response.ok) throw handleAuthError(response.status);
            const result = await response.json();

            if (result.success) {
                if (Object.keys(result.data).length === 0) {
                    alert('Keine Daten zum Exportieren.');
                    return;
                }
                const jsonData = JSON.stringify(result.data, null, 2);
                const blob = new Blob([jsonData], { type: 'application/json;charset=utf-8;' });
                const timestamp = new Date().toISOString().replace(/[:.]/g, '-');
                downloadFile(blob, `skyrun_export_${timestamp}.json`);
            } else {
                alert('Fehler beim Abrufen der Exportdaten: ' + (result.message || 'Unbekannter Fehler'));
                if (result.message === 'Nicht autorisiert.') handleLogout();
            }
        } catch (error) {
            alert('Fehler beim Exportieren. Bitte später erneut versuchen.');
            console.error('Fehler beim Exportieren (JSON):', error);
        }
    }

    async function importData() {
        if (!adminAuthenticated || !importJsonInput) return;
        const file = importJsonInput.files?.[0];
        if (!file || file.type !== 'application/json' || !confirm(`Daten aus '${file.name}' importieren?`)) return;

        const reader = new FileReader();
        reader.onload = async e => {
            const jsonData = e.target?.result;
            if (!jsonData || typeof jsonData !== 'string') {
                alert('Fehler beim Lesen der Datei.');
                return;
            }

            if (importBtn) importBtn.disabled = true;
            const formData = new FormData();
            formData.append('action', 'importData');
            formData.append('username', tempAdminUser);
            formData.append('password', tempAdminPass);
            formData.append('data', jsonData);

            try {
                const response = await fetch(API_URL, { method: 'POST', body: formData });
                if (!response.ok) throw handleAuthError(response.status);
                const result = await response.json();

                if (result.success) {
                    alert(result.message || 'Daten erfolgreich importiert!');
                    if (importJsonInput) importJsonInput.value = '';
                    updateParticipantsList();
                    updateWaitlistTable();
                    updateStatistics();
                } else {
                    alert('Fehler beim Importieren: ' + (result.message || 'Unbekannter Fehler'));
                    if (result.message === 'Nicht autorisiert.') handleLogout();
                }
            } catch (error) {
                alert('Fehler beim Importieren. Bitte später erneut versuchen.');
                console.error('Fehler beim Importieren:', error);
            } finally {
                if (importBtn) importBtn.disabled = false;
            }
        };
        reader.onerror = () => alert('Fehler beim Lesen der Datei.');
        reader.readAsText(file);
    }

    async function saveSettings() {
        if (!adminAuthenticated || !maxParticipantsInput || !runDaySelect || !runTimeInput) return;

        const maxParticipants = parseInt(maxParticipantsInput.value);
        const runDay = parseInt(runDaySelect.value);
        const runTime = runTimeInput.value;

        if (isNaN(maxParticipants) || maxParticipants < 1 || isNaN(runDay) || runDay < 0 || runDay > 6 || !/^\d{2}:\d{2}$/.test(runTime)) {
            alert('Bitte gültige Werte für Teilnehmerzahl, Wochentag und Uhrzeit eingeben.');
            return;
        }

        const formData = new FormData();
        formData.append('action', 'updateConfig');
        formData.append('username', tempAdminUser);
        formData.append('password', tempAdminPass);
        formData.append('maxParticipants', maxParticipants);
        formData.append('runDay', runDay);
        formData.append('runTime', runTime);

        const saveButton = document.getElementById('save-settings-btn');
        if (saveButton) saveButton.disabled = true;

        try {
            const response = await fetch(API_URL, { method: 'POST', body: formData });
            if (!response.ok) throw handleAuthError(response.status);
            const result = await response.json();

            if (result.success) {
                alert('Einstellungen erfolgreich gespeichert!');
                config.maxParticipants = maxParticipants;
                config.runDay = runDay;
                config.runTime = runTime;
                if (maxRegistrationsEl) maxRegistrationsEl.textContent = maxParticipants;
                generateRunDates();
                updateStatistics();
            } else {
                alert('Fehler beim Speichern: ' + (result.message || 'Unbekannter Fehler'));
                if (result.message === 'Nicht autorisiert.') handleLogout();
            }
        } catch (error) {
            alert('Fehler beim Speichern. Bitte später erneut versuchen.');
            console.error('Fehler beim Speichern:', error);
        } finally {
            if (saveButton) saveButton.disabled = false;
        }
    }

    async function changePassword() {
        if (!adminAuthenticated) return;
        const currentPasswordEl = document.getElementById('current-password');
        const newPasswordEl = document.getElementById('new-password');
        const confirmPasswordEl = document.getElementById('confirm-password');
        if (!currentPasswordEl || !newPasswordEl || !confirmPasswordEl) return;

        const currentPassword = currentPasswordEl.value;
        const newPassword = newPasswordEl.value;
        const confirmPassword = confirmPasswordEl.value;

        if (!currentPassword || !newPassword || !confirmPassword || newPassword !== confirmPassword || newPassword.length < 8) {
            alert('Bitte alle Felder ausfüllen, Passwörter müssen übereinstimmen und mindestens 8 Zeichen lang sein.');
            return;
        }

        const formData = new FormData();
        formData.append('action', 'changePassword');
        formData.append('username', tempAdminUser);
        formData.append('currentPassword', currentPassword);
        formData.append('newPassword', newPassword);

        const changeButton = document.getElementById('change-password-btn');
        if (changeButton) changeButton.disabled = true;

        try {
            const response = await fetch(API_URL, { method: 'POST', body: formData });
            if (!response.ok) throw handleAuthError(response.status);
            const result = await response.json();

            if (result.success) {
                alert(result.message || 'Passwort erfolgreich geändert!');
                tempAdminPass = newPassword;
                currentPasswordEl.value = '';
                newPasswordEl.value = '';
                confirmPasswordEl.value = '';
            } else {
                alert('Fehler: ' + (result.message || 'Unbekannter Fehler'));
                if (result.message === 'Nicht autorisiert.' || result.message === 'Aktuelles Passwort ist falsch.') {
                    currentPasswordEl.value = '';
                    currentPasswordEl.focus();
                    if (result.message === 'Nicht autorisiert.') handleLogout();
                }
            }
        } catch (error) {
            alert('Fehler beim Ändern. Bitte später erneut versuchen.');
            console.error('Fehler beim Ändern des Passworts:', error);
        } finally {
            if (changeButton) changeButton.disabled = false;
        }
    }

    function handleLogout() {
        console.log('handleLogout aufgerufen: Zurücksetzen des Admin-Zugangs');
        adminAuthenticated = false;
        tempAdminUser = '';
        tempAdminPass = '';
        adminContent?.classList.add('hidden');
        document.querySelector('.admin-panel .form-group')?.classList.remove('hidden');
        alert('Sitzung abgelaufen oder nicht autorisiert. Bitte neu einloggen.');
        tabButtons.forEach(btn => btn.classList.remove('active'));
        tabContents.forEach(content => content.classList.remove('active'));
        document.querySelector('.tab-btn[data-tab="participants"]')?.classList.add('active');
        document.getElementById('participants-tab')?.classList.add('active');
    }

    function handleAuthError(status) {
        console.log('handleAuthError: Status:', status);
        if (status === 401 || status === 403) handleLogout();
        return new Error(`HTTP error! status: ${status}`);
    }

    function downloadFile(blob, filename) {
        const url = URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.download = filename;
        link.style.visibility = 'hidden';
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