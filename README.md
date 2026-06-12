# WP AI Publisher

Versione corrente: **0.3.3**

WP AI Publisher è un plugin WordPress per preparare un workflow di pubblicazione assistita da AI usando il sistema AI di WordPress configurato sul sito.

## Stato sviluppo

Il plugin è in fase operativa controllata. Non crea ancora post, bozze, immagini reali o metadati SEO definitivi, ma consente di testare idee editoriali con un dry-run strutturato.

## Target editoriale: Editor Classico

Dalla versione **0.3.2** il target editoriale principale è l’**Editor Classico** di WordPress. Il plugin non deve produrre blocchi Gutenberg, commenti `<!-- wp:... -->` o serializzazione a blocchi.

La futura bozza WordPress userà `post_content` con HTML pulito e sicuro, ad esempio paragrafi, titoli `h2`/`h3`, liste e altri tag consentiti da allowlist. L’anteprima del dry-run passa da sanitizzazione dedicata e viene mostrata nell’admin come contenuto compatibile con Classic Editor.

AIOSEO sarà gestito separatamente in una fase successiva e non viene scritto in questa versione. Le immagini saranno integrate più avanti tramite Media Library, senza generazione reale nella fase 0.3.3.

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
- sezione Idee contenuto;
- salvataggio idee editoriali in tabella dedicata;
- dry-run articolo con output JSON validabile, visualizzazione leggibile e anteprima HTML per Editor Classico;
- migrazione database durante aggiornamento plugin.

## Regole operative

- `wp-ai-publisher.php`, `readme.txt` e `README.md` devono essere sempre aggiornati.
- Ogni micro o macro modifica deve aggiornare il changelog.
- Le voci **Impostazioni** e **Stato sistema** devono restare sempre alla fine del menu del plugin.
- Le altre voci del menu devono essere ordinate per importanza d’uso. Ordine attuale: Bacheca, Idee contenuto, Coda job, Impostazioni, Stato sistema.
- Il plugin deve restare funzionante anche se i plugin terzi consigliati non sono installati o non sono attivi.
- Le chiamate AI future dovranno passare solo dall’adapter centrale.

## Compatibilità hook dry-run

Dalla versione **0.3.3** il filtro consigliato per fornire output strutturato al dry-run è `wpai_publisher_generate_structured_content_dry_run`. Questo hook riceve il risultato iniziale `null`, il payload normalizzato e lo schema richiesto, quindi permette alle integrazioni WordPress AI di restituire JSON o array già conformi.

Per compatibilità con integrazioni create nella versione **0.3.0**, resta supportato anche il filtro legacy `wpai_publisher_structured_content_dry_run`. Il plugin lo richiama solo se il nuovo hook non produce un output utilizzabile: in questo modo le integrazioni aggiornate restano prioritarie e quelle esistenti continuano a funzionare senza causare fallback locale non necessario.

Entrambi gli hook devono restituire solo dati strutturati per anteprima e validazione: il plugin non crea post, non crea bozze, non pubblica contenuti e non chiama OpenAI direttamente.

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

Dalla versione **0.2.2**, il plugin controlla lo schema database anche durante il bootstrap. Questo risolve il caso in cui WordPress aggiorna il plugin senza rieseguire l’hook di attivazione. In **0.3.0** lo schema passa alla versione 3 e include la tabella delle idee contenuto.

## Idee contenuto

La sezione **WP AI Publisher > Idee contenuto** permette all’amministratore di inserire un argomento editoriale, una keyword, la lingua, il pubblico target, il livello tutorial e note editoriali.

In questa fase la sezione è sicura e reversibile:

- non crea post WordPress;
- non crea bozze;
- non pubblica contenuti;
- non genera immagini reali;
- non scrive dati AIOSEO;
- non modifica contenuti esistenti;
- non avvia job automatici in background;
- non chiama OpenAI direttamente.

## Dry-run articolo

Il pulsante **Esegui dry-run** genera una struttura articolo validabile con titolo, slug, estratto, outline, categorie, tag, metadati SEO proposti, prompt immagine solo testuale, link interni previsti, sintesi conoscenza, anteprima HTML per Editor Classico e note di validazione.

L’output viene salvato come JSON nella tabella delle idee contenuto e mostrato anche in forma leggibile nell’admin. Il campo `classic_editor_preview` contiene HTML sanificato, riepilogo testuale e note diagnostiche dedicate. Se nessuna chiamata WordPress AI reale è disponibile, l’adapter può produrre un fallback locale controllato solo quando il payload abilita `allow_local_fallback`.


## Dry-run AI reale e fallback locale

Dalla versione **0.3.1**, il dry-run delle Idee contenuto tenta prima una generazione reale tramite il sistema AI di WordPress disponibile sul sito: Abilities API, funzioni WordPress AI note, client AI locali o filtro di integrazione `wpai_publisher_generate_structured_content_dry_run`.

Se WordPress AI non è disponibile o non restituisce un JSON utilizzabile, il plugin usa un fallback locale più contestuale solo per testare il workflow admin, con outline WordPress più concreti per menù, plugin, WPML, SEO, sicurezza, backup, WooCommerce, Elementor, Classic Editor e Media Library. Il fallback locale è marcato con `source: local_fallback`, mostra note di validazione visibili e non deve essere considerato contenuto finale generato da AI reale.

Anche con WordPress AI disponibile, il dry-run resta sicuro:

- non crea post WordPress;
- non crea bozze;
- non pubblica contenuti;
- non genera immagini reali;
- non scrive metadati AIOSEO;
- non modifica contenuti esistenti.

## Changelog

### 0.3.3
- Ripristinata compatibilità con il filtro legacy `wpai_publisher_structured_content_dry_run`.
- Mantenuto il nuovo filtro `wpai_publisher_generate_structured_content_dry_run` come hook principale.
- Migliorato fallback locale per outline WordPress più concreti.
- Migliorate summary e anteprima HTML per Editor Classico.
- Migliorati titoli, slug, meta title e link interni previsti.
- Rafforzato controllo anti-placeholder nel contenuto di anteprima.
- Confermato target Editor Classico e assenza di blocchi Gutenberg.

### 0.3.2
- Impostato Editor Classico come target editoriale principale.
- Aggiunto Classic Content Builder.
- Aggiunta anteprima HTML compatibile con Editor Classico.
- Esclusa generazione di blocchi Gutenberg.
- Migliorato fallback locale per outline WordPress più concreti.
- Migliorati titoli e slug del dry-run.
- Aggiornata documentazione progetto.

### 0.3.1

- Migliorato dry-run Idee contenuto.
- Aggiunto tentativo di generazione tramite sistema AI di WordPress.
- Aggiunto fallback locale più utile e contestuale.
- Migliorata validazione dell’output strutturato.
- Normalizzato content_outline con heading, level numerico e summary.
- Aggiunta indicazione origine risultato: WordPress AI o fallback locale.
- Corretto ordine menu con Impostazioni e Stato sistema sempre in fondo.

### 0.3.0

- Aggiunta sezione Idee contenuto.
- Aggiunta tabella database per idee editoriali.
- Aggiunto primo dry-run strutturato articolo.
- Aggiunto output JSON validabile.
- Nessuna creazione automatica di post.
- Nessuna pubblicazione automatica.
- Nessuna chiamata OpenAI diretta.
- Aggiornato ordine menu operativo.

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
