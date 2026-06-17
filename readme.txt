=== WP AI Publisher ===
Contributors: wp-ai-publisher
Tags: ai, publishing, admin, drafts, wordpress-ai
Requires at least: 6.5
Tested up to: 6.5
Requires PHP: 8.1
Stable tag: 0.5.46
License: GPLv3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Base modulare per la pubblicazione assistita da AI in WordPress.

== Descrizione ==

WP AI Publisher prepara l’infrastruttura del plugin per la futura generazione assistita di articoli, bozze strutturate, media, metadati SEO, link interni, knowledge index, coda job, dry-run, controllo duplicati e pubblicazione assistita. La versione 0.5.0 aggiunge il flusso prudente “Approva dry-run / Crea bozza”, crea post WordPress solo in stato draft o pending, collega la bozza all’idea e continua a bloccare la pubblicazione automatica.

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

= 0.5.46 =
* Assistente Guide AI: corretto l'errore "Sessione scaduta" che compariva al primo invio per gli utenti loggati. La richiesta REST ora invia l'header X-WP-Nonce (cookie auth), mantenendo coerente l'utente tra creazione e verifica del nonce.
* Gli utenti loggati con permessi sul plugin (admin) ignorano i limiti anti-abuso e la cache, così possono testare la funzionalità liberamente senza consumare i tetti giornalieri.

= 0.5.45 =
* Assistente Guide AI: nuovo modulo pubblico (shortcode [wpai_guide_generator]) che genera una guida personalizzata in base alla richiesta del visitatore, ancorata ai contenuti del sito. La risposta AI è seguita dagli articoli consigliati (risultati reali della ricerca) e può essere salvata in PDF (stampa lato browser). One-shot, non chat.
* Link sotto controllo: l'AI riceve solo URL reali del sito e una whitelist opzionale di link esterni; ogni link non interno o non in whitelist viene rimosso automaticamente (niente link inventati). HTML ripulito con wp_kses.
* Limite token/abuso: limite giornaliero per IP e globale, attesa tra richieste, honeypot e cache delle richieste identiche. Nuova pagina "Assistente Guide AI" per la configurazione (prompt, lingua, categorie/tipi di contenuto in cui cercare, whitelist, limiti) e pagina "Richieste guide" con l'elenco delle richieste, convertibili in Idea contenuto. IP salvato solo come hash.

= 0.5.44 =
* Integrazione Instagram: pubblicazione automatica dell'immagine in evidenza dell'articolo su un account Instagram Business alla pubblicazione (stesso schema di Facebook). Casella "Condividi su Instagram" nell'editor (per-articolo); pubblicazione in background (WP-Cron, non bloccante, anti-duplicato) con flusso a due passi (container + media_publish) e salvataggio di media ID e permalink sul post.
* Caption da template personalizzabile ({title}, {meta_title}, {meta_description}, {excerpt}, {hashtags}, {link}, max 2200 caratteri) oppure generata dall'AI (opzionale). Instagram richiede un'immagine: senza immagine in evidenza la condivisione viene saltata. Il link viene aggiunto come testo (non cliccabile su Instagram).
* Token letto da WPAIP_INSTAGRAM_ACCESS_TOKEN con fallback automatico a WPAIP_FACEBOOK_ACCESS_TOKEN (o filtro), mai nel database. Impostazioni → Instagram con guida ai prerequisiti (account Business collegato alla Pagina, permessi instagram_basic/instagram_content_publish), come recuperare l'IG User ID e pulsante "Verifica connessione Instagram".

