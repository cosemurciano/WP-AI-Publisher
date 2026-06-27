# WP AI Publisher

Versione corrente: **0.5.70**

## Assistente Guide AI 0.5.45

Modulo pubblico (shortcode `[wpai_guide_generator]`) che trasforma la richiesta di un visitatore in una **guida personalizzata** ancorata ai contenuti del sito. Sotto il testo generato dall'AI compaiono gli **articoli consigliati** (risultati reali della ricerca) e un pulsante per **salvare in PDF** (stampa lato browser). È **one-shot**, non una chat.

- **Link sotto controllo**: l'AI riceve solo URL reali del sito + una **whitelist** opzionale di link esterni; qualsiasi link non interno e non in whitelist viene rimosso automaticamente (niente link inventati). L'HTML è ripulito con `wp_kses`.
- **Limite token/abuso**: limite giornaliero **per IP** e **globale**, attesa minima tra richieste, **honeypot** e **cache** delle richieste identiche (riusa il risultato salvato).
- **Configurazione**: pagina *Assistente Guide AI* (prompt di sistema, lingua, tipi di contenuto e categorie in cui cercare, whitelist, lunghezza massima, limiti). Pagina *Richieste guide* con l'elenco di tutte le richieste, **convertibili in Idea contenuto**. L'IP è salvato solo come **hash**.

## Pubblicazione automatica su Instagram 0.5.44

## Pubblicazione automatica su Instagram 0.5.44

Alla **pubblicazione** di un articolo, WP AI Publisher può pubblicare l'**immagine in evidenza** su un account **Instagram Business** (stesso schema di Facebook). La condivisione è **per-articolo** (casella "Condividi su Instagram" nell'editor) e avviene in **background** (WP-Cron, non bloccante, anti-duplicato) con il flusso a due passi della Graph API (creazione del *media container* + `media_publish`); media ID e permalink vengono salvati sul post.

- **Immagine obbligatoria**: Instagram richiede un'immagine; senza immagine in evidenza la condivisione viene saltata (con errore registrato).
- **Caption**: template personalizzabile (`{title}`, `{meta_title}`, `{meta_description}`, `{excerpt}`, `{hashtags}`, `{link}`, max 2200 caratteri) o **generata dall'AI** (opzionale). Su Instagram i link non sono cliccabili: il link viene aggiunto come testo.
- **Token**: `WPAIP_INSTAGRAM_ACCESS_TOKEN` con fallback automatico a `WPAIP_FACEBOOK_ACCESS_TOKEN` (o filtro `wpai_publisher_instagram_access_token`), mai nel DB.
- **Prerequisiti Meta**: account Instagram Business/Creator collegato alla Pagina Facebook; permessi `instagram_basic` e `instagram_content_publish`. L'IG User ID si ricava da `{ID-Pagina}?fields=instagram_business_account`. Pulsante **"Verifica connessione Instagram"** in Impostazioni.

## Condivisione automatica su Facebook 0.5.43

