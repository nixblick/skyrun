# TODO - Skyrun Training

## KRITISCH - Sicherheit

- [ ] **Passwörter rotieren** — Alte Passwörter aus Git-Historie sind kompromittiert. Bei Goneo ändern: DB-Passwort, SMTP-Passwort (`skyrun@mein-computerfreund.de`), dann `./rotate_credentials.sh` ausführen.

## VERBESSERUNGEN

- [ ] **Rate Limiting für Registrierung** — Aktuell nur CAPTCHA, kein IP-basiertes Rate Limiting