= 0.5.43 =
* Integrazione Facebook: condivisione automatica dell'articolo su una Pagina Facebook alla pubblicazione. Casella "Condividi su Facebook" nell'editor (per-articolo); la condivisione avviene in background (WP-Cron, non bloccante, anti-duplicato) e l'ID del post Facebook viene salvato sul post.
* Testo del post da template personalizzabile ({title}, {meta_title}, {meta_description}, {excerpt}, {hashtags}, {link}) oppure generato dall'AI (opzionale). Modalità link (anteprima Open Graph) o foto (immagine in evidenza + testo).
* Token letto dalla costante WPAIP_FACEBOOK_ACCESS_TOKEN (o filtro), mai salvato nel database. Pulsante "Verifica connessione Pagina" nelle Impostazioni. I post generati dal plugin sono marcati con meta _wpai_publisher_generated.
* Guida nelle Impostazioni → Facebook su come recuperare l'ID Pagina e generare il Page Access Token (con link a Graph API Explorer, Meta Business Suite, Utenti di sistema e Access Token Debugger) e link diretto alla Pagina quando l'ID è impostato.

= 0.5.42 =
* Telegram interattivo: dopo l'invio del messaggio il bot mostra i pulsanti (inline keyboard) per scegliere la Tipologia articolo e poi selezionare una o più Categorie, quindi genera la bozza con quelle scelte. Le categorie scelte vengono forzate sulla bozza (filtro wpai_publisher_forced_category_ids). Nuova impostazione "Scelta interattiva" (attiva di default); se disattivata, la bozza viene generata subito come prima.

= 0.5.41 =
* Knowledge base OpenAI: nuovo pulsante "Testa accesso allo storage OpenAI" che verifica la raggiungibilità dei Vector store (con numero di file indicizzati) e che il modello sappia usarli tramite file_search. L'uso dei documenti è automatico quando la spunta file_search è attiva: non serve indicarlo nel prompt.

= 0.5.40 =
* Telegram: nuovo pulsante "Invia istruzioni su Telegram" che recapita alle Chat ID autorizzate le istruzioni su come creare una bozza tramite messaggi.
* Aggiunto nelle Impostazioni un riquadro "Come configurare e usare l'integrazione Telegram" con i passi di configurazione e uso.

= 0.5.39 =
* Telegram: pulsanti "Registra webhook" e "Verifica stato webhook" nelle Impostazioni, per configurare il webhook direttamente dal plugin senza usare il terminale (usano token e secret dalle costanti). Esito mostrato come avviso nella pagina.

= 0.5.38 =
* Integrazione Telegram: invia un messaggio al bot per creare automaticamente un'idea contenuto e generare la bozza. La generazione avviene in background (WP-Cron) e il bot risponde con il link alla bozza.
* Endpoint REST POST /wp-json/wp-ai-publisher/v1/telegram autenticato con secret token (header X-Telegram-Bot-Api-Secret-Token) e allowlist di chat ID. Token e secret letti da costanti WPAIP_TELEGRAM_BOT_TOKEN / WPAIP_TELEGRAM_SECRET (o filtri), mai salvati nel database.
* Nuove impostazioni Telegram: abilitazione, chat autorizzate, Tipologia articolo, lingua, risposta on/off; mostra l'URL del webhook da registrare.

= 0.5.37 =
* Fix inserimento immagini nel corpo e immagine in evidenza: la generazione delle immagini (lenta) poteva interrompere la richiesta prima che il post venisse salvato, lasciando i segnaposto e saltando l'immagine in evidenza. Ora l'immagine in evidenza viene generata per prima e ogni immagine del corpo viene salvata subito nel post (salvataggio incrementale), così un'eventuale interruzione non annulla il lavoro già fatto.
* Limiti di runtime alzati durante la fase immagini (set_time_limit, memoria) per ridurre i timeout su immagini multiple.