Alla **pubblicazione** di un articolo, WP AI Publisher può condividerlo su una **Pagina Facebook**. La condivisione è **per-articolo** (casella "Condividi su Facebook" nel riquadro dell'editor) e avviene in **background** (WP-Cron, non bloccante, con anti-duplicato); l'ID del post Facebook viene salvato sul post.

- **Trigger**: transizione del post a *pubblicato* (`transition_post_status`), solo se la casella è attiva.
- **Testo**: template personalizzabile con segnaposto `{title}`, `{meta_title}`, `{meta_description}`, `{excerpt}`, `{hashtags}`, `{link}` — oppure **caption generata dall'AI** (opzionale, con fallback al template).
- **Modalità**: *link* (condivide il permalink, anteprima da Open Graph) o *foto* (immagine in evidenza + testo).
- **Sicurezza**: token Pagina dalla costante `WPAIP_FACEBOOK_ACCESS_TOKEN` (o filtro `wpai_publisher_facebook_access_token`), mai nel DB. Pulsante **"Verifica connessione Pagina"** in Impostazioni.
- **Prerequisiti Meta**: App Meta, Pagina, Page Access Token (preferibile System User) con permessi `pages_manage_posts` e `pages_read_engagement`. Instagram è previsto in una fase successiva.

## Telegram interattivo: scelta Tipologia e Categorie 0.5.42

Quando la **Scelta interattiva** è attiva, dopo aver inviato il messaggio il bot risponde con dei **pulsanti** (inline keyboard): prima scegli la **Tipologia articolo**, poi selezioni una o più **Categorie** (multi-selezione con ✅), infine premi **Genera bozza**. Le categorie scelte vengono **forzate** sulla bozza (filtro `wpai_publisher_forced_category_ids`); con **Salta** le sceglie l'AI. Disattivando l'opzione, la bozza viene generata subito con la Tipologia predefinita (comportamento precedente).

## Integrazione Telegram 0.5.38

Invia un messaggio al **bot Telegram** per creare automaticamente un'**idea contenuto** e generare la **bozza**. Il flusso è asincrono: l'endpoint crea subito l'idea, accoda la generazione su WP-Cron e risponde rapido (così Telegram non ritenta); a bozza pronta il bot invia un messaggio con il **link di modifica**.

- **Endpoint**: `POST /wp-json/wp-ai-publisher/v1/telegram`
- **Sicurezza**: secret token nell'header `X-Telegram-Bot-Api-Secret-Token` (confronto `hash_equals`) + **allowlist di chat ID**. Token e secret **non si salvano nel DB**: costanti `WPAIP_TELEGRAM_BOT_TOKEN` / `WPAIP_TELEGRAM_SECRET` (o filtri `wpai_publisher_telegram_bot_token` / `wpai_publisher_telegram_secret_token`).
- **Impostazioni → Telegram**: abilitazione, chat autorizzate, Tipologia articolo, lingua, risposta on/off, URL webhook da registrare.

Setup:
1. In `wp-config.php`: `define( 'WPAIP_TELEGRAM_BOT_TOKEN', '123456:ABC...' );` e `define( 'WPAIP_TELEGRAM_SECRET', 'stringa-casuale' );`
2. In Impostazioni abilita Telegram, aggiungi le chat ID autorizzate e scegli la Tipologia.
3. Registra il webhook con il pulsante **"Registra webhook"** in Impostazioni (oppure via terminale: `curl "https://api.telegram.org/bot<TOKEN>/setWebhook?url=<URL_WEBHOOK>&secret_token=<SECRET>"`). Usa **"Verifica stato webhook"** per controllare che sia attivo.

## Fix inserimento immagini (corpo + in evidenza) 0.5.37

La generazione delle immagini è lenta e sincrona: con più immagini la richiesta poteva andare in timeout **prima** del salvataggio del post, lasciando i segnaposto `[[wpai-image: ...]]` nell'articolo e saltando l'immagine in evidenza. Ora:
- l'**immagine in evidenza** viene generata **per prima** (asset indipendente e più visibile), quindi viene impostata anche se la fase delle immagini nel corpo si interrompe;
- ogni **immagine nel corpo** viene **salvata subito** nel post (salvataggio incrementale per immagine), così un'interruzione non annulla quelle già inserite;
- i **limiti di runtime** (tempo di esecuzione, memoria) vengono alzati durante la fase immagini.

## Titolo/permalink dall'AI, immagini nel corpo e nomi file 0.5.36

- **Titolo e permalink generati dall'AI**: il titolo del post non è più la prima parte del testo dell'idea, ma viene **riscritto dall'AI** in forma SEO-oriented; lo **slug/permalink** è proposto dall'AI. Aggiunti i campi JSON `title` e `slug` al contratto di generazione.
- **Immagini nel corpo più robuste**: oltre ai segnaposto `[[wpai-image: ...]]`, ora vengono intercettati anche i tag `<img>`/`<figure>` che l'AI può produrre (es. con prompt personalizzati che chiedono markup figure) e convertiti in **immagini reali**. Output con `<figure>` e `<img class="aligncenter">`.
- **Nome file immagini**: file e titolo in Libreria media derivano dalla **descrizione/alt della singola immagine**, non dal titolo dell'articolo. L'immagine in evidenza usa il nuovo campo `featured_image_alt` dell'AI e imposta anche il testo alternativo.

## Knowledge base OpenAI — file_search / RAG 0.5.35

Funzione **opt-in** ("Knowledge base OpenAI" in Impostazioni). Quando attiva, gli articoli vengono generati tramite la **Responses API di OpenAI** con il tool **file_search**, **ancorandoli ai documenti caricati nei Vector store** del tuo storage OpenAI (`platform.openai.com/storage`). Questo canale, quando configurato, viene **tentato per primo**; in caso di errore o assenza di configurazione c'è il **fallback automatico** al canale AI di WordPress (php_ai_client) e agli altri.

Per sicurezza la **chiave API non si salva nel database**: definiscila in `wp-config.php`

```php
define( 'WPAIP_OPENAI_API_KEY', 'sk-...' );
```

oppure forniscila con il filtro `wpai_publisher_openai_api_key`. Imposta poi uno o più **Vector Store ID** e, facoltativamente, il **Modello Responses API** (se vuoto usa il modello dei Parametri AI o un default). Filtri disponibili: `wpai_publisher_openai_model`, `wpai_publisher_openai_responses_body`. Lo **Stato sistema** mostra il canale `openai_responses` quando è pronto.

## Immagini reali nel corpo dell'articolo 0.5.34

Funzione **opt-in** ("Immagini nel corpo AI" in Impostazioni). Quando attiva, l'AI inserisce nel testo dei **segnaposto** nel formato `[[wpai-image: descrizione]]` nei punti in cui un'immagine aiuta davvero. Dopo la creazione della bozza il plugin **genera l'immagine reale** per ogni segnaposto, la **carica nella Libreria media** (con `alt` dalla descrizione) e **sostituisce il segnaposto con il vero `<img>`** — nessun placeholder lasciato nel testo. I segnaposto non elaborati o oltre il limite (**Numero massimo immagini nel corpo**, 0–10, default 3) vengono rimossi. Lo stile segue il "Prompt immagini" della Tipologia articolo. L'operazione è **non bloccante**: se la generazione fallisce, la bozza resta valida.

> Il formato del segnaposto viene comunicato **automaticamente** all'AI dal plugin quando la funzione è attiva: non devi aggiungere nulla al prompt. Puoi comunque scrivere nel "Prompt principale" della Tipologia indicazioni su **quante** immagini o **quali soggetti** preferisci.

## Contesto del sito, link interni e SEO 0.5.33

La generazione dell'articolo ora riceve il **contesto reale del sito**: i **tag esistenti** (che l'AI riusa quando pertinenti), le **categorie disponibili** (l'AI sceglie quelle coerenti scegliendo tra gli `category_ids` forniti) e gli **URL degli ultimi articoli pubblicati**, così da inserire **link interni** reali nel corpo del testo. L'AI restituisce un **oggetto JSON strutturato** (`html`, `tags`, `category_ids`, `meta_title`, `meta_description`) con fallback robusto all'HTML semplice. In fase di creazione bozza vengono scritti **Meta title e Meta description per All in One SEO Pack** (modello AIOSEO v4 con fallback su post meta `_aioseo_title`/`_aioseo_description`), in modo non bloccante. Filtri: `wpai_publisher_context_max_tags`, `wpai_publisher_context_max_links`, `wpai_publisher_seo_meta`.

## Programmazione creazione bozze 0.5.32

Nella pagina **Idee contenuto** puoi impostare **data e ora** e usare il pulsante **“Programma”**: l'idea passa allo stato *Programmata* (`scheduled_at`, schema DB 9) e un **cron ogni 5 minuti** accoda la generazione della bozza quando l'orario è raggiunto. Puoi comunque forzare subito la generazione con “Genera bozza”.

## Dashboard widget + elenco idee paginato 0.5.31

Aggiunto un **widget nella dashboard WordPress** ("WP AI Publisher — Idee contenuto") con contatori per stato, ultime idee con link e accesso rapido. L'**elenco "Ultime idee"** è ora a tutta larghezza, con righe più compatte e **paginazione** (filtro `wpai_publisher_ideas_per_page`, default 20).

## Escissione profonda adapter 0.5.30

Rimosso il vecchio motore **dry-run/strutturato** e il **probing speculativo** non più usato (generazione locale di fallback, registry/client AI ipotetici, validazione strutturata): `class-ai-provider-adapter.php` passa da **~3380 a ~1890 righe**. Eliminata la classe `Structured_Output_Validator` e gli helper privati di `Content_Ideas` legati al vecchio flusso. **Restano intatti** il flusso a chiamata singola (filtro → `php_ai_client` → Abilities API → AI Services → `wp_ai_generate_text`), la generazione immagini e la diagnostica. Nessuna modifica funzionale al percorso attivo. Codebase più pulita e stabile per i prossimi sviluppi.

## Test e CI 0.5.29

Aggiunti test unitari per `Classic_Content_Builder` (validazione pubblicabilità, normalizzazione HTML, placeholder), per i parametri AI e la decodifica immagini di `AI_Provider_Adapter`, e per la normalizzazione delle impostazioni. Il bootstrap di test (`tests/bootstrap.php`) è stato ampliato con stub WordPress; i test girano in CI tramite `composer test`.

## Snellimento workflow 0.5.28

Rimossa la **modalità avanzata** e il vecchio flusso **dry-run** dall'interfaccia: eliminati l'impostazione `workflow_mode`, i pulsanti *Esegui dry-run* / *Genera articolo completo*, i relativi handler e i metodi `run_dry_run` / `generate_full_article` / `save_full_article_output` (non più usati dal flusso a chiamata singola), più dead code in `Draft_Creator`. La creazione bozza resta interamente sul flusso single-call. L'escissione profonda del probing speculativo nell'adapter è rinviata a un passo dedicato e testabile (ancora intrecciata con Diagnostica AI e filtri pubblici legacy).

## Tipologie a prompt unico 0.5.27

La generazione dell'articolo è ora guidata dal solo **“Prompt principale”** della Tipologia. I campi legacy (tono, lunghezza, intento, livello lettore, sezioni obbligatorie, pattern vietati, checklist) sono usati **solo come fallback** per le tipologie create prima del prompt unico — nessuna migrazione distruttiva. Le 5 tipologie default per le nuove installazioni includono già un prompt unico pronto all'uso.

## Pulizia configurazione 0.5.26

Rimosse impostazioni legacy mai usate (limiti di costo giornaliero/mensile, `default_text_model`, `ai_provider_preference`) e alleggerita la pagina **Impostazioni**. La scelta del modello AI resta in **Impostazioni → Parametri AI → “Modello AI”**.

## Immagine in evidenza con l'AI 0.5.25

Dalla **0.5.25** il plugin può generare automaticamente l'**immagine in evidenza** della bozza. Attiva l'opzione in **Impostazioni → Parametri AI → “Immagine in evidenza AI”**: alla creazione della bozza, il plugin chiama `WordPress\AiClient\AiClient::prompt( $imagePrompt )->generateImage()` usando il **“Prompt immagini”** della Tipologia articolo (se vuoto, ne costruisce uno dal titolo), importa il file nella **Libreria media** e lo imposta come immagine in evidenza.

La funzione è **opt-in e non bloccante**: richiede un provider AI con generazione immagini (es. un modello immagini OpenAI configurato in AI Provider for OpenAI); se la generazione o l'import falliscono, la bozza viene comunque creata **senza** immagine e l'errore è registrato nei log (Stato sistema).

## UI/UX 0.5.24 — Tipologie articolo e Idee contenuto

**Tipologie articolo**: il form è stato semplificato in un **unico “Prompt principale”** (tono, struttura, lunghezza, regole e sezioni si descrivono qui in testo libero) più una **textarea dedicata “Prompt immagini”** (nuova colonna database `image_prompt`, schema 8) per la futura generazione delle immagini. La lista mostra Nome, Attiva, Prompt, Immagini e Categorie.

**Idee contenuto**: aggiunto il pulsante **Elimina** per la singola idea (la bozza collegata non viene rimossa), testi e avvisi ridondanti rimossi, e la sezione risultato è ora **“Risultato generazione”** senza i blocchi di debug non più necessari.

## Parametri AI configurabili 0.5.23

La versione **0.5.23** risolve l’errore `400 Unsupported parameter: 'temperature'` (i modelli “reasoning” come o1/o3/gpt‑5 non accettano `temperature`): ora la temperatura **non viene inviata** salvo che tu la imposti esplicitamente. È stata aggiunta la sezione **Impostazioni → Parametri AI** dove puoi configurare:

- **Modello AI**: ID del modello da richiedere (es. `gpt-4o-mini`); vuoto = modello predefinito del provider.
- **Timeout richiesta AI**: secondi di attesa (default 180).
- **Lunghezza massima output (token)**: limite per contenere i tempi (default 4000; 0 = nessun limite).
- **Temperatura**: 0–2; **lasciala vuota** con i modelli reasoning.

Tutti i valori sono sovrascrivibili via filtri (`wpai_publisher_ai_model`, `wpai_publisher_ai_http_timeout`, `wpai_publisher_ai_max_output_tokens`, `wpai_publisher_ai_temperature`). La scelta del modello viene applicata best-effort al PHP AI Client; se l’SDK non accetta l’ID indicato, viene usato il modello configurato nel provider AI.

## Generazione lenta vs rete 0.5.22

Se **“Verifica connettività OpenAI”** risulta *Raggiungibile* (es. HTTP 401 in poche centinaia di ms) ma la creazione bozza va comunque in timeout con `0 bytes received`, la causa è una **generazione troppo lenta** (modello lento o output lungo), non la rete: la risposta dell’endpoint `/v1/responses` arriva solo a generazione completata. Dalla **0.5.22** il timeout HTTP predefinito è **180 secondi** (`wpai_publisher_ai_http_timeout`) e l’output è limitato per contenere i tempi (`wpai_publisher_ai_max_output_tokens`, default 4000; `wpai_publisher_ai_temperature`, default 0.7). Per accelerare ulteriormente, scegli un modello più veloce (es. `gpt-4o-mini`) nelle impostazioni del plugin AI o riduci i token:

```php
add_filter( 'wpai_publisher_ai_http_timeout', fn() => 240 );
add_filter( 'wpai_publisher_ai_max_output_tokens', fn() => 2500 );
```

## Diagnostica connettività OpenAI 0.5.21

Se la generazione raggiunge OpenAI ma fallisce con `cURL error 28 … 0 bytes received` anche con timeout alti, il problema è la **connettività in uscita del server** (firewall hosting, proxy o DNS), non il plugin. Dalla **0.5.21**, in **Diagnostica AI** è disponibile il pulsante **“Verifica connettività OpenAI”**: esegue una richiesta leggera a `api.openai.com` e indica se il server raggiunge il provider (qualunque codice HTTP, anche `401`, conferma la connettività) o se le connessioni in uscita sono bloccate. In quest’ultimo caso il messaggio di errore della creazione bozza lo segnala esplicitamente e rimanda al test.

## Fix timeout generazione 0.5.20

La generazione via PHP AI Client raggiungeva OpenAI ma falliva con `cURL error 28: Operation timed out after 5000 milliseconds`: il timeout HTTP predefinito di WordPress (5s) è troppo breve per generare un articolo. Dalla **0.5.20** il timeout viene esteso a **90 secondi** (filtrabile con `wpai_publisher_ai_http_timeout`) **solo per la durata della richiesta di generazione**, poi ripristinato. Esempio per personalizzarlo:

```php
add_filter( 'wpai_publisher_ai_http_timeout', function () { return 120; } );
```

## Integrazione AI (generazione articolo)

WP AI Publisher genera l’articolo tramite il sistema AI di WordPress, provando in ordine questi canali:

1. il filtro `wpai_publisher_generate_article_from_idea` (integrazione personalizzata, ha priorità);
2. il **PHP AI Client ufficiale di WordPress** (`WordPress\AiClient\AiClient::prompt( $prompt )->generateText()`), incluso nello stack `WordPress/ai` e usato da **AI Provider for OpenAI**: usa il provider/modello configurato sul sito (es. OpenAI). **Questo è il canale consigliato e oggi funzionante** per chi ha installato quello stack;
3. la **WordPress Abilities API** (`wp_get_abilities`/`wp_get_ability`), se espone un’ability di generazione testo;
4. il plugin **AI Services** (`ai_services()`), se presente;
5. la funzione `wp_ai_generate_text()`, se presente.

Con **AI Provider for OpenAI** correttamente configurato (chiave API e modello), la creazione bozza usa automaticamente il canale 2 senza configurazione aggiuntiva.

Alcuni stack AI espongono **solo ability specifiche** (generazione immagini, classificazione, ridimensionamento contenuti, dati SEO) e **nessuna generazione di articoli/testo**, oppure richiedono permessi non disponibili durante l’esecuzione pianificata (WP-Cron senza utente). In questi casi la creazione bozza non può produrre testo: collega un generatore reale con il filtro qui sotto. Il dettaglio per-ability (nome, schema input, esito) è visibile in **Stato sistema → Dettaglio log critici interni**.

```php
add_filter(
    'wpai_publisher_generate_article_from_idea',
    function ( $result, $generation_context, $site_context, $prompt, $article_type ) {
        // $prompt contiene idea + istruzioni della Tipologia articolo
        // (incluse le sezioni richieste). Chiama il tuo generatore di testo
        // e restituisci l'articolo come HTML pulito per Editor Classico.
        $html = mia_funzione_di_generazione_testo( $prompt ); // tua integrazione

        // Tag consentiti: p, h2, h3, h4, ul, ol, li, strong, em, blockquote, code, pre, br, a.
        return array( 'html' => $html );
    },
    10,
    5
);
```

Restituendo `null` lasci il controllo all’adapter (che proverà gli altri canali). Restituendo `array( 'html' => ... )` (o una stringa HTML) la bozza viene creata da quel contenuto.

## Generazione via Abilities API più robusta 0.5.17

La versione **0.5.17** corregge la causa per cui la generazione tramite **WordPress Abilities API** non produceva contenuto: l’input dell’ability viene ora **derivato dal suo schema di input** (prima si usava un input fisso `prompt`/`dry_run_output` che falliva la validazione dello schema), vengono tentate più forme di input e più metodi (`execute`/`run`/`invoke`/`call`/`perform`), e gli eventuali `WP_Error` restituiti dall’ability vengono catturati e mostrati. Le ability di generazione testo non sono più escluse per la sola assenza di marcatori “read-only”: si escludono soltanto quelle con segnali distruttivi (pubblica, crea, elimina…). Per ogni ability pertinente la diagnostica registra nome, chiavi dello schema di input ed esito dell’invocazione, visibili in **Stato sistema → Dettaglio log critici interni**.

## Diagnostica generazione AI 0.5.16

La versione **0.5.16** rende trasparente *dove* si interrompe la creazione bozza. Ogni tentativo registra nei log interni quale integrazione AI è rilevata (classi e funzioni presenti) e l’esito di **ciascun canale di generazione**: filtro `wpai_publisher_generate_article_from_idea`, WordPress Abilities API, plugin **AI Services** (`felix-arntz/ai-services`) e `wp_ai_generate_text`. È stato aggiunto un canale che invoca direttamente AI Services in modo sicuro (qualsiasi incompatibilità dell’API viene catturata e diagnosticata, non blocca). In **Stato sistema** la riga “WordPress AI Client / API” indica cosa è rilevato e se esiste un canale di generazione compatibile, e la sezione “Dettaglio log critici interni” mostra una colonna **Dettaglio** con canale usato, integrazioni rilevate ed esiti per canale. Se un’AI risulta rilevata ma nessun canale produce contenuto, l’errore lo dice chiaramente e rimanda a Stato sistema.

## Validazione non bloccante 0.5.15

Dalla versione **0.5.15** la qualità dell’articolo non blocca più la creazione della bozza. I prompt e le istruzioni della Tipologia articolo (tono, lunghezza, sezioni obbligatorie, checklist, livello lettore) **guidano** la scrittura ma non impediscono la bozza: lunghezza minima, numero di H2, sezioni obbligatorie e frasi segnaposto diventano **note di qualità non bloccanti**, salvate sull’idea e visibili per la revisione editoriale. Restano bloccanti solo i controlli di **sicurezza**, già garantiti dalla sanitizzazione (allowlist `wp_kses`, rimozione di blocchi Gutenberg, script, iframe e style inline). Un articolo viene scartato solo se, dopo la sanitizzazione, risulta completamente vuoto. Di conseguenza l’errore “Il sistema AI non ha restituito un articolo pubblicabile” compare ora soltanto quando l’AI non produce contenuto reale.

## Creazione bozza semplificata 0.5.14

Dalla versione **0.5.14** il flusso di creazione bozza segue il percorso essenziale: **1)** inserisci l’idea contenuto, **2)** scegli la Tipologia articolo, **3)** richiedi la bozza. L’idea e i prompt della Tipologia vengono inviati al sistema AI di WordPress in **un’unica chiamata**, l’AI restituisce l’articolo completo in HTML pulito e il plugin crea direttamente la bozza, senza più il passaggio intermedio di dry-run JSON.

