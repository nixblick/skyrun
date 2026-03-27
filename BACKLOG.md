# BACKLOG - Skyrun Training

Ausgelagert aus TODO.md am 2026-03-27. Nice-to-have, keine 30-Tage-Priorität.

## Verbesserungen

- [ ] **shared/policies Templates befüllen** — datenschutz.html + impressum.html als Vorlagen in shared/policies/
- [ ] **Sticky Header** — kompakterer Header beim Scrollen, bessere Mobile-UX
- [ ] **prefers-color-scheme Support** — Dark Theme automatisch
- [ ] **Google Search Console einrichten** — sitemap.xml einreichen für schnellere Indexierung. Anleitung: search.google.com/search-console → Property `https://www.mein-computerfreund.de/` hinzufügen → Verifizierung per HTML-Datei → Sitemaps → `sitemap.xml` einreichen.

## Referenz: Public-Repo-Regeln

Dieses Repo ist PUBLIC. Bei JEDER Änderung prüfen:

### Niemals committen:
- Passwörter, Tokens, API-Keys, DB-Credentials
- config.php, .env, .backup_config (stehen in .gitignore)
- SQL-Dumps, Log-Dateien (stehen in .gitignore)
- Echte Namen, E-Mails, Telefonnummern von Nutzern
- Server-Pfade, interne IPs, Hosting-Details (soweit vermeidbar)
- TODO.md, NOTES.md (stehen in .gitignore — nur lokal!)

### Vor jedem Commit checken:
- `git diff --staged` lesen — stehen da Secrets drin?
- Neue Dateien: gehören die in .gitignore?
- Fehlermeldungen/Logs: enthalten die Credentials?
- Workflow-Dateien: werden Secrets in Klartext geloggt (`echo`, `cat`)?

### Im Zweifelsfall:
- Credentials IMMER in config.php (gitignored), NIE im Code
- Tokens als GitHub Secrets, NIE in Workflow-Dateien
- Interne Notizen in TODO.md (gitignored), NICHT in CHANGELOG.md
- Wenn sensible Info committet wurde: sofort History bereinigen + rotieren