= 0.5.36 =
* Titolo e permalink generati dall'AI: il titolo non è più la prima parte dell'idea ma viene riscritto dall'AI (SEO-oriented) e lo slug/permalink è proposto dall'AI. Nuovi campi JSON "title" e "slug" nel contratto di generazione.
* Immagini nel corpo più robuste: oltre ai segnaposto [[wpai-image: ...]] vengono ora intercettati anche eventuali tag <img>/<figure> prodotti dall'AI (es. con prompt personalizzati) e convertiti in immagini reali. Markup di output con <figure> e <img class="aligncenter">.
* Nome file immagini: il file e il titolo nella Libreria media derivano ora dalla descrizione/alt della singola immagine (non dal titolo dell'articolo). L'immagine in evidenza usa il nuovo campo "featured_image_alt" generato dall'AI; viene impostato anche il testo alternativo.

= 0.5.35 =
* Knowledge base OpenAI (file_search): nuovo canale opt-in che genera gli articoli tramite la Responses API di OpenAI ancorandoli ai documenti caricati nei Vector store (RAG). Quando attivo e configurato viene tentato per primo, con fallback automatico al canale AI di WordPress.
* La chiave API OpenAI non viene salvata nel database: si legge dalla costante WPAIP_OPENAI_API_KEY (wp-config.php) o dal filtro wpai_publisher_openai_api_key.
* Nuove impostazioni: "Usa knowledge base", "Vector Store ID" (uno o più) e "Modello Responses API". Nuovi filtri wpai_publisher_openai_model e wpai_publisher_openai_responses_body. Lo Stato sistema mostra il canale openai_responses quando pronto.

= 0.5.34 =
* Immagini reali nel corpo dell'articolo: l'AI indica i punti adatti con un segnaposto [[wpai-image: descrizione]] e il plugin genera l'immagine, la carica nella Libreria media e sostituisce il segnaposto con un'immagine vera (mai un placeholder). I segnaposto non elaborati o in eccesso vengono rimossi.
* Nuove impostazioni opt-in: "Immagini nel corpo AI" e "Numero massimo immagini nel corpo" (0–10, default 3). Strettamente non bloccante.
* Il prompt di generazione istruisce automaticamente l'AI sul formato del segnaposto quando la funzione è attiva.

= 0.5.33 =
* Contesto del sito passato all'AI: la generazione riceve i tag esistenti (da riusare), le categorie disponibili (l'AI sceglie quelle coerenti tramite ID) e gli URL reali degli articoli pubblicati per inserire link interni pertinenti nel corpo dell'articolo.
* Output AI strutturato: l'AI restituisce un oggetto JSON (html, tags, category_ids, meta_title, meta_description) con fallback robusto all'HTML semplice se il modello non rispetta il formato.
* SEO All in One SEO Pack: in fase di creazione della bozza vengono scritti Meta title e Meta description (modello AIOSEO v4 con fallback su post meta _aioseo_title/_aioseo_description). Operazione non bloccante.
* Nuovi filtri: wpai_publisher_context_max_tags, wpai_publisher_context_max_links, wpai_publisher_seo_meta.

= 0.5.32 =
* Programmazione idee: ora puoi impostare data e ora e usare il pulsante "Programma" per generare la bozza automaticamente in seguito. Nuovo stato "Programmata", colonna database scheduled_at (schema 9) e cron ogni 5 minuti che accoda le idee scadute. Resta possibile forzare subito la generazione.

= 0.5.31 =
* Dashboard WordPress: nuovo widget "WP AI Publisher — Idee contenuto" con contatori per stato, ultime idee con link e collegamento rapido alla pagina Idee.
* Elenco "Ultime idee": tabella a tutta larghezza e più compatta, con paginazione quando le idee sono molte (filtro wpai_publisher_ideas_per_page, default 20).

= 0.5.30 =
* Escissione profonda dell'adapter AI: rimosso il vecchio motore dry-run/strutturato e il probing speculativo non più usato (generazione locale di fallback, registry e client ipotetici, validazione strutturata). L'adapter passa da ~3380 a ~1890 righe.
* Rimossa la classe Structured_Output_Validator e gli helper privati di Content_Ideas legati al vecchio flusso. Restano intatti il flusso a chiamata singola (php_ai_client, abilities API, AI Services, filtro), la generazione immagini e la diagnostica.
* Nessuna modifica funzionale al percorso attivo: creazione bozza, immagine in evidenza, Parametri AI e Diagnostica invariati.