Le **sezioni obbligatorie** della Tipologia vengono iniettate nel prompt come scaletta richiesta all’AI e non bloccano più la pubblicabilità (restano un segnale di qualità nei log): questo risolve l’errore per cui la bozza falliva quando i titoli generati non corrispondevano esattamente alle sezioni configurate. Se non è attivo alcun sistema AI di WordPress, la creazione bozza non produce contenuti segnaposto: mostra un messaggio chiaro e lascia l’idea pronta a riprovare. I passaggi manuali (dry-run, genera articolo completo) restano disponibili in modalità avanzata.

## Fix 0.5.13

La versione **0.5.13** corregge il requisito minimo di WordPress, erroneamente impostato a 7.0 (versione inesistente) che impediva l’attivazione del plugin: ora è **6.5**, allineato in header plugin, `readme.txt`, Stato sistema e Bacheca. Sono inoltre state allineate le versioni di `wp-ai-publisher.php`, `readme.txt` e `README.md` e reso **atomico il claim dei job** in coda, così WP-Cron e l’esecuzione manuale non possono più elaborare lo stesso job due volte creando bozze duplicate.

Migliorie aggiuntive di manutenzione 0.5.13:

- **Performance letture idee**: il controllo della colonna `article_type_id` esegue le query `SHOW` al massimo una volta per richiesta (memo statico), eliminando l’overhead su ogni lettura.
- **Migrazione DB più economica**: `dbDelta` viene eseguito solo quando cambia lo schema, non a ogni bump di versione; la versione plugin resta comunque sincronizzata per la diagnostica.
- **Coda job più affidabile**: ogni esecuzione cron elabora un piccolo batch (filtro `wpai_publisher_jobs_per_run`, default 5) e si ripianifica finché restano job pendenti.
- **Pulizia codice legacy**: rimossi la classe CPT `Article_Types`, la vista `article-type-meta-boxes.php`, la costante `WPAIP_ENABLE_ARTICLE_TYPES` e il metodo `render_article_types_unavailable()` non più utilizzati.
- **Capability dedicata**: introdotta `manage_wp_ai_publisher`, filtrabile via `wpai_publisher_capability`, concessa agli amministratori in attivazione e upgrade. Consente di delegare il workflow editoriale ad altri ruoli senza concedere `manage_options`.
- **Iniezione dipendenze**: `Article_Type_Repository` è creato una sola volta e iniettato in `Admin`; `Draft_Creator` usa un accessor condiviso in `Content_Ideas`.
- **Disinstallazione opt-in**: nuova opzione “Elimina i dati alla disinstallazione” (default disattiva) e filtro `wpai_publisher_delete_data_on_uninstall`; il plugin conserva i dati salvo scelta esplicita.
- **Tooling di qualità**: aggiunti `composer.json`, `phpcs.xml.dist`, test PHPUnit di base in `tests/` e workflow CI in `.github/workflows/ci.yml` (lint PHP su 8.1–8.3, unit test, phpcs informativo).
- **Adapter AI più manutenibile**: le liste di rilevamento dell’integrazione AI (classi/funzioni indicatore, discovery modelli e abilities) sono state estratte in metodi pubblici dedicati e coperti da test, senza alcuna modifica di comportamento o degli hook esistenti.

