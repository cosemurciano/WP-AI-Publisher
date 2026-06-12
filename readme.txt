=== WP AI Publisher ===
Contributors: wp-ai-publisher
Tags: ai, publishing, admin, drafts, wordpress-ai
Requires at least: 7.0
Tested up to: 7.0
Requires PHP: 8.1
Stable tag: 0.1.1
License: GPLv3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Base modulare per la pubblicazione assistita da AI in WordPress.

== Descrizione ==

WP AI Publisher prepara l’infrastruttura del plugin per la futura generazione assistita di articoli, bozze strutturate, media, metadati SEO, link interni, knowledge index, coda job, dry-run, controllo duplicati e pubblicazione assistita.

Dalla versione 0.1.1 il plugin usa esclusivamente il sistema AI di WordPress configurato sul sito. Non gestisce un client OpenAI custom e non salva chiavi API proprie.

Questa versione non genera ancora articoli, immagini o embedding. Tutte le future funzioni AI passeranno dall’adapter centrale collegato al sistema AI di WordPress.

== Installazione ==

1. Carica la cartella del plugin in `/wp-content/plugins/` oppure installa lo ZIP da Plugin > Aggiungi plugin > Carica plugin.
2. Attiva WP AI Publisher dalla schermata Plugin.
3. Apri WP AI Publisher > Bacheca.
4. Controlla WP AI Publisher > Impostazioni.
5. Controlla WP AI Publisher > Stato sistema.

== Domande frequenti ==

= Questa versione chiama direttamente OpenAI? =

No. WP AI Publisher usa solo il sistema AI di WordPress configurato sul sito.

= Questa versione genera articoli? =

No. La generazione articoli sarà implementata in una fase successiva.

= Come vengono mostrati i modelli AI disponibili? =

Il plugin prova a leggerli dal sistema AI di WordPress. Se l’integrazione attiva espone i modelli tramite funzioni, client o filtro `wpai_publisher_available_ai_models`, questi compaiono nel menu a tendina delle impostazioni.

== Changelog ==

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