= 0.5.29 =
* Test: aggiunti test unitari per Classic_Content_Builder (validazione pubblicabilità, normalizzazione HTML, rilevamento placeholder), per i parametri AI e la decodifica immagini dell'adapter, e per la normalizzazione delle impostazioni. Bootstrap di test ampliato con stub WordPress; i test girano in CI via composer test.

= 0.5.28 =
* Snellimento (fase 1): rimossa la "modalità avanzata" e il vecchio flusso dry-run dall'interfaccia. Eliminati l'impostazione workflow_mode, i pulsanti Esegui dry-run / Genera articolo completo, i relativi handler e i metodi run_dry_run / generate_full_article / save_full_article_output in Content_Ideas (non più usati dal flusso single-call). Rimosso dead code in Draft_Creator.
* La creazione bozza resta interamente sul flusso a chiamata singola; la vista "Risultato generazione" è invariata.
* Nota: l'escissione profonda del probing speculativo nell'adapter è rinviata a un passo dedicato e testabile (ancora intrecciata con Diagnostica AI e filtri pubblici legacy).

= 0.5.27 =
* Tipologie articolo: la generazione è ora guidata dal solo "Prompt principale". I campi legacy (tono, lunghezza, intento, livello, sezioni, pattern, checklist) restano usati solo come fallback per le tipologie create prima del prompt unico (retrocompatibilità, nessuna migrazione distruttiva).
* Aggiornate le 5 tipologie default per nuove installazioni con un prompt unico già pronto.

= 0.5.26 =
* Pulizia configurazione: rimosse impostazioni legacy inutilizzate (limite costo giornaliero/mensile, modello testo legacy default_text_model, provider_preference fisso). La scelta del modello resta in Parametri AI → "Modello AI".
* Pagina Impostazioni più snella (rimosse le sezioni Provider AI e i limiti di costo non operativi).

= 0.5.25 =
* Generazione immagine in evidenza con l'AI (opt-in): nuova opzione in Impostazioni → Parametri AI. Quando attiva, alla creazione della bozza viene generata un'immagine tramite il PHP AI Client (WordPress\AiClient\AiClient::prompt()->generateImage()) usando il "Prompt immagini" della Tipologia articolo (o un prompt costruito dal titolo) e impostata come immagine in evidenza.
* L'immagine viene importata nella Libreria media; la funzione è strettamente non bloccante: se la generazione o l'import falliscono, la bozza viene comunque creata senza immagine e l'errore è registrato nei log.

= 0.5.24 =
* Tipologie articolo: form semplificato con un unico "Prompt principale" (tono, struttura, lunghezza, regole, sezioni tutto in un campo) e una textarea dedicata "Prompt immagini" per la futura generazione delle immagini. Aggiunta colonna database image_prompt (schema 8).
* Lista Tipologie articolo più chiara (Nome, Attiva, Prompt, Immagini, Categorie) e pulsante Annulla nel form.
* Idee contenuto: aggiunto il pulsante "Elimina" per la singola idea (la bozza eventualmente collegata non viene eliminata).
* Idee contenuto: rimossi testi e blocchi descrittivi non più necessari (avvisi dry-run/contesto ridondanti, diagnostica candidato, JSON grezzo, prompt immagine in evidenza, link interni) e rinominata la sezione risultato in "Risultato generazione".

= 0.5.23 =
* Risolto l'errore 400 "Unsupported parameter: 'temperature'": la temperatura non viene più inviata di default (molti modelli "reasoning" la rifiutano). Ora è opzionale e configurabile.
* Nuova sezione Impostazioni → "Parametri AI": scelta del modello, timeout richiesta, lunghezza massima output (token) e temperatura. I valori sono usati dal PHP AI Client e sovrascrivibili via filtri.
* La scelta del modello viene applicata best-effort tramite il PHP AI Client; se vuota si usa il modello configurato nel provider AI.