WP AI Publisher è un plugin WordPress per preparare un workflow di pubblicazione assistita da AI usando il sistema AI di WordPress configurato sul sito.

## Settings cleanup 0.5.7

La versione **0.5.7** semplifica le impostazioni globali, sposta tono/tag/istruzioni specifiche sulle Tipologie Articolo e aggiunge categorie globali opzionali basate su ID WordPress con intersezione rispetto alle categorie della Tipologia.

La versione **0.5.5** aggiunge una pagina admin **Stato sistema** stabile e di sola lettura per controllare ambiente, integrazioni e configurazione tecnica senza chiamate esterne, generazione contenuti o azioni distruttive.

## Recovery mode 0.5.4

La versione **0.5.5** reintroduce le **Tipologie articolo** come entità interne del plugin basate sulla tabella custom `wpai_publisher_article_types`, senza registrare Custom Post Type, metabox, capability post custom o `map_meta_cap`.

Il workflow **Idee contenuto** usa `article_type_id` come riferimento alla tabella custom: è facoltativo per “Salva solo idea”, ma obbligatorio e validato come tipologia attiva per generare bozze. Questa scelta riduce il rischio di notice ripetuti `map_meta_cap` legati a capability meta del CPT durante il caricamento dell’admin.

La feature legacy basata su `WPAIP_ENABLE_ARTICLE_TYPES` resta disabilitata e non deve essere riattivata: il flusso 0.5.5 usa il nuovo flag `WPAIP_ENABLE_ARTICLE_TYPE_REPOSITORY`, attivo di default, e la tabella custom.

## Hotfix 0.5.3 e recovery

La versione **0.5.3** è una hotfix di recupero per siti che possono mostrare “Si è verificato un errore critico in questo sito” dopo un aggiornamento 0.5.x incompleto. La causa più probabile è l’esecuzione troppo precoce della creazione delle Tipologie articolo default durante il bootstrap, prima che classe repository o migrazione `article_type_id` siano disponibili in modo sicuro.

### Recupero sito dopo fatal error

1. Rinominare via FTP/SFTP la cartella `wp-ai-publisher` in `wp-ai-publisher-disabled`.
2. Accedere a `wp-admin` e verificare che il sito torni operativo.
3. Installare la hotfix 0.5.3 caricando la nuova cartella/ZIP del plugin.
4. Riattivare WP AI Publisher.
5. Aprire **WP AI Publisher > Stato sistema** e controllare tabella article_types, colonna `article_type_id`, tipologie attive e idee da riassegnare.
6. Aprire **Idee contenuto** e assegnare una Tipologia articolo attiva alle idee migrate, senza cancellare `dry_run_output` e senza cambiare stato idea.

### Cosa controllare dopo update

- Lo schema database deve aggiornarsi alla versione `7`.
- Le Tipologie articolo default vengono create una sola volta in admin sicuro tramite repository custom, mai tramite CPT.
- Idee con tipologia cancellata, cestinata, inattiva o inesistente mostrano il form **Assegna tipologia**.
- Se una Tipologia articolo non contiene categorie consentite, WP AI Publisher non assegna categorie suggerite dall’AI e lascia a WordPress l’eventuale categoria predefinita.


## Debug fatal error

Per diagnosticare problemi di bootstrap, admin menu o caricamento admin, aggiungere temporaneamente in `wp-config.php` prima della riga “That’s all, stop editing”:

```php
define( 'WP_DEBUG', true );
define( 'WP_DEBUG_LOG', true );
define( 'WP_DEBUG_DISPLAY', false );
define( 'SCRIPT_DEBUG', true );
define( 'WP_DISABLE_FATAL_ERROR_HANDLER', true );
```

Il log atteso è:

```text
wp-content/debug.log
```

Dopo la verifica, rimuovere o disattivare queste costanti su siti di produzione.

## Workflow idea → bozza

Dalla versione **0.5.0** il workflow principale è pensato per il sito con Editor Classico: l’utente scrive o programma un’idea e il plugin la porta fino alla bozza WordPress senza pubblicarla automaticamente.

### Modalità semplice

In modalità `simple`, il form “Nuova idea contenuto” mostra il pulsante primario **Crea bozza** e il pulsante secondario **Salva solo idea**. Il clic su **Crea bozza** esegue la pipeline idea → dry-run → `full_article` → validazione → approvazione → bozza. Se uno step fallisce, l’idea resta nello stato più utile per riprovare: `dry_run_failed` mostra **Riprova**, `dry_run_ready` o `approved` senza articolo completo mostra **Genera articolo**, mentre gli errori reali di `wp_insert_post()` restano `draft_failed`.

### Modalità avanzata

In modalità `advanced`, restano visibili i passaggi manuali: **Esegui dry-run**, **Genera articolo completo**, **Approva**, **Rifiuta**, **Crea bozza** e **Visualizza risultato**. Anche nel workflow avanzato, un’idea `approved` senza `full_article.html` non mostra **Crea bozza**: l’azione corretta è **Genera articolo**.


### Normalizzazione full_article

L’AI può restituire l’articolo completo sia come HTML già strutturato sia come testo semplice. WP AI Publisher normalizza sempre `full_article` in HTML pulito compatibile con Editor Classico prima di creare una bozza.

Il campo `content_outline` del dry-run guida la conversione: gli heading vengono riconosciuti come sezioni H2 anche quando sono lunghi, mentre il testo viene distribuito in paragrafi `<p>` sanitizzati. Se l’output non contiene titoli chiari, il plugin usa comunque l’outline per ricostruire sezioni H2 e paragrafi leggibili.

La bozza viene creata solo dopo normalizzazione e validazione: il contenuto deve avere almeno tre H2, almeno 300 parole, nessun blocco Gutenberg, nessuno script/iframe/style inline, nessun JSON grezzo, nessun placeholder editoriale e nessun prompt immagine. Il plugin non pubblica automaticamente e non scrive metadati AIOSEO in questa fase.

### Regola full_article obbligatorio

La bozza usa `dry_run_output.full_article.html` come fonte del `post_content`. Se `full_article.html` manca, l’errore è recuperabile: l’idea resta `approved` o `dry_run_ready`, non viene segnata come `draft_failed`, e l’admin mostra “Genera prima l’articolo completo, poi crea la bozza.”

### HTML Classic Editor

Il contenuto AI può arrivare come HTML o testo semplice. Prima del salvataggio viene normalizzato in HTML pulito per Editor Classico, con titoli `<h2>`, paragrafi `<p>` e liste consentite quando presenti. Il plugin blocca blocchi Gutenberg, script, iframe, style inline, JSON grezzo, prompt immagini, note interne e placeholder redazionali.

### Sicurezza editoriale

WP AI Publisher 0.5.0 continua a creare solo bozze o contenuti pending autorizzati dal flusso configurato. Non pubblica automaticamente, non genera immagini reali, non chiama OpenAI direttamente e non scrive metadati AIOSEO in questa fase.

## Tipologie di Articolo

Dalla versione **0.5.6** WP AI Publisher gestisce le **Tipologie articolo** come entità interne in tabella custom (`wpai_publisher_article_types`), gestibili da **WP AI Publisher → Tipologie articolo**, senza Custom Post Type.

Le Tipologie di Articolo definiscono struttura, prompt specifico, sezioni obbligatorie, pattern vietati, tono, lunghezza, intento di ricerca, livello lettore, categorie WordPress esistenti consentite, tag consigliati, regole SEO, regole di linking interno e checklist qualità.

Il **Contesto editoriale del sito** resta il quadro generale: descrive il sito, il pubblico, la nicchia e le regole comuni. La **Tipologia di Articolo** è invece l’istruzione specifica principale per generare un singolo contenuto e ha priorità più alta del contesto generale.

Il form **Idee contenuto** è stato semplificato: l’utente inserisce argomento principale, keyword principale, lingua e Tipologia articolo. I vecchi campi “Livello tutorial” e “Note editoriali” sono stati rimossi dal form perché ora appartengono alla Tipologia articolo, così il flusso resta più coerente e riutilizzabile.

Le categorie non vengono create dall’AI. Ogni Tipologia può selezionare solo categorie WordPress già esistenti; l’output AI può suggerire `category_ids`, ma il plugin li interseca con le categorie consentite e assegna solo termini esistenti.

