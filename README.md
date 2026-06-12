# WP AI Publisher

Versione corrente: **0.2.2**

WP AI Publisher è un plugin WordPress per preparare un workflow di pubblicazione assistita da AI usando il sistema AI di WordPress configurato sul sito.

## Stato sviluppo

Il plugin è in fase infrastrutturale. Non genera ancora articoli, immagini o metadati SEO definitivi.

Funzioni presenti:

- bacheca admin in italiano;
- impostazioni base;
- stato sistema;
- adapter AI collegato al sistema AI di WordPress;
- diagnostica plugin terzi;
- predisposizione Git Updater;
- database log;
- database job queue;
- pagina Coda job;
- migrazione database durante aggiornamento plugin.

## Regole operative

- `wp-ai-publisher.php`, `readme.txt` e `README.md` devono essere sempre aggiornati.
- Ogni micro o macro modifica deve aggiornare il changelog.
- Le voci **Impostazioni** e **Stato sistema** devono restare sempre alla fine del menu del plugin.
- Le altre voci del menu devono essere ordinate per importanza d’uso.
- Il plugin deve restare funzionante anche se i plugin terzi consigliati non sono installati o non sono attivi.
- Le chiamate AI future dovranno passare solo dall’adapter centrale.

## Plugin terzi controllati

- Git Updater
- Git Remote Updater
- AI Services / WordPress AI
- AI Request Logging
- Connector Approval
- Abilities Explorer
- AIOSEO

La chiave REST di Git Remote Updater resta nel pannello Git Updater / Git Remote Updater. WP AI Publisher non la salva.

## Upgrade database

Dalla versione **0.2.2**, il plugin controlla lo schema database anche durante il bootstrap. Questo risolve il caso in cui WordPress aggiorna il plugin senza rieseguire l’hook di attivazione.

## Changelog

### 0.2.2

- Corretto il flusso di upgrade database.
- Le migrazioni vengono eseguite anche dopo aggiornamento plugin.
- Evitati errori sulla pagina Coda job nei siti aggiornati da versioni precedenti.
- Formalizzata la regola di ordinamento menu.
- Aggiornato README.md insieme alla versione plugin.

### 0.2.1

- Aggiunto controllo plugin terzi richiesti e consigliati.
- Aggiunto box plugin terzi nella Bacheca.
- Migliorata diagnostica Stato sistema.

### 0.2.0

- Aggiunta fondazione Job Queue.
- Aggiunta tabella database dei job.
- Aggiunta pagina admin Coda job.

### 0.1.1

- Interfaccia principale tradotta in italiano.
- Uso esclusivo del sistema AI di WordPress.
- Aggiunto menu a tendina per la selezione modello AI.

### 0.1.0

- Fondazione iniziale plugin.
- Bacheca admin, impostazioni e stato sistema.