= 0.5.22 =
* Connettività OpenAI confermata funzionante: il timeout in generazione dipende dalla lentezza del modello/lunghezza output, non dalla rete. Timeout HTTP predefinito alzato a 180 secondi (filtrabile con wpai_publisher_ai_http_timeout).
* Aggiunto un limite all'output per contenere i tempi di generazione: filtro wpai_publisher_ai_max_output_tokens (default 4000) e wpai_publisher_ai_temperature (default 0.7), applicati al PHP AI Client quando supportati.
* Messaggio d'errore aggiornato: distingue tra connettività bloccata e generazione troppo lenta, con le leve concrete (timeout, modello più veloce, meno token).

= 0.5.21 =
* Aggiunto in Diagnostica AI il pulsante "Verifica connettività OpenAI": esegue una richiesta leggera a api.openai.com e indica se il server raggiunge il provider (qualunque codice HTTP, anche 401) o se le connessioni in uscita sono bloccate (timeout/cURL error 28).
* Messaggio d'errore dedicato quando il provider è raggiunto ma la richiesta va in timeout / non riceve risposta: indica che è un blocco di rete in uscita del server (firewall/proxy/DNS), non un problema del plugin, e rimanda al test di connettività.

= 0.5.20 =
* Risolto il timeout di rete (cURL error 28) durante la generazione via PHP AI Client: la chiamata a OpenAI usava il timeout HTTP predefinito di WordPress (5 secondi), troppo breve per generare un articolo. Ora il timeout viene esteso (default 90 secondi, filtrabile con wpai_publisher_ai_http_timeout) solo per la durata della richiesta di generazione.

= 0.5.19 =
* Aggiunto il canale di generazione tramite il PHP AI Client ufficiale di WordPress (WordPress\AiClient\AiClient::prompt()->generateText()), usato dallo stack "AI Provider for OpenAI". È il canale primario dopo l’eventuale filtro personalizzato e usa il provider/modello configurato sul sito (es. OpenAI). Questo abilita finalmente la generazione reale dell’articolo e la creazione della bozza.
* Stato sistema e diagnostica generazione riportano il nuovo canale php_ai_client.

= 0.5.18 =
* Messaggio d’errore chiaro quando l’integrazione AI presente non offre una generazione di testo/articoli: molti stack espongono solo ability specifiche (immagini, classificazione, SEO) o richiedono permessi non disponibili in WP-Cron.
* Documentata la via di integrazione affidabile: il filtro wpai_publisher_generate_article_from_idea per collegare un generatore di testo reale (vedi README → Integrazione AI). È il primo canale tentato.

= 0.5.17 =
* Generazione via WordPress Abilities API molto più robusta: l’input viene ora derivato dallo schema dell’ability (prima si usava un input fisso che falliva la validazione), si tentano più forme di input e metodi (execute/run/invoke/call/perform), e gli eventuali WP_Error restituiti dall’ability vengono catturati e riportati.
* Le ability di generazione testo non vengono più escluse per assenza di marcatori "read-only": si escludono solo quelle con segnali distruttivi/di azione (pubblica, crea, elimina, ecc.).
* Diagnostica per-ability: per ogni ability pertinente vengono registrati nome, chiavi dello schema di input ed esito dell’invocazione, visibili in Stato sistema → Dettaglio log critici interni.

= 0.5.16 =
* Diagnostica generazione AI: ogni tentativo di creazione bozza ora registra quale integrazione AI è rilevata (classi/funzioni presenti) e l’esito di ciascun canale di generazione (filtro, Abilities API, AI Services, wp_ai_generate_text).
* Aggiunto un canale di generazione per il plugin "AI Services" (felix-arntz/ai-services), invocato in modo sicuro: se l’API non è compatibile l’errore viene catturato e diagnosticato invece di bloccare.
* Stato sistema: la riga "WordPress AI Client / API" mostra cosa è rilevato e se esiste un canale di generazione compatibile; la sezione "Dettaglio log critici interni" ora include una colonna Dettaglio (canale, integrazioni rilevate, esiti per canale) e mostra più voci.
* Messaggio d’errore più chiaro quando un’AI è rilevata ma nessun canale produce contenuto, con rimando a Stato sistema.