## Hotfix upgrade 0.5.1

La versione **0.5.1** è una hotfix di recovery per aggiornamenti da 0.5.0 e versioni precedenti. Stabilizza il caricamento del plugin anche quando dati, colonne, Tipologie articolo o meta non sono ancora disponibili.

- Risolve possibili errori critici post-upgrade con controlli difensivi su classi, metodi, colonne database e CPT.
- Rende sicure le idee migrate senza Tipologia articolo: non eseguono dry-run o creazione bozza finché non viene assegnata una tipologia.
- Introduce l’assegnazione reale della Tipologia articolo alle idee migrate dalla schermata Idee contenuto.
- Non crea categorie nuove: le categorie suggerite dall’AI vengono applicate solo se esistono e sono esplicitamente consentite dalla Tipologia articolo.
- Stabilizza la validazione `full_article` passando la Tipologia articolo al builder Classic Editor e rispettando sezioni obbligatorie e pattern vietati.

## Stato sviluppo

Il plugin è in fase operativa controllata. Crea bozze WordPress reali solo dopo approvazione esplicita del dry-run. Non pubblica automaticamente, non genera immagini reali e non scrive metadati SEO definitivi.

## Articolo completo per bozza

Dalla versione **0.4.2** il workflow distingue chiaramente tre livelli di contenuto:

- **dry-run**: struttura editoriale, vincoli e dati di pianificazione usati per controllare l’idea prima della bozza;
- **classic_editor_preview**: anteprima strutturale in HTML pulito utile per leggere la scaletta nell’admin;
- **full_article**: articolo finale in HTML pulito compatibile con Editor Classico, usato come `post_content` della bozza WordPress.

Il contenuto finale salvato in `full_article.html` non include istruzioni interne, pubblico target, tono di voce, regole editoriali, note di validazione, JSON grezzo, prompt immagini o placeholder come “Spiegare”, “Descrivere”, “Indicare” e “Mostrare”.

La bozza viene creata solo quando `full_article.html` supera la validazione di pubblicabilità: niente blocchi Gutenberg, niente script/iframe/style inline, almeno tre sezioni H2, almeno 300 parole per impostazione predefinita e nessuna nota interna visibile. Il plugin continua a creare esclusivamente bozze o contenuti pending autorizzati dal flusso: **non pubblica nulla automaticamente** e non scrive metadati AIOSEO in questa fase.


## Contesto editoriale riutilizzabile

Dalla versione **0.3.7** WP AI Publisher non è legato a wptutorial.ai, a tutorial WordPress o a una singola struttura editoriale. La pagina **Impostazioni** include una sezione **Contesto editoriale del sito** per configurare profilo, descrizione, nicchia, pubblico, tono, lingua, categorie consentite, tag preferiti, argomenti esclusi, regole editoriali, claim vietati e formato contenuto preferito.

Il contesto guida:

- prompt dei dry-run AI tramite sistema AI di WordPress;
- fallback locale quando non è disponibile un output AI utilizzabile;
- anteprima HTML compatibile con Classic Editor;
- futura creazione controllata di bozze.

Il target editoriale corrente resta **Editor Classico**: il plugin produce HTML pulito e non genera blocchi Gutenberg. La creazione 0.5.0 può produrre **bozze** o contenuti **in attesa di revisione**, ma non pubblicazioni automatiche.


## Pubblico target dal contesto editoriale

Dalla versione **0.4.1** il pubblico target non viene più richiesto per ogni nuova idea contenuto. Il dry-run legge il valore globale da **Impostazioni → Contesto editoriale del sito → Pubblico target predefinito** e lo usa come priorità per costruire il payload editoriale.

Le singole idee restano più semplici: argomento, keyword, lingua, livello tutorial e note editoriali. Le vecchie idee che hanno ancora un valore `target_audience` mantengono quel dato solo come fallback di retrocompatibilità se il contesto editoriale non contiene un pubblico predefinito. Eventuali override specifici potranno essere aggiunti in futuro come opzione avanzata.

## Sicurezza safety signals

Dalla versione **0.4.1** il matching dei safety signal delle WordPress Abilities normalizza underscore, spazi, trattini e altri separatori non alfanumerici come equivalenti. Un signal come `create_post` intercetta quindi metadata scritti come `create post`, `create-post`, `create/post`, `create.post` o `create:post`.

Il controllo usa boundary su parole complete e non substring ambigue: parole editoriali come `editorial` o `editing` non vengono bloccate solo perché contengono frammenti simili a segnali generici. Le abilities non sicure non vengono invocate durante il dry-run.

## Target editoriale: Editor Classico

Dalla versione **0.3.2** il target editoriale principale è l’**Editor Classico** di WordPress. Il plugin non deve produrre blocchi Gutenberg, commenti `<!-- wp:... -->` o serializzazione a blocchi.

La bozza WordPress usa `post_content` con HTML pulito e sicuro, ad esempio paragrafi, titoli `h2`/`h3`, liste e altri tag consentiti da allowlist. L’anteprima del dry-run passa da sanitizzazione dedicata e viene mostrata nell’admin come contenuto compatibile con Classic Editor.

AIOSEO sarà gestito separatamente in una fase successiva e non viene scritto in questa versione. Le immagini saranno integrate più avanti tramite Media Library, senza generazione reale nella fase 0.5.0.

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
- pagina Diagnostica AI;
- salvataggio idee editoriali in tabella dedicata;
- dry-run articolo con output JSON validabile, visualizzazione leggibile e anteprima HTML per Editor Classico;
- migrazione database durante aggiornamento plugin;
- approvazione dry-run;
- creazione bozza WordPress da dry-run approvato;
- collegamento tra idea contenuto e post bozza;
- link “Modifica bozza” nell’admin.

## Regole operative

- `wp-ai-publisher.php`, `readme.txt` e `README.md` devono essere sempre aggiornati.
- Ogni micro o macro modifica deve aggiornare il changelog.
- Le voci **Impostazioni** e **Stato sistema** devono restare sempre alla fine del menu del plugin.
- Le altre voci del menu devono essere ordinate per importanza d’uso. Ordine attuale: Bacheca, Idee contenuto, Diagnostica AI, Coda job, Impostazioni, Stato sistema.
- Il plugin deve restare funzionante anche se i plugin terzi consigliati non sono installati o non sono attivi.
- Le chiamate AI future dovranno passare solo dall’adapter centrale.


## Creazione bozza WordPress

Dalla versione **0.5.0** WP AI Publisher introduce la prima creazione reale di contenuto WordPress con un flusso a due passaggi:

1. generare e verificare un dry-run;
2. approvare il dry-run;
3. cliccare **Crea bozza**.

La bozza viene creata solo se l’idea è in stato **Approvata** e contiene un output `classic_editor_preview.html` valido. Il plugin usa `wp_insert_post()` con `post_type: post`, titolo, slug, excerpt e `post_content` HTML sanificato per **Editor Classico**. Non genera blocchi Gutenberg, non inserisce commenti `<!-- wp:... -->` e rifiuta contenuti con segnali Gutenberg.

Lo stato post è prudente:

- `draft` resta `draft`;
- `pending` resta `pending`;
- `publish` viene convertito in `draft` in 0.5.0, salvo costante di sviluppo esplicita `WPAIP_ALLOW_DIRECT_PUBLISH`.

La pubblicazione automatica resta disabilitata. La creazione assegna categorie e tag sanificati, rispetta le categorie consentite nel contesto sito, limita i tag e collega l’idea alla bozza con `draft_post_id`, `draft_status` e `draft_created_at`. Se una bozza collegata esiste già, il plugin non crea duplicati e mostra il link **Modifica bozza**.

## Sicurezza Abilities e diagnostica

Le ability non sicure non vengono invocate durante il dry-run. Il matching dei segnali pericolosi usa token o boundary regex e non substring generiche: parole innocue come `editorial`, `editing summary`, `credit` o `mediazione` non devono essere bloccate solo perché contengono frammenti come `edit` o `media`.

La diagnostica separa i valori logici dalle label tradotte: ad esempio una riga ability espone booleani come `safe_for_dry_run_bool`, `generation_candidate_bool`, `dangerous_signals_bool`, `read_only_bool` e `invocable_bool`, mentre la view mostra label localizzate come “Sì” o “No”. Le stringhe localizzate non sono usate per decidere flussi interni.

## Compatibilità filtri dry-run

Dalla versione **0.3.5** il filtro consigliato per fornire output strutturato al dry-run è `wpai_publisher_generate_structured_content_dry_run`. Questo hook riceve il risultato iniziale `null`, il payload normalizzato e lo schema richiesto, quindi permette alle integrazioni WordPress AI di restituire JSON o array già conformi.

Per compatibilità con integrazioni create nella versione **0.3.0**, resta supportato anche il filtro legacy `wpai_publisher_structured_content_dry_run`. Il plugin lo richiama solo se il nuovo hook non produce un output utilizzabile: in questo modo le integrazioni aggiornate restano prioritarie e quelle esistenti continuano a funzionare senza causare fallback locale non necessario.

