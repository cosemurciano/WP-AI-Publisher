=== WP AI Publisher ===
Contributors: wp-ai-publisher
Tags: ai, publishing, admin, drafts, wordpress-ai
Requires at least: 7.0
Tested up to: 7.0
Requires PHP: 8.1
Stable tag: 0.2.2
License: GPLv3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Base modulare per la pubblicazione assistita da AI in WordPress.

== Descrizione ==

WP AI Publisher prepara l’infrastruttura del plugin per la futura generazione assistita di articoli, bozze strutturate, media, metadati SEO, link interni, knowledge index, coda job, dry-run, controllo duplicati e pubblicazione assistita.

Il plugin usa esclusivamente il sistema AI di WordPress configurato sul sito. Non gestisce un client OpenAI custom e non salva chiavi API proprie. Include diagnostica difensiva per plugin terzi richiesti o consigliati, senza creare dipendenze rigide.

Questa versione non genera ancora articoli, immagini o embedding. Tutte le future funzioni AI passeranno dall’adapter centrale collegato al sistema AI di WordPress.

== Installazione ==

1. Carica la cartella del plugin in `/wp-content/plugins/` oppure installa lo ZIP da Plugin > Aggiungi plugin > Carica plugin.
2. Attiva WP AI Publisher dalla schermata Plugin.
3. Apri WP AI Publisher > Bacheca.
4. Controlla WP AI Publisher > Coda job.
5. Controlla WP AI Publisher > Impostazioni.
6. Controlla WP AI Publisher > Stato sistema.

== Note sviluppo ==

Durante tutto lo sviluppo, `readme.txt`, `README.md` e versione del plugin devono restare aggiornati. Le voci “Impostazioni” e “Stato sistema” devono restare sempre alla fine del menu del plugin; le altre voci vanno ordinate per importanza d’uso.

Dalla versione 0.2.2 le migrazioni database vengono controllate anche durante il bootstrap del plugin, perché WordPress non riesegue automaticamente l’hook di attivazione dopo un aggiornamento one-click. Questo evita errori quando una nuova pagina admin interroga tabelle create in versioni successive.

== Plugin terzi consigliati ==

WP AI Publisher controlla in modo difensivo la presenza di plugin e integrazioni terze. Il plugin resta attivabile e funzionante anche se queste integrazioni non sono installate, non sono attive o vengono disattivate.

Plugin e integrazioni controllati:

* Git Updater
* Git Remote Updater
* AI Services / WordPress AI
* AI Request Logging
* Connector Approval
* Abilities Explorer
* AIOSEO

Git Updater è consigliato per gli aggiornamenti del plugin da GitHub. AI Services / WordPress AI è il layer AI previsto per le funzioni future di WP AI Publisher. Gli altri plugin sono consigliati per diagnostica, sicurezza, sviluppo e integrazioni SEO future.

La chiave REST di Git Remote Updater deve essere gestita nel pannello Git Updater / Git Remote Updater. WP AI Publisher non salva né replica questa chiave.

== Domande frequenti ==

= Questa versione chiama direttamente OpenAI? =

No. WP AI Publisher usa solo il sistema AI di WordPress configurato sul sito.

= Questa versione genera articoli? =

No. La generazione articoli sarà implementata in una fase successiva.

= Come vengono mostrati i modelli AI disponibili? =

Il plugin prova a leggerli dal sistema AI di WordPress. Se l’integrazione attiva espone i modelli tramite funzioni, client o filtro `wpai_publisher_available_ai_models`, questi compaiono nel menu a tendina delle impostazioni.

== Changelog ==

= 0.2.2 =
* Corretto il flusso di upgrade database: le migrazioni vengono eseguite anche dopo aggiornamento plugin, non solo in attivazione.
* Evitati errori sulla pagina Coda job nei siti aggiornati da versioni precedenti.
* Formalizzata la regola di ordinamento menu: Impostazioni e Stato sistema restano sempre in fondo.
* Aggiornato README.md insieme alla versione plugin.

= 0.2.1 =
* Aggiunto controllo dei plugin terzi richiesti e consigliati.
* Aggiunto box “Plugin terzi e integrazioni” nella Bacheca.
* Migliorata la diagnostica dello Stato sistema.
* Aggiunti controlli difensivi per Git Updater, Git Remote Updater, WordPress AI, AI Request Logging, Connector Approval, Abilities Explorer e AIOSEO.
* Nessuna chiave REST o API viene salvata da WP AI Publisher.

= 0.2.0 =
* Aggiunta fondazione Job Queue.
* Aggiunta tabella database dei job.
* Aggiunta pagina admin Coda job.
* Migliorata diagnostica del sistema AI WordPress.
* Predisposto rilevamento abilità AI WordPress.
* Nessuna generazione automatica ancora attiva.

= 0.1.1 =
* Interfaccia principale tradotta in italiano.
* Uso esclusivo del sistema AI di WordPress.
* Rimosso il fallback OpenAI diretto dalle impostazioni operative.
* Aggiunto menu a tendina per la selezione del modello AI disponibile.
* Aggiornato lo stato sistema con diagnostica WordPress AI.

= 0.1.0 =
* Fondazione iniziale del plugin.
* Bacheca admin, impostazioni e stato sistema.
* Scaffolding tabella log database.
* Stub adapter AI centrale.
