=== WP AI Publisher ===
Contributors: wp-ai-publisher
Tags: ai, publishing, admin, drafts, wordpress-ai
Requires at least: 7.0
Tested up to: 7.0
Requires PHP: 8.1
Stable tag: 0.4.5
License: GPLv3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Base modulare per la pubblicazione assistita da AI in WordPress.

== Descrizione ==

WP AI Publisher prepara l’infrastruttura del plugin per la futura generazione assistita di articoli, bozze strutturate, media, metadati SEO, link interni, knowledge index, coda job, dry-run, controllo duplicati e pubblicazione assistita. La versione 0.4.0 aggiunge il flusso prudente “Approva dry-run / Crea bozza”, crea post WordPress solo in stato draft o pending, collega la bozza all’idea e continua a bloccare la pubblicazione automatica.

Il plugin usa esclusivamente il sistema AI di WordPress configurato sul sito. Non gestisce un client OpenAI custom e non salva chiavi API proprie. Include diagnostica difensiva per plugin terzi richiesti o consigliati, senza creare dipendenze rigide.

Questa versione può creare una bozza WordPress solo da un dry-run approvato. Non pubblica automaticamente, non genera immagini reali e non scrive metadati SEO. Il dry-run delle idee contenuto salva un output JSON validabile, leggibile nell’admin e corredato da anteprima HTML compatibile con Editor Classico. Tutte le funzioni AI passano dall’adapter centrale collegato al sistema AI di WordPress.

== Installazione ==

1. Carica la cartella del plugin in `/wp-content/plugins/` oppure installa lo ZIP da Plugin > Aggiungi plugin > Carica plugin.
2. Attiva WP AI Publisher dalla schermata Plugin.
3. Apri WP AI Publisher > Bacheca.
4. Apri WP AI Publisher > Idee contenuto per salvare un argomento editoriale ed eseguire un dry-run.
5. Apri WP AI Publisher > Diagnostica AI per analizzare il runtime AI.
6. Controlla WP AI Publisher > Coda job.
7. Controlla WP AI Publisher > Impostazioni.
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

Sì, ma solo come bozza o contenuto in attesa di revisione dopo approvazione esplicita del dry-run. Non pubblica automaticamente.

= Come vengono mostrati i modelli AI disponibili? =

Il plugin prova a leggerli dal sistema AI di WordPress. Se l’integrazione attiva espone i modelli tramite funzioni, client o filtro `wpai_publisher_available_ai_models`, questi compaiono nel menu a tendina delle impostazioni.

== Changelog ==

= 0.4.5 =
* Corretto il passaggio del dry-run originale nella normalizzazione di full_article.
* Migliorata conversione di articoli plain text in HTML per Editor Classico.
* Gli heading lunghi del content_outline vengono ora riconosciuti correttamente.
* Migliorata la creazione bozza quando l’AI restituisce testo semplice.
* Aggiunti controlli e messaggi più chiari sugli errori di normalizzazione full_article.
* Confermata assenza di pubblicazione automatica.

= 0.4.3 =
* Semplificato il workflow principale: idea → articolo completo → bozza.
* Aggiunto pulsante principale “Crea bozza”.
* Aggiunta modalità workflow semplificata e avanzata.
* Aggiunta impostazione per creare automaticamente la bozza dal salvataggio idea.
* Migliorata la riscrittura fallback delle summary redazionali.
* Evitato che frasi come “Spiegare”, “Mostrare”, “Descrivere” entrino nel contenuto finale.
* Migliorata gestione degli errori recuperabili nella creazione bozza.
* Preparato il job type generate_draft_from_idea.
* Confermata assenza di pubblicazione automatica.


= 0.4.2 =
* Aggiunta generazione articolo completo da dry-run approvato.
* Distinta anteprima strutturale da contenuto finale per bozza.
* La bozza usa full_article.html quando disponibile.
* Rimosse istruzioni interne, pubblico, tono e regole editoriali dal corpo della bozza.
* Aggiunta validazione articolo pubblicabile per Editor Classico.
* Aggiunto pulsante “Genera articolo completo”.
* Bloccata la creazione di bozze con placeholder editoriali.
* Rafforzati i segnali distruttivi standalone per WordPress Abilities.
* Confermata assenza di pubblicazione automatica.

= 0.4.1 =
* Corretto e migliorato il salvataggio delle nuove idee contenuto.
* Aggiunta diagnostica più chiara in caso di errore database durante il salvataggio idee.
* Rimosso il campo Pubblico target dal form nuova idea.
* Il pubblico target viene ora letto dal Contesto editoriale del sito.
* Corretto il matching dei safety signal delle WordPress Abilities trattando underscore, spazi e trattini come separatori equivalenti.
* Rafforzata la protezione contro abilities con effetti collaterali.
* Consolidato l’uso di booleani interni nella diagnostica AI, separati dalle label tradotte.
* Mantenuta la creazione bozza da dry-run approvato.

= 0.4.0 =
* Aggiunta creazione bozza WordPress da dry-run approvato.
* Aggiunto flusso Approva dry-run / Crea bozza.
* Aggiunta classe Draft_Creator.
* Aggiunta relazione tra idea contenuto e post bozza.
* Aggiunta sanitizzazione HTML per Editor Classico.
* Aggiunta assegnazione categorie e tag.
* Aggiunta protezione contro creazione duplicata della bozza.
* Pubblicazione automatica non attiva in questa fase.
* Corretto default_tone con chiave valida chiaro_didattico_e_operativo.
* Rafforzato safety matching delle WordPress Abilities per evitare falsi positivi su parole come editorial/editing.
* Corretta la diagnostica AI per usare booleani reali invece di label localizzate come “Sì”.

= 0.3.8 =
* Rafforzata la sicurezza dell’invocazione WordPress Abilities API.
* Aggiunta allowlist per abilities AI sicure nel dry-run.
* Evitata l’esecuzione arbitraria di abilities con possibili effetti collaterali.
* Aggiunti filtri wpai_publisher_safe_ai_ability_names e wpai_publisher_is_ability_safe_for_dry_run.
* Migliorata diagnostica delle abilities con indicazione sicurezza.
* Aggiunta opzione “Pubblicato” nello stato post dopo generazione.
* Il default resta “Bozza” e la pubblicazione automatica non è ancora attiva.

= 0.3.7 =
* Aggiunte impostazioni di contesto editoriale del sito.
* Reso il plugin più adattabile a siti e nicchie diverse.
* Aggiunta configurazione per nicchia, pubblico, tono, lingua, categorie, tag e regole editoriali.
* Impostato Editor Classico come target corrente configurabile.
* Impostato flusso futuro di generazione post su Bozza o In attesa di revisione, senza pubblicazione automatica.
* Aggiornato prompt dry-run per usare il contesto sito.
* Migliorato fallback locale per contesti non WordPress.

= 0.3.5 =
* Corretto payload del filtro legacy wpai_publisher_structured_content_dry_run.
* Ripristinata piena compatibilità con integrazioni 0.3.0.
* Rimosso falso positivo sul termine “passaggio” nella validazione anteprima.
* Distinte note gravi e note lievi nella validazione Classic Editor.
* Aggiunto primo bridge verso WordPress Abilities API tramite wp_get_abilities e wp_get_ability.
* Migliorata privacy della pagina Diagnostica AI.
* Mascherate email, token, chiavi e options sensibili.
* Aggiunta sezione Abilities WordPress rilevate.
* Normalizzate impostazioni obsolete legate a OpenAI diretto.

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