Il filtro legacy riceve un payload compatibile con la versione 0.3.0: se il chiamante moderno non include `idea`, WP AI Publisher ricostruisce `idea.topic`, `idea.keyword`, `idea.language`, `idea.target_audience`, `idea.tutorial_level` e `idea.notes` a partire dal payload normalizzato. Entrambi gli hook devono restituire solo dati strutturati per anteprima e validazione: il plugin non crea post, non crea bozze, non pubblica contenuti e non chiama OpenAI direttamente.

## WordPress Abilities API

Dalla versione **0.3.6**, WP AI Publisher consolida l’integrazione con la WordPress Abilities API quando disponibile nel runtime WordPress 7. Il plugin usa `wp_get_abilities()` per leggere le ability registrate e `wp_get_ability()` per ottenere l’istanza invocabile, sempre con chiamate protette e senza generare fatal error.

Le istanze `WP_Ability` vengono lette tramite getter (`get_name()`, `get_label()`, `get_description()`, `get_category()`, `get_input_schema()`, `get_output_schema()` e `get_meta()`) quando disponibili, con fallback difensivo su array o proprietà pubbliche solo per metadati scalari sicuri. Gli schema vengono usati solo per dedurre parole chiave e input compatibili; non vengono mostrati completi nella diagnostica.

WP AI Publisher non chiama API OpenAI dirette, non salva chiavi e non contiene un client OpenAI custom. L’output generato da un’ability reale è marcato come `source: wordpress_ai` e viene distinto dal fallback locale (`source: local_fallback`) nell’admin, nella qualità anteprima e nelle note di revisione.

## Sicurezza WordPress Abilities

Non tutte le WordPress Abilities sono sicure per un dry-run editoriale: alcune possono creare post, caricare media, aggiornare opzioni, inviare email o chiamare servizi esterni. Dalla versione **0.3.8** WP AI Publisher non invoca più abilities arbitrarie solo perché nome, descrizione o schema contengono parole generiche come `generate`, `content`, `title` o `summary`.

Il dry-run può invocare solo abilities che rientrano in almeno una regola sicura:

- nome presente nella allowlist **Impostazioni > Sicurezza Abilities AI**;
- nome aggiunto dal filtro `wpai_publisher_safe_ai_ability_names`;
- decisione esplicita del filtro avanzato `wpai_publisher_is_ability_safe_for_dry_run`;
- metadata compatibili con read-only / non destructive e senza segnali pericolosi.

Esempio allowlist da codice:

```php
add_filter(
  'wpai_publisher_safe_ai_ability_names',
  function ( $names ) {
      $names[] = 'nome/ability/testuale/sicura';
      return $names;
  }
);
```

Esempio filtro avanzato:

```php
add_filter(
  'wpai_publisher_is_ability_safe_for_dry_run',
  function ( $safe, $ability, $metadata ) {
      if ( isset( $metadata['name'] ) && 'nome/ability/testuale/sicura' === $metadata['name'] ) {
          return true;
      }
      return $safe;
  },
  10,
  3
);
```

Se nessuna ability sicura è disponibile, l’adapter restituisce un errore diagnostico utile e il workflow può usare il fallback locale controllato quando abilitato dal payload. Questo evita esecuzioni con effetti collaterali durante test e anteprime.

## Stato post dopo generazione

Il contesto editoriale permette di scegliere lo stato post previsto per la creazione controllata:

- **Bozza** (`draft`) — valore predefinito e più sicuro;
- **In attesa di revisione** (`pending`) — previsto per workflow editoriali con revisione;
- **Pubblicato** (`publish`) — selezionabile come intenzione futura, ma in 0.5.0 viene convertito in `draft` salvo costante di sviluppo esplicita.

Nella fase 0.5.0 il plugin crea solo bozze o post in attesa di revisione da dry-run approvati. L’eventuale pubblicazione automatica richiederà una fase futura, conferma esplicita e controlli dedicati.

## Diagnostica AI

La sezione **WP AI Publisher > Diagnostica AI** serve a scoprire cosa espone davvero il sistema AI WordPress installato sul sito prima di implementare un’integrazione definitiva con il connector OpenAI già configurato. È una pagina di debugging runtime: non inventa un’integrazione, ma elenca ciò che WordPress, plugin AI, connector, abilities, classi PHP e route REST rendono disponibile nel processo corrente.

La diagnostica rileva:

- funzioni PHP note o probabili legate ad AI, abilities, connector, models ed experiments;
- classi PHP probabili del layer WordPress AI, AI Services e registri ability;
- REST route registrate che contengono keyword AI o di generazione, senza chiamarle automaticamente;
- option WordPress potenzialmente collegate ad AI, sempre mascherate se il nome o il valore suggeriscono chiavi, token, secret, password, credenziali, bearer, auth, API o OpenAI;
- plugin attivi collegati ad AI, OpenAI, connector, services, abilities, Git Updater, AIOSEO, Classic Editor e Disable Gutenberg;
- esperimenti AI deducibili dalle option rilevate;
- possibili percorsi di generazione, distinguendo `available`, `maybe` e `unavailable`.

La pagina non legge né mostra chiavi API, non salva token, non salva risultati del test nel database, non crea post, non crea bozze, non pubblica contenuti, non genera immagini, non chiama endpoint REST automaticamente e non usa Gutenberg.

Il pulsante **Esegui test AI controllato** è manuale, protetto da nonce e disponibile solo agli amministratori con `manage_options`. Quando viene premuto, il plugin tenta soltanto funzioni o client locali già rilevati, con un prompt brevissimo che richiede JSON valido. Il test non chiama OpenAI direttamente e mostra nella pagina solo path usato, esito, tipo risposta ed estratto mascherato fino a 500 caratteri.

Se il sistema AI WordPress è presente ma non espone una funzione o un client invocabile in modo sicuro, WP AI Publisher può continuare a cadere nel fallback locale durante il dry-run. In quel caso la pagina consiglia di usare Abilities Explorer per individuare la callback reale oppure di registrare un bridge tramite il filtro `wpai_publisher_generate_structured_content_dry_run`, mantenendo il filtro legacy `wpai_publisher_structured_content_dry_run` per compatibilità.

## Privacy diagnostica

La pagina **Diagnostica AI** è pensata per trovare integration path sicuri senza esporre segreti. Dalla versione **0.3.5** la tabella delle option mostra solo nome option, autoload, tipo, lunghezza, anteprima breve, indicatore sensibile e indicatore mascherata.

La diagnostica:

- non mostra chiavi, token, bearer, password, credential, license o valori collegati a OpenAI/API;
- non mostra email complete e sostituisce i pattern email con un valore mascherato;
- non mostra JSON lunghi di array o object, ma solo tipo e lunghezza stimata;
- elenca fino a 50 abilities WordPress rilevate per aiutare a scegliere un bridge sicuro senza chiamare endpoint REST automaticamente.

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

### 0.5.70
- Import idee: categorie a 2 livelli "PRINCIPALE | sub1; sub2" con creazione automatica della gerarchia (opzionale); nuove colonne prompt immagine (sola copertina, priorità sulla Tipologia) e prompt social Facebook/Instagram/LinkedIn (salvati sulla bozza, usati da FB/IG alla pubblicazione, LinkedIn in arrivo). Campi modificabili anche da "Modifica idea". Schema 12 non distruttivo, retrocompatibile.

### 0.5.69
- Controllo accessi: regola impostabile anche per le singole guide AI (dalla pagina "Modifica guida"); le modifiche alla regola di una categoria/tag si propagano subito ai contenuti collegati (ricalcolo su edited_term/delete_term, batch a scrittura singola).

### 0.5.68
- Controllo accessi: restrizione della visualizzazione dei contenuti per login/ruolo (post, pagine, CPT, categorie/tag, voci di menu). Modalità: Tutti (default), Solo registrati, Ruoli specifici; admin vede tutto. Indice precalcolato (autoload), applicazione a strati (template_redirect, pre_get_posts, menu, get_terms, REST, sitemap) senza meta_query per richiesta; non loggato → login area membri, ruolo non autorizzato → pagina dedicata; cache full-page esclusa sui contenuti riservati. Nuova pagina admin "Controllo accessi".

### 0.5.67
- Guide AI: modifica admin della guida creata (titolo + contenuto) con pulsante "Modifica" (lista Richieste guide e pagina pubblica) e pagina di editor dedicata, con sync del result_html nell'area membri. Card "Articoli per la tua guida" ridisegnate in stile rivista (badge categoria, data, titolo, estratto, CTA) con hover/zoom.

### 0.5.66
- Bacheca: area in evidenza con il numero di Idee create (+ Idee programmate e Bozze create), con riquadro principale cliccabile verso Idee contenuto.

### 0.5.65
- Area membri: [wpai_guide_login] mostra "Esci" se loggato; [wpai_guide_account] visibile solo se loggato. Pagina guida più larga e card "Articoli per la tua guida" allineate (3/2/1). Feedback di salvataggio come toast fisso sempre visibile.

### 0.5.64
- Area membri: UI flat moderna per registrazione/login/area personale; flusso "Salva la tua guida" con auto-salvataggio dopo registrazione/login (intent), salvataggio inline per utenti loggati, messaggi chiari ("Crea il tuo account e salva le tue guide") e area personale a card con stato vuoto e CTA.