= 0.5.15 =
* La creazione bozza non viene più bloccata dalla qualità dell’articolo: i prompt della Tipologia articolo guidano la scrittura ma non impediscono la bozza. Lunghezza minima, numero di H2, sezioni obbligatorie e frasi segnaposto sono ora note di qualità non bloccanti, visibili sull’idea/bozza.
* Restano bloccanti solo i controlli di sicurezza, già garantiti dalla sanitizzazione (allowlist wp_kses, rimozione di blocchi Gutenberg/script/iframe/style). Un articolo viene scartato solo se, dopo la sanitizzazione, è completamente vuoto.
* L’errore "Il sistema AI non ha restituito un articolo pubblicabile" ora compare solo quando l’AI non restituisce contenuto reale, non quando l’articolo è semplicemente breve o non rispetta una checklist editoriale.

= 0.5.14 =
* Semplificato il flusso di creazione bozza: una sola chiamata AI (idea + prompt Tipologia articolo) produce l’articolo completo, poi il plugin crea la bozza. Rimosso il passaggio intermedio di dry-run JSON dal percorso "Crea bozza".
* Corretto l’errore di creazione bozza: le sezioni obbligatorie della Tipologia vengono ora iniettate nel prompt come scaletta richiesta e non bloccano più la pubblicabilità (restano un segnale di qualità nei log).
* Quando non è attivo alcun sistema AI di WordPress, "Crea bozza" non genera più contenuti segnaposto: mostra un messaggio chiaro e lascia l’idea pronta a riprovare.
* I passaggi manuali avanzati (dry-run, genera articolo completo) restano disponibili per chi usa la modalità avanzata.

= 0.5.13 =
* Corretto il requisito minimo di WordPress: da 7.0 (versione inesistente che bloccava l’attivazione su qualsiasi sito) a 6.5, allineato in header plugin, readme, Stato sistema e Bacheca.
* Allineate le versioni di plugin, readme.txt e README.md.
* Reso atomico il claim dei job in coda per evitare doppia elaborazione e bozze duplicate quando WP-Cron e l’esecuzione manuale si sovrappongono.
* Ridotto l’overhead delle letture idee: il controllo della colonna article_type_id ora esegue le query SHOW al massimo una volta per richiesta.
* La migrazione database non riesegue più dbDelta a ogni nuova versione, ma solo quando cambia effettivamente lo schema.
* La coda elabora un piccolo batch di job per esecuzione cron (filtrabile via wpai_publisher_jobs_per_run) e si ripianifica se restano job pendenti.
* Rimosso codice legacy non utilizzato: classe CPT Tipologie articolo, relativa vista metabox, costante WPAIP_ENABLE_ARTICLE_TYPES e fallback admin inutilizzato.
* Introdotta una capability dedicata e filtrabile (manage_wp_ai_publisher), concessa agli amministratori in attivazione e upgrade, così l’accesso al plugin può essere delegato senza concedere manage_options.
* Centralizzata l’istanziazione di Article_Type_Repository (iniettato in Admin) e Draft_Creator (accessor condiviso in Content_Ideas).
* Aggiunta l’opzione opt-in “Elimina i dati alla disinstallazione”: di default il plugin conserva tabelle, impostazioni e capability.
* Aggiunto tooling di qualità: composer.json, ruleset phpcs, test PHPUnit di base e workflow GitHub Actions (lint PHP, unit test, phpcs informativo).
* Adapter AI: centralizzate in metodi dedicati e testabili le liste di rilevamento (classi/funzioni AI, discovery modelli e abilities), senza modificarne il comportamento o gli hook.

= 0.5.7 =
* Semplificata la pagina Impostazioni: il contesto globale non duplica più tono, tag e categorie delle Tipologie Articolo.
* Aggiunto vincolo categorie globali con checkbox e intersezione con le categorie della Tipologia Articolo.

