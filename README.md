# WP AI Publisher

Versione corrente: **0.4.1**

WP AI Publisher è un plugin WordPress per preparare un workflow di pubblicazione assistita da AI usando il sistema AI di WordPress configurato sul sito.

## Stato sviluppo

Il plugin è in fase operativa controllata. Crea bozze WordPress reali solo dopo approvazione esplicita del dry-run. Non pubblica automaticamente, non genera immagini reali e non scrive metadati SEO definitivi.


## Contesto editoriale riutilizzabile

Dalla versione **0.3.7** WP AI Publisher non è legato a wptutorial.ai, a tutorial WordPress o a una singola struttura editoriale. La pagina **Impostazioni** include una sezione **Contesto editoriale del sito** per configurare profilo, descrizione, nicchia, pubblico, tono, lingua, categorie consentite, tag preferiti, argomenti esclusi, regole editoriali, claim vietati e formato contenuto preferito.

Il contesto guida:

- prompt dei dry-run AI tramite sistema AI di WordPress;
- fallback locale quando non è disponibile un output AI utilizzabile;
- anteprima HTML compatibile con Classic Editor;
- futura creazione controllata di bozze.

Il target editoriale corrente resta **Editor Classico**: il plugin produce HTML pulito e non genera blocchi Gutenberg. La creazione 0.4.0 può produrre **bozze** o contenuti **in attesa di revisione**, ma non pubblicazioni automatiche.


## Pubblico target dal contesto editoriale

Dalla versione **0.4.1** il pubblico target non viene più richiesto per ogni nuova idea contenuto. Il dry-run legge il valore globale da **Impostazioni → Contesto editoriale del sito → Pubblico target predefinito** e lo usa come priorità per costruire il payload editoriale.

Le singole idee restano più semplici: argomento, keyword, lingua, livello tutorial e note editoriali. Le vecchie idee che hanno ancora un valore `target_audience` mantengono quel dato solo come fallback di retrocompatibilità se il contesto editoriale non contiene un pubblico predefinito. Eventuali override specifici potranno essere aggiunti in futuro come opzione avanzata.

## Sicurezza safety signals

Dalla versione **0.4.1** il matching dei safety signal delle WordPress Abilities normalizza underscore, spazi, trattini e altri separatori non alfanumerici come equivalenti. Un signal come `create_post` intercetta quindi metadata scritti come `create post`, `create-post`, `create/post`, `create.post` o `create:post`.

Il controllo usa boundary su parole complete e non substring ambigue: parole editoriali come `editorial` o `editing` non vengono bloccate solo perché contengono frammenti simili a segnali generici. Le abilities non sicure non vengono invocate durante il dry-run.

## Target editoriale: Editor Classico

Dalla versione **0.3.2** il target editoriale principale è l’**Editor Classico** di WordPress. Il plugin non deve produrre blocchi Gutenberg, commenti `<!-- wp:... -->` o serializzazione a blocchi.

La bozza WordPress usa `post_content` con HTML pulito e sicuro, ad esempio paragrafi, titoli `h2`/`h3`, liste e altri tag consentiti da allowlist. L’anteprima del dry-run passa da sanitizzazione dedicata e viene mostrata nell’admin come contenuto compatibile con Classic Editor.

AIOSEO sarà gestito separatamente in una fase successiva e non viene scritto in questa versione. Le immagini saranno integrate più avanti tramite Media Library, senza generazione reale nella fase 0.4.0.

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

Dalla versione **0.4.0** WP AI Publisher introduce la prima creazione reale di contenuto WordPress con un flusso a due passaggi:

1. generare e verificare un dry-run;
2. approvare il dry-run;
3. cliccare **Crea bozza**.

La bozza viene creata solo se l’idea è in stato **Approvata** e contiene un output `classic_editor_preview.html` valido. Il plugin usa `wp_insert_post()` con `post_type: post`, titolo, slug, excerpt e `post_content` HTML sanificato per **Editor Classico**. Non genera blocchi Gutenberg, non inserisce commenti `<!-- wp:... -->` e rifiuta contenuti con segnali Gutenberg.

Lo stato post è prudente:

- `draft` resta `draft`;
- `pending` resta `pending`;
- `publish` viene convertito in `draft` in 0.4.0, salvo costante di sviluppo esplicita `WPAIP_ALLOW_DIRECT_PUBLISH`.

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
- **Pubblicato** (`publish`) — selezionabile come intenzione futura, ma in 0.4.0 viene convertito in `draft` salvo costante di sviluppo esplicita.

Nella fase 0.4.0 il plugin crea solo bozze o post in attesa di revisione da dry-run approvati. L’eventuale pubblicazione automatica richiederà una fase futura, conferma esplicita e controlli dedicati.

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

### 0.4.0

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