### 0.5.63
- Pagina guida: rimossa la post-navigation del tema (link ad altre guide); pulsanti stampa/condivisione sia in alto (accanto alla data) sia in fondo; pulsante "Salva" evidenziato che punta alla pagina di login dell'area membri; fix allineamento card "Articoli per la tua guida" in righe da 3 (minmax(0,1fr)).

### 0.5.62
- Assistente Guide AI: secondo testo segnaposto in loop con effetto typewriter; pagina guida pubblica migliorata (fino a 6 "Articoli per la tua guida", pulsanti stampa/WhatsApp sotto la guida, data di creazione formattata e localizzata, blocco correlati unico).

### 0.5.61
- Assistente Guide AI: bagliore sfumato dietro la hero + gradiente del titolo reso robusto (anti-override del tema); le risposte veloci con pagina già creata rimandano alla pagina esistente invece di rigenerare; placeholder del campo animato in stile typewriter.

### 0.5.60
- Risposte veloci: "Etichetta chip" personalizzabile per ogni richiesta (se vuota usa la richiesta accorciata); al clic invia comunque la richiesta completa.

### 0.5.59
- Assistente Guide AI: shortcode ridisegnato in stile "hero" (eyebrow, titolo gradiente azzurro→viola, testo introduttivo, prompt a pillola con send circolare, value props) con nuovi campi testo nelle impostazioni; "risposte veloci" selezionabili con una spunta nella lista Richieste guide e mostrate come chip cliccabili sotto il campo di ricerca.

### 0.5.58
- Ultime idee: filtri e ordinamento (stato/tipologia/categoria; ordina per creazione, programmazione o aggiornamento) e colonna Categorie. Telegram: link alla bozza sempre presente (fix get_edit_post_link in cron) e messaggio inviato solo a bozza completa, dopo l'inserimento delle immagini (nuovo hook wpai_publisher_idea_draft_finalized).

### 0.5.57
- Modifica idea: le categorie associate vengono mostrate anche per le idee importate/create con la 0.5.55 (fallback dal vecchio meccanismo a opzione); compaiono in modifica, nell'elenco e alla generazione, e vengono migrate sul campo dell'idea al salvataggio.

### 0.5.56
- Idee contenuto: categorie come campo dell'idea con UI a tag di WordPress (creazione/modifica, precompilate in modifica), salvate sull'idea e inviate all'AI oltre che assegnate alla bozza; import CSV allineato; rimosso il campo "Keyword principale" (sostituito da Categorie nell'elenco).

### 0.5.55
- Importazione massiva idee: nuova colonna "Categorie" (nomi separati da virgola, categorie esistenti) che forza le categorie sulla bozza al posto della scelta dell'AI; nomi inesistenti ignorati e segnalati; CSV di esempio aggiornato.

### 0.5.54
- Idee contenuto: importazione massiva da CSV (pulsante in basso a destra) con CSV di esempio scaricabile, campi Argomento/Lingua/Tipologia/Programma; tutte le idee importate vanno in programmazione, riepilogo con errori per riga, notifica Telegram alla creazione di ogni bozza, e nuova azione "Modifica" sull'elenco idee.

### 0.5.53
- Knowledge base OpenAI: il test dello storage rileva l'inserimento errato di un ID file (file-...) o di un valore non valido al posto di un Vector Store ID (vs_...) e spiega come correggere.

### 0.5.52
- Assistente Guide AI — Area membri front-end: ruolo dedicato "Membro Guide", shortcode `[wpai_guide_register]`/`[wpai_guide_login]`/`[wpai_guide_account]`, salvataggio reale delle guide nell'area utente, blocco accesso a wp-admin per i membri. Nuova sezione impostazioni con selezione pagine. Aggiunta frase d'attesa "Ancora un momento, grazie per la pazienza…".

### 0.5.51
- Assistente Guide AI: pulsante invio a icona circolare con spinner, testo d'attesa animato dentro il campo input (fix icona/cursore visibili prima dell'invio), tre pulsanti finali (PDF/WhatsApp/Salva) come icone circolari con tooltip, link alla pagina pubblica in Richieste guide, e redirect con avviso configurabile per i link di guide eliminate (verso la "Pagina del generatore").

### 0.5.50
- Assistente Guide AI: cancellazione automatica delle pagine pubbliche delle guide con retention configurabile dal pannello ("Cancella le pagine guida dopo (giorni)", 0 = mai), eseguita da una pulizia giornaliera WP-Cron. Le richieste restano archiviate.

### 0.5.49
- Assistente Guide AI: pagina pubblica per ogni guida (CPT "Guida AI" noindex/nofollow) con URL permanente condivisibile (usato da WhatsApp), opzionale dalle impostazioni; toggle per usare anche lo storage OpenAI (vector store / file_search) come grounding aggiuntivo, con fallback automatico alla ricerca WP.

### 0.5.48
- Assistente Guide AI: ricerca articoli con fallback per parole chiave (recall migliore sulle domande in linguaggio naturale), prompt anti-troncamento, stima 1 token ≈ 1 parola + costo indicativo OpenAI, azione "Visualizza" per il dettaglio completo della guida in Richieste guide, loader che scorre in vista dopo l'invio.

### 0.5.47
- Assistente Guide AI: UI in stile chat (Claude/ChatGPT), loader con effetto scrittura, articoli correlati come card 3 per riga ("Articoli per la tua guida"), pulsanti "Invia su WhatsApp" e "Salva la tua guida" (anteprima futura registrazione), lunghezza max a quantità libera con stima parole/minuti di lettura.

