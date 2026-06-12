=== WP AI Publisher ===
Contributors: wp-ai-publisher
Tags: ai, publishing, admin, drafts, wordpress-ai
Requires at least: 7.0
Tested up to: 7.0
Requires PHP: 8.1
Stable tag: 0.3.4
License: GPLv3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Base modulare per la pubblicazione assistita da AI in WordPress.

== Descrizione ==

WP AI Publisher prepara l’infrastruttura del plugin per la futura generazione assistita di articoli, bozze strutturate, media, metadati SEO, link interni, knowledge index, coda job, dry-run, controllo duplicati e pubblicazione assistita. La versione 0.3.4 mantiene l’Editor Classico come target editoriale, migliora anteprima HTML sicura da dry-run e mantiene il flusso senza creare post WordPress.

Il plugin usa esclusivamente il sistema AI di WordPress configurato sul sito. Non gestisce un client OpenAI custom e non salva chiavi API proprie. Include diagnostica difensiva per plugin terzi richiesti o consigliati, senza creare dipendenze rigide.

Questa versione non crea post, non pubblica contenuti, non genera immagini reali e non scrive metadati SEO. Il dry-run delle idee contenuto salva solo un output JSON validabile, leggibile nell’admin e corredato da anteprima HTML compatibile con Editor Classico. Tutte le funzioni AI passano dall’adapter centrale collegato al sistema AI di WordPress.

== Installazione ==

1. Carica la cartella del plugin in `/wp-content/plugins/` oppure installa lo ZIP da Plugin > Aggiungi plugin > Carica plugin.
2. Attiva WP AI Publisher dalla schermata Plugin.
3. Apri WP AI Publisher > Bacheca.
4. Apri WP AI Publisher > Idee contenuto per salvare un argomento editoriale ed eseguire un dry-run.
5. Controlla WP AI Publisher > Coda job.
6. Controlla WP AI Publisher > Impostazioni.
7. Apri WP AI Publisher > Diagnostica AI per analizzare il runtime AI.
8. Controlla WP AI Publisher > Stato sistema.

== Note sviluppo ==

Durante tutto lo sviluppo, `readme.txt`, `README.md` e versione del plugin devono restare aggiornati. Le voci “Impostazioni” e “Stato sistema” devono restare sempre alla fine del menu del plugin; le altre voci vanno ordinate per importanza d’uso.

Dalla versione 0.2.2 le migrazioni database vengono controllate anche durante il bootstrap del plugin, perché WordPress non riesegue automaticamente l’hook di attivazione dopo un aggiornamento one-click. In 0.3.0 questo flusso crea anche la tabella delle idee contenuto durante gli upgrade normali.

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

= 0.3.4 =
* Aggiunta pagina Diagnostica AI.
* Aggiunto rilevamento runtime di funzioni, classi, REST route, options e plugin collegati al sistema AI WordPress.
* Aggiunto test AI controllato eseguibile manualmente dall’amministratore.
* Aggiunta diagnostica dei possibili percorsi di generazione AI.
* Aggiunta sezione bridge manuale per collegare il connector AI reale.
* Nessuna chiave API viene mostrata o salvata.
* Nessuna chiamata OpenAI diretta.

= 0.3.3 =
* Ripristinata compatibilità con il filtro legacy wpai_publisher_structured_content_dry_run.
* Mantenuto il nuovo filtro wpai_publisher_generate_structured_content_dry_run.
* Migliorato fallback locale per outline WordPress più concreti.
* Migliorate summary e anteprima HTML per Editor Classico.
* Migliorati titoli, slug, meta title e link interni previsti.
* Rafforzato controllo anti-placeholder nel contenuto di anteprima.
* Confermato target Editor Classico e assenza di blocchi Gutenberg.

= 0.3.2 =
* Impostato Editor Classico come target editoriale principale.
* Aggiunto Classic Content Builder.
* Aggiunta anteprima HTML compatibile con Editor Classico.
* Esclusa generazione di blocchi Gutenberg.
* Migliorato fallback locale per outline WordPress più concreti.
* Migliorati titoli e slug del dry-run.
* Aggiornata documentazione progetto.

= 0.3.1 =
* Migliorato dry-run Idee contenuto.
* Aggiunto tentativo di generazione tramite sistema AI di WordPress.
* Aggiunto fallback locale più utile e contestuale.
* Migliorata validazione dell’output strutturato.
* Normalizzato content_outline con heading, level numerico e summary.
* Aggiunta indicazione origine risultato: WordPress AI o fallback locale.
* Corretto ordine menu con Impostazioni e Stato sistema sempre in fondo.

= 0.3.0 =
* Aggiunta sezione Idee contenuto.
* Aggiunta tabella database per idee editoriali.
* Aggiunto primo dry-run strutturato articolo.
* Aggiunto output JSON validabile.
* Nessuna creazione automatica di post.
* Nessuna pubblicazione automatica.
* Nessuna chiamata OpenAI diretta.
* Aggiornato ordine menu operativo.

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