= 0.5.6 =
* Corretta la gestione dei campi editoriali multilinea nelle Tipologie articolo in tabella custom.
* Allineati i formati SQL usati durante il salvataggio e ampliati i campi editoriali brevi a TEXT.
* Reso il form Tipologie articolo libero per tono, lunghezza, intento ricerca e livello lettore, con testi di aiuto.
* Mantenuto il fallback bozze quando il repository Tipologie articolo è disabilitato.

= 0.5.5 =
* Reintrodotte Tipologie articolo come entità interne del plugin.
* Rimossa dipendenza dal Custom Post Type per le Tipologie articolo.
* Aggiunta tabella wpai_publisher_article_types.
* Aggiunta gestione admin interna per creare, modificare, eliminare e attivare tipologie.
* Le Tipologie articolo guidano prompt, struttura, tono, intento, lunghezza e categorie consentite.
* Il workflow Idee contenuto usa le tipologie dalla tabella custom.
* Evitato uso di map_meta_cap e capability post custom.
* Mantenuta stabilità admin della recovery 0.5.4.

= 0.5.4 =
* Recovery release per stabilizzare admin menu.
* Disabilitato temporaneamente il modulo Tipologie Articolo tramite feature flag.
* Rimossa registrazione CPT Tipologie Articolo dal caricamento predefinito.
* Ridotto rischio di notice map_meta_cap su delete_post.
* Ripristinato workflow Idee contenuto senza obbligo di Tipologia Articolo in recovery mode.
* Migliorata compatibilità admin WordPress.

= 0.5.3 =
* Hotfix bootstrap/admin menu per prevenire fatal error.
* Rimossa creazione Tipologie articolo dal bootstrap database.
* Spostata creazione tipologie default in admin_init sicuro.
* Eliminato uso di get_page_by_title.
* Aggiunti helper sicuri per Article_Types.
* Reso admin menu difensivo se CPT o classe non sono disponibili.
* Migliorata gestione tipologie inattive/cancellate nelle idee contenuto.
* Aggiunte istruzioni debug nel README.

= 0.5.2 =
* Hotfix per errore fatale dopo aggiornamento 0.5.x.
* Rimossa creazione tipologie default dal bootstrap diretto.
* Spostata creazione tipologie default in fase admin sicura.
* Rimosso uso di get_page_by_title.
* Aggiunti helper sicuri per Article_Types.
* Corretta gestione tipologie inattive, cancellate o mancanti.
* Aggiunta riassegnazione tipologia per idee migrate o non valide.
* Corretta logica categorie consentite vuote.
* Rafforzata diagnostica Stato sistema.

= 0.5.1 =
* Hotfix per errore critico dopo aggiornamento 0.5.0.
* Rafforzata la sicurezza del caricamento plugin durante upgrade.
* Migliorata la migrazione article_type_id.
* Corretto uso categorie: se una tipologia non ha categorie consentite, l’AI non può assegnare categorie liberamente.
* Passata la Tipologia articolo anche al builder e validatore full_article.
* Aggiunta assegnazione reale della Tipologia articolo alle idee migrate.
* Rimosse/semplificate impostazioni GitHub updater non più necessarie.
* Migliorata diagnostica Stato sistema per Tipologie articolo.

= 0.5.0 =
* Aggiunte Tipologie di Articolo gestibili da pannello WordPress.
* Aggiunto CPT wpai_article_type.
* Le Tipologie di Articolo guidano prompt, struttura, tono, intento, lunghezza e sezioni obbligatorie.
* Semplificato il form Idee contenuto rimuovendo Livello tutorial e Note editoriali.
* Aggiunta selezione obbligatoria della Tipologia articolo nella creazione idea.
* Le categorie non vengono più create dall’AI: vengono associate solo categorie WordPress esistenti.
* Aggiunto campo article_type_id alla tabella idee.
* Aggiornato workflow: Contesto sito + Tipologia articolo + Idea → Bozza.
* Rimossa impostazione Aggiornamenti da GitHub non più necessaria.
* Spostata/verificata configurazione Sicurezza Abilities AI come opzione avanzata.

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

= 0.5.0 =
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