### 0.5.46
- Assistente Guide AI: fix "Sessione scaduta" al primo invio per utenti loggati (la richiesta REST ora invia l'header `X-WP-Nonce`). Gli admin ignorano limiti anti-abuso e cache per poter testare liberamente.

### 0.5.45
- Assistente Guide AI: shortcode pubblico che genera guide personalizzate ancorate ai contenuti del sito, con articoli consigliati reali, export PDF lato client, link interni validati (+ whitelist esterni), limiti anti-abuso/token, cache e archivio richieste convertibili in idee.

### 0.5.44
- Integrazione Instagram: pubblicazione automatica dell'immagine in evidenza su account Business alla pubblicazione (casella per-articolo, background, anti-duplicato, flusso container + media_publish). Caption da template o AI; immagine obbligatoria; link come testo. Token via costante (fallback a quello di Facebook). Guida ai prerequisiti e pulsante di verifica connessione.

### 0.5.43
- Integrazione Facebook: condivisione automatica su Pagina alla pubblicazione (casella per-articolo, background, anti-duplicato). Testo da template o AI; modalità link/foto. Token via costante. Pulsante di verifica connessione.

### 0.5.42
- Telegram interattivo: pulsanti per scegliere Tipologia articolo e Categorie (multi-selezione) prima di generare; categorie forzate sulla bozza. Nuova opzione "Scelta interattiva".

### 0.5.41
- Knowledge base OpenAI: pulsante "Testa accesso allo storage OpenAI" (raggiungibilità vector store + file indicizzati + verifica uso file_search). L'uso dei documenti è automatico con la spunta file_search attiva.

### 0.5.40
- Telegram: pulsante "Invia istruzioni su Telegram" (recapita le istruzioni d'uso alle chat autorizzate) e riquadro istruzioni di configurazione/uso nelle Impostazioni.

### 0.5.39
- Telegram: pulsanti "Registra webhook" e "Verifica stato webhook" in Impostazioni (nessun terminale necessario); esito mostrato come avviso.

### 0.5.38
- Integrazione Telegram: messaggio al bot → idea + bozza (async via WP-Cron) con risposta e link alla bozza.
- Endpoint REST autenticato (secret token + allowlist chat); credenziali da costanti/filtri, mai nel DB.
- Nuove impostazioni Telegram e refactor: `Content_Ideas::create_idea_programmatic()` per la creazione idee da chiamanti fidati.

### 0.5.37
- Immagine in evidenza generata per prima; immagini nel corpo salvate in modo incrementale per evitare la perdita del lavoro in caso di timeout.
- Limiti di runtime alzati durante la generazione immagini.

### 0.5.36
- Titolo e permalink generati dall'AI (campi JSON `title`/`slug`); il titolo non deriva più dal testo grezzo dell'idea.
- Immagini nel corpo: intercettati anche `<img>`/`<figure>` emessi dall'AI e convertiti in immagini reali; markup `<figure>` + `<img class="aligncenter">`.
- Nome file e titolo media derivati dalla descrizione/alt della singola immagine; immagine in evidenza con `featured_image_alt` e alt impostato.

### 0.5.35
- Canale opt-in OpenAI Responses API con file_search: articoli ancorati ai Vector store dello storage OpenAI (RAG), con fallback automatico ai canali esistenti.
- Chiave API letta da costante `WPAIP_OPENAI_API_KEY` o filtro `wpai_publisher_openai_api_key` (mai salvata nel DB).
- Nuove impostazioni: knowledge base on/off, Vector Store ID, modello Responses API. Filtri `wpai_publisher_openai_model`, `wpai_publisher_openai_responses_body`.

### 0.5.34
- Immagini reali nel corpo dell'articolo: segnaposto `[[wpai-image: descrizione]]` generati dall'AI e sostituiti con immagini vere caricate in Libreria media.
- Nuove impostazioni opt-in: "Immagini nel corpo AI" e "Numero massimo immagini nel corpo" (0–10, default 3).
- Segnaposto non elaborati o in eccesso rimossi automaticamente; funzione non bloccante.

### 0.5.33
- Contesto del sito passato all'AI: tag esistenti da riusare, categorie disponibili (scelta per ID) e URL reali degli articoli pubblicati per i link interni.
- Output AI strutturato in JSON (`html`, `tags`, `category_ids`, `meta_title`, `meta_description`) con fallback all'HTML semplice.
- Scrittura Meta title/description per All in One SEO Pack (modello AIOSEO v4 + fallback post meta), non bloccante.
- Nuovi filtri: `wpai_publisher_context_max_tags`, `wpai_publisher_context_max_links`, `wpai_publisher_seo_meta`.

### 0.5.7
- Semplificata la pagina Impostazioni per separare contesto globale e istruzioni delle Tipologie Articolo.
- Aggiunte categorie globali opzionali con checkbox e controllo di intersezione con le Tipologie Articolo.

### 0.5.6

- Corretta la normalizzazione delle Tipologie articolo per mantenere i campi editoriali multilinea come testo libero.
- Allineati i formati SQL ai dati salvati e ampliati i campi editoriali brevi a `TEXT`.
- Reso il form Tipologie articolo completamente libero per istruzioni editoriali e aggiunti testi di aiuto.
- Mantenuto il fallback di creazione bozze quando il repository Tipologie articolo è disabilitato.

### 0.5.3

- Hotfix bootstrap/admin menu per prevenire fatal error.
- Rimossa creazione Tipologie articolo dal bootstrap database.
- Spostata creazione tipologie default in `admin_init` sicuro.
- Eliminato uso di `get_page_by_title`.
- Aggiunti helper sicuri per `Article_Types`.
- Reso admin menu difensivo se CPT o classe non sono disponibili.
- Migliorata gestione tipologie inattive/cancellate nelle idee contenuto.
- Aggiunte istruzioni debug nel README.

### 0.5.1

- Hotfix per errore critico dopo aggiornamento 0.5.0.
- Rafforzata sicurezza upgrade, migrazione `article_type_id`, assegnazione tipologie migrate e validazione full article con Tipologia articolo.
- Corretto comportamento categorie: nessuna categoria AI viene assegnata se la Tipologia articolo non configura categorie consentite.

### 0.5.0

- Aggiunte Tipologie di Articolo come tabella custom, senza CPT `wpai_article_type`.
- Semplificato il form Idee contenuto con selezione obbligatoria della Tipologia.
- Aggiunto `article_type_id` alle idee e aggiornato schema database a 5.
- Bloccata la creazione di nuove categorie da output AI: sono assegnate solo categorie esistenti consentite.
- Rimossa l’impostazione Aggiornamenti da GitHub dalle impostazioni principali.


### 0.4.2

- Aggiunta generazione articolo completo da dry-run approvato.
- Distinta anteprima strutturale da contenuto finale per bozza.
- La bozza usa `full_article.html` quando disponibile.
- Rimosse istruzioni interne, pubblico, tono e regole editoriali dal corpo della bozza.
- Aggiunta validazione articolo pubblicabile per Editor Classico.
- Aggiunto pulsante “Genera articolo completo”.
- Bloccata la creazione di bozze con placeholder editoriali.
- Rafforzati i segnali distruttivi standalone per WordPress Abilities.
- Confermata assenza di pubblicazione automatica.


### 0.5.0

- Aggiunta creazione bozza WordPress da dry-run approvato.
- Aggiunto flusso Approva dry-run / Crea bozza.
- Aggiunta classe `Draft_Creator`.
- Aggiunta relazione tra idea contenuto e post bozza.
- Aggiunta sanitizzazione HTML per Editor Classico.
- Aggiunta assegnazione categorie e tag.
- Aggiunta protezione contro creazione duplicata della bozza.
- Pubblicazione automatica non attiva in questa fase.
- Corretto `default_tone` con chiave valida `chiaro_didattico_e_operativo`.
- Rafforzato safety matching delle WordPress Abilities per evitare falsi positivi su parole come `editorial`/`editing`.
- Corretta la diagnostica AI per usare booleani reali invece di label localizzate come “Sì”.

### 0.3.8

- Rafforzata la sicurezza dell’invocazione WordPress Abilities API.
- Aggiunta allowlist per abilities AI sicure nel dry-run.
- Evitata l’esecuzione arbitraria di abilities con possibili effetti collaterali.
- Aggiunti filtri `wpai_publisher_safe_ai_ability_names` e `wpai_publisher_is_ability_safe_for_dry_run`.
- Migliorata diagnostica delle abilities con indicazione sicurezza.
- Aggiunta opzione “Pubblicato” nello stato post dopo generazione.
- Il default resta “Bozza” e la pubblicazione automatica non è ancora attiva.

### 0.3.7

- Aggiunte impostazioni di contesto editoriale del sito.
- Reso il plugin più adattabile a siti e nicchie diverse.
- Aggiunta configurazione per nicchia, pubblico, tono, lingua, categorie, tag e regole editoriali.
- Impostato Editor Classico come target corrente configurabile.
- Impostato flusso futuro di generazione post su Bozza o In attesa di revisione, senza pubblicazione automatica.
- Aggiornato prompt dry-run per usare il contesto sito.
- Migliorato fallback locale per contesti non WordPress.

### 0.3.5
- Corretto payload del filtro legacy `wpai_publisher_structured_content_dry_run`.
- Ripristinata piena compatibilità con integrazioni 0.3.0.
- Rimosso falso positivo sul termine “passaggio” nella validazione anteprima.
- Distinte note gravi e note lievi nella validazione Classic Editor.
- Aggiunto primo bridge verso WordPress Abilities API tramite `wp_get_abilities` e `wp_get_ability`.
- Migliorata privacy della pagina Diagnostica AI.
- Mascherate email, token, chiavi e options sensibili.
- Aggiunta sezione Abilities WordPress rilevate.
- Normalizzate impostazioni obsolete legate a OpenAI diretto.

### 0.3.4
- Aggiunta pagina Diagnostica AI.
- Aggiunto rilevamento runtime di funzioni, classi, REST route, options e plugin collegati al sistema AI WordPress.
- Aggiunto test AI controllato eseguibile manualmente dall’amministratore.
- Aggiunta diagnostica dei possibili percorsi di generazione AI.
- Aggiunta sezione bridge manuale per collegare il connector AI reale.
- Nessuna chiave API viene mostrata o salvata.
- Nessuna chiamata OpenAI diretta.

### 0.5.5
- Aggiunta pagina admin Stato sistema di sola lettura con tabella Controllo/Stato/Valore/Suggerimento.
- Aggiunti controlli diagnostici difensivi per versioni, WP-Cron, uploads, AIOSEO, WordPress AI, updater GitHub, schema database e log interni.
- Nessuna chiamata OpenAI/GitHub, nessuna migrazione database e nessuna modifica al workflow di generazione.

### 0.5.4
- Recovery release per stabilizzare admin menu.
- Disabilitato temporaneamente il modulo Tipologie Articolo tramite feature flag.
- Rimossa registrazione CPT Tipologie Articolo dal caricamento predefinito.
- Ridotto rischio di notice map_meta_cap su delete_post.
- Ripristinato workflow Idee contenuto senza obbligo di Tipologia Articolo in recovery mode.
- Migliorata compatibilità admin WordPress.

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

## Workflow semplificato

Dalla versione **0.4.3** il workflow predefinito è **simple**: l’utente inserisce un’idea e usa il pulsante principale “Crea bozza”. Il plugin esegue internamente idea → dry-run → articolo completo → bozza WordPress, senza pubblicare automaticamente nulla. La bozza resta sempre revisionabile nel normale Editor Classico di WordPress.

La modalità **advanced** resta disponibile dalle impostazioni per debug e controllo manuale: dry-run, generazione articolo completo, approvazione e creazione bozza possono essere gestiti come passaggi separati.

## Fallback locale e contenuto pubblicabile

Il fallback locale non copia istruzioni redazionali nel contenuto finale. Le summary dell’outline vengono riscritte in testo rivolto al lettore prima della validazione, evitando frasi operative come “Spiegare”, “Mostrare”, “Descrivere” o “Indicare”. Se dopo la riscrittura l’articolo contiene ancora placeholder, blocchi Gutenberg, script, iframe, style inline, JSON grezzo o note interne, la bozza non viene creata.


## Changelog 0.5.5

* Reintrodotte Tipologie articolo come entità interne del plugin.
* Rimossa dipendenza dal Custom Post Type per le Tipologie articolo.
* Aggiunta tabella wpai_publisher_article_types.
* Aggiunta gestione admin interna per creare, modificare, eliminare e attivare tipologie.
* Le Tipologie articolo guidano prompt, struttura, tono, intento, lunghezza e categorie consentite.
* Il workflow Idee contenuto usa le tipologie dalla tabella custom.
* Evitato uso di map_meta_cap e capability post custom.
* Mantenuta stabilità admin della recovery 0.5.4.
