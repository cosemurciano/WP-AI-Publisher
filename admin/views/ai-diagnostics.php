<?php
/**
 * AI diagnostics admin view.
 *
 * @package WPAIPublisher
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$status_labels = array(
	'available'   => __( 'Disponibile', 'wp-ai-publisher' ),
	'maybe'       => __( 'Possibile', 'wp-ai-publisher' ),
	'unavailable' => __( 'Non disponibile', 'wp-ai-publisher' ),
);
$confidence_labels = array(
	'high'   => __( 'Alta', 'wp-ai-publisher' ),
	'medium' => __( 'Media', 'wp-ai-publisher' ),
	'low'    => __( 'Bassa', 'wp-ai-publisher' ),
);
?>
<div class="wrap wpai-admin wpai-ai-diagnostics">
	<h1><?php echo esc_html__( 'Diagnostica AI', 'wp-ai-publisher' ); ?></h1>
	<p class="wpai-lead"><?php echo esc_html__( 'Questa pagina aiuta a capire come il sistema AI di WordPress espone connector, abilities, funzioni e route REST. Non mostra chiavi segrete e non esegue chiamate remote se non tramite test manuale.', 'wp-ai-publisher' ); ?></p>

	<h2><?php echo esc_html__( '1. Riepilogo', 'wp-ai-publisher' ); ?></h2>
	<table class="widefat striped wpai-status-table">
		<tbody>
			<tr><th scope="row"><?php echo esc_html__( 'Sistema AI WordPress', 'wp-ai-publisher' ); ?></th><td><?php echo ! empty( $report['summary']['wordpress_ai_available'] ) ? esc_html__( 'Rilevato', 'wp-ai-publisher' ) : esc_html__( 'Non rilevato', 'wp-ai-publisher' ); ?></td></tr>
			<tr><th scope="row"><?php echo esc_html__( 'Funzioni AI trovate', 'wp-ai-publisher' ); ?></th><td><?php echo esc_html( (string) $report['summary']['functions_found'] ); ?></td></tr>
			<tr><th scope="row"><?php echo esc_html__( 'Classi AI trovate', 'wp-ai-publisher' ); ?></th><td><?php echo esc_html( (string) $report['summary']['classes_found'] ); ?></td></tr>
			<tr><th scope="row"><?php echo esc_html__( 'REST route AI trovate', 'wp-ai-publisher' ); ?></th><td><?php echo esc_html( (string) $report['summary']['rest_routes_found'] ); ?></td></tr>
			<tr><th scope="row"><?php echo esc_html__( 'Options AI rilevate', 'wp-ai-publisher' ); ?></th><td><?php echo esc_html( (string) $report['summary']['options_found'] ); ?></td></tr>
			<tr><th scope="row"><?php echo esc_html__( 'Abilities WordPress rilevate', 'wp-ai-publisher' ); ?></th><td><?php echo esc_html( (string) ( $report['summary']['abilities_found'] ?? 0 ) ); ?></td></tr>
			<tr><th scope="row"><?php echo esc_html__( 'Plugin AI attivi rilevati', 'wp-ai-publisher' ); ?></th><td><?php echo esc_html( (string) $report['summary']['plugins_found'] ); ?></td></tr>
			<tr><th scope="row"><?php echo esc_html__( 'Esperimenti AI rilevati', 'wp-ai-publisher' ); ?></th><td><?php echo esc_html( (string) $report['summary']['experiments_detected'] ); ?></td></tr>
		</tbody>
	</table>

	<?php if ( ! empty( $report['recommendations'] ) ) : ?>
		<h3><?php echo esc_html__( 'Raccomandazioni', 'wp-ai-publisher' ); ?></h3>
		<ul class="ul-disc">
			<?php foreach ( $report['recommendations'] as $recommendation ) : ?>
				<li><?php echo esc_html( $recommendation ); ?></li>
			<?php endforeach; ?>
		</ul>
	<?php endif; ?>

	<h2><?php echo esc_html__( '2. Percorsi di generazione possibili', 'wp-ai-publisher' ); ?></h2>
	<table class="widefat striped wpai-status-table">
		<thead><tr><th><?php echo esc_html__( 'Percorso', 'wp-ai-publisher' ); ?></th><th><?php echo esc_html__( 'Stato', 'wp-ai-publisher' ); ?></th><th><?php echo esc_html__( 'Confidenza', 'wp-ai-publisher' ); ?></th><th><?php echo esc_html__( 'Note', 'wp-ai-publisher' ); ?></th></tr></thead>
		<tbody>
			<?php foreach ( $report['possible_generation_paths'] as $path ) : ?>
				<tr>
					<td><?php echo esc_html( $path['label'] ); ?></td>
					<td><span class="<?php echo esc_attr( wpai_publisher_badge_class( 'available' === $path['status'] ? 'ok' : ( 'maybe' === $path['status'] ? 'warning' : 'not-verified' ) ) ); ?>"><?php echo esc_html( $status_labels[ $path['status'] ] ?? $path['status'] ); ?></span></td>
					<td><?php echo esc_html( $confidence_labels[ $path['confidence'] ] ?? $path['confidence'] ); ?></td>
					<td><?php echo esc_html( $path['notes'] ); ?></td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>

	<h2><?php echo esc_html__( '3. Funzioni rilevate', 'wp-ai-publisher' ); ?></h2>
	<table class="widefat striped wpai-status-table">
		<thead><tr><th><?php echo esc_html__( 'Funzione', 'wp-ai-publisher' ); ?></th><th><?php echo esc_html__( 'Esiste', 'wp-ai-publisher' ); ?></th><th><?php echo esc_html__( 'Callable', 'wp-ai-publisher' ); ?></th><th><?php echo esc_html__( 'Tipo risultato', 'wp-ai-publisher' ); ?></th><th><?php echo esc_html__( 'Note', 'wp-ai-publisher' ); ?></th></tr></thead>
		<tbody>
			<?php foreach ( $report['functions'] as $function ) : ?>
				<tr>
					<td><code><?php echo esc_html( $function['name'] ); ?></code></td>
					<td><?php echo ! empty( $function['exists'] ) ? esc_html__( 'Sì', 'wp-ai-publisher' ) : esc_html__( 'No', 'wp-ai-publisher' ); ?></td>
					<td><?php echo ! empty( $function['callable'] ) ? esc_html__( 'Sì', 'wp-ai-publisher' ) : esc_html__( 'No', 'wp-ai-publisher' ); ?></td>
					<td><?php echo esc_html( (string) $function['result_type'] ); ?></td>
					<td><?php echo esc_html( $function['notes'] ); ?></td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>

	<h2><?php echo esc_html__( '4. Classi rilevate', 'wp-ai-publisher' ); ?></h2>
	<table class="widefat striped wpai-status-table">
		<thead><tr><th><?php echo esc_html__( 'Classe', 'wp-ai-publisher' ); ?></th><th><?php echo esc_html__( 'Esiste', 'wp-ai-publisher' ); ?></th><th><?php echo esc_html__( 'Metodi pubblici principali', 'wp-ai-publisher' ); ?></th><th><?php echo esc_html__( 'Note', 'wp-ai-publisher' ); ?></th></tr></thead>
		<tbody>
			<?php foreach ( $report['classes'] as $class ) : ?>
				<tr>
					<td><code><?php echo esc_html( $class['name'] ); ?></code></td>
					<td><?php echo ! empty( $class['exists'] ) ? esc_html__( 'Sì', 'wp-ai-publisher' ) : esc_html__( 'No', 'wp-ai-publisher' ); ?></td>
					<td><?php echo esc_html( implode( ', ', (array) $class['methods'] ) ); ?></td>
					<td><?php echo esc_html( $class['notes'] ); ?></td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>

	<h2><?php echo esc_html__( '5. REST route AI', 'wp-ai-publisher' ); ?></h2>
	<?php if ( empty( $report['rest_routes'] ) ) : ?>
		<p><?php echo esc_html__( 'Nessuna REST route AI rilevata.', 'wp-ai-publisher' ); ?></p>
	<?php else : ?>
		<table class="widefat striped wpai-status-table">
			<thead><tr><th><?php echo esc_html__( 'Namespace / route', 'wp-ai-publisher' ); ?></th><th><?php echo esc_html__( 'Metodi HTTP', 'wp-ai-publisher' ); ?></th><th><?php echo esc_html__( 'Callback', 'wp-ai-publisher' ); ?></th><th><?php echo esc_html__( 'Permission callback', 'wp-ai-publisher' ); ?></th></tr></thead>
			<tbody>
				<?php foreach ( $report['rest_routes'] as $route ) : ?>
					<tr>
						<td><code><?php echo esc_html( $route['namespace'] . ' / ' . $route['route'] ); ?></code></td>
						<td><?php echo esc_html( $route['methods'] ); ?></td>
						<td><code><?php echo esc_html( $route['callback'] ); ?></code></td>
						<td><?php echo ! empty( $route['permission_callback'] ) ? esc_html__( 'Sì', 'wp-ai-publisher' ) : esc_html__( 'No', 'wp-ai-publisher' ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>

	<h2><?php echo esc_html__( '6. Opzioni AI rilevate, mascherate', 'wp-ai-publisher' ); ?></h2>
	<?php if ( empty( $report['options'] ) ) : ?>
		<p><?php echo esc_html__( 'Nessuna option AI rilevata.', 'wp-ai-publisher' ); ?></p>
	<?php else : ?>
		<table class="widefat striped wpai-status-table">
			<thead><tr><th><?php echo esc_html__( 'Option name', 'wp-ai-publisher' ); ?></th><th><?php echo esc_html__( 'Autoload', 'wp-ai-publisher' ); ?></th><th><?php echo esc_html__( 'Tipo', 'wp-ai-publisher' ); ?></th><th><?php echo esc_html__( 'Lunghezza', 'wp-ai-publisher' ); ?></th><th><?php echo esc_html__( 'Anteprima', 'wp-ai-publisher' ); ?></th><th><?php echo esc_html__( 'Sensibile', 'wp-ai-publisher' ); ?></th><th><?php echo esc_html__( 'Mascherata', 'wp-ai-publisher' ); ?></th></tr></thead>
			<tbody>
				<?php foreach ( $report['options'] as $option ) : ?>
					<tr>
						<td><code><?php echo esc_html( $option['option_name'] ); ?></code></td>
						<td><?php echo esc_html( $option['autoload'] ); ?></td>
						<td><?php echo esc_html( $option['type'] ); ?></td>
						<td><?php echo esc_html( (string) $option['length'] ); ?></td>
						<td><code><?php echo esc_html( $option['preview'] ); ?></code></td>
						<td><?php echo ! empty( $option['sensitive'] ) ? esc_html__( 'Sì', 'wp-ai-publisher' ) : esc_html__( 'No', 'wp-ai-publisher' ); ?></td>
						<td><?php echo ! empty( $option['masked'] ) ? esc_html__( 'Sì', 'wp-ai-publisher' ) : esc_html__( 'No', 'wp-ai-publisher' ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>

	<h2><?php echo esc_html__( '7. Abilities WordPress rilevate', 'wp-ai-publisher' ); ?></h2>
	<p><?php echo esc_html__( 'Se una ability sembra adatta alla generazione testo, indicarne il nome nelle impostazioni future o nel bridge manuale.', 'wp-ai-publisher' ); ?></p>
	<?php if ( empty( $report['abilities'] ) ) : ?>
		<p><?php echo esc_html__( 'Nessuna ability WordPress rilevata tramite wp_get_abilities.', 'wp-ai-publisher' ); ?></p>
	<?php else : ?>
		<table class="widefat striped wpai-status-table">
			<thead><tr><th><?php echo esc_html__( 'Name', 'wp-ai-publisher' ); ?></th><th><?php echo esc_html__( 'Label/title', 'wp-ai-publisher' ); ?></th><th><?php echo esc_html__( 'Description', 'wp-ai-publisher' ); ?></th><th><?php echo esc_html__( 'Category', 'wp-ai-publisher' ); ?></th><th><?php echo esc_html__( 'Input schema', 'wp-ai-publisher' ); ?></th><th><?php echo esc_html__( 'Output schema', 'wp-ai-publisher' ); ?></th><th><?php echo esc_html__( 'Invocabile', 'wp-ai-publisher' ); ?></th></tr></thead>
			<tbody>
				<?php foreach ( $report['abilities'] as $ability ) : ?>
					<tr>
						<td><code><?php echo esc_html( $ability['name'] ); ?></code></td>
						<td><?php echo esc_html( $ability['label'] ); ?></td>
						<td><?php echo esc_html( $ability['description'] ); ?></td>
						<td><?php echo esc_html( $ability['category'] ); ?></td>
						<td><?php echo esc_html( $ability['input_schema'] ); ?></td>
						<td><?php echo esc_html( $ability['output_schema'] ); ?></td>
						<td><?php echo esc_html( $ability['invocable'] ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>

	<h2><?php echo esc_html__( '8. Plugin AI rilevati', 'wp-ai-publisher' ); ?></h2>
	<?php if ( empty( $report['plugins'] ) ) : ?>
		<p><?php echo esc_html__( 'Nessun plugin AI attivo rilevato con le keyword note.', 'wp-ai-publisher' ); ?></p>
	<?php else : ?>
		<table class="widefat striped wpai-status-table">
			<thead><tr><th><?php echo esc_html__( 'Plugin', 'wp-ai-publisher' ); ?></th><th><?php echo esc_html__( 'Nome', 'wp-ai-publisher' ); ?></th><th><?php echo esc_html__( 'Versione', 'wp-ai-publisher' ); ?></th><th><?php echo esc_html__( 'Descrizione', 'wp-ai-publisher' ); ?></th></tr></thead>
			<tbody>
				<?php foreach ( $report['plugins'] as $plugin ) : ?>
					<tr>
						<td><code><?php echo esc_html( $plugin['plugin'] ); ?></code></td>
						<td><?php echo esc_html( $plugin['name'] ); ?></td>
						<td><?php echo esc_html( $plugin['version'] ); ?></td>
						<td><?php echo esc_html( $plugin['description'] ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>

	<h2><?php echo esc_html__( '9. Esperimenti AI rilevati', 'wp-ai-publisher' ); ?></h2>
	<table class="widefat striped wpai-status-table">
		<thead><tr><th><?php echo esc_html__( 'Esperimento', 'wp-ai-publisher' ); ?></th><th><?php echo esc_html__( 'Rilevato', 'wp-ai-publisher' ); ?></th><th><?php echo esc_html__( 'Attivo', 'wp-ai-publisher' ); ?></th><th><?php echo esc_html__( 'Fonte rilevamento', 'wp-ai-publisher' ); ?></th></tr></thead>
		<tbody>
			<?php foreach ( $report['experiments'] as $experiment ) : ?>
				<tr>
					<td><?php echo esc_html( $experiment['label'] ); ?></td>
					<td><?php echo ! empty( $experiment['detected'] ) ? esc_html__( 'Sì', 'wp-ai-publisher' ) : esc_html__( 'No', 'wp-ai-publisher' ); ?></td>
					<td><?php echo null === $experiment['active'] ? esc_html__( 'Non deducibile', 'wp-ai-publisher' ) : ( $experiment['active'] ? esc_html__( 'Sì', 'wp-ai-publisher' ) : esc_html__( 'No', 'wp-ai-publisher' ) ); ?></td>
					<td><code><?php echo esc_html( $experiment['source'] ); ?></code></td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>

	<h2><?php echo esc_html__( '10. Test AI controllato', 'wp-ai-publisher' ); ?></h2>
	<p><?php echo esc_html__( 'Il test viene eseguito solo quando premi il pulsante. Usa esclusivamente funzioni o client locali rilevati dal sistema AI WordPress e non chiama OpenAI direttamente.', 'wp-ai-publisher' ); ?></p>
	<form method="post">
		<?php wp_nonce_field( 'wpai_publisher_ai_diagnostics_test', 'wpai_publisher_ai_diagnostics_nonce' ); ?>
		<input type="hidden" name="wpai_publisher_ai_diagnostics_action" value="run_safe_generation_test" />
		<?php submit_button( __( 'Esegui test AI controllato', 'wp-ai-publisher' ), 'secondary', 'submit', false ); ?>
	</form>
	<?php if ( ! empty( $test_result ) ) : ?>
		<table class="widefat striped wpai-status-table wpai-test-result">
			<tbody>
				<tr><th scope="row"><?php echo esc_html__( 'Path usato', 'wp-ai-publisher' ); ?></th><td><code><?php echo esc_html( $test_result['path'] ); ?></code></td></tr>
				<?php if ( ! empty( $test_result['ability'] ) ) : ?>
					<tr><th scope="row"><?php echo esc_html__( 'Ability usata', 'wp-ai-publisher' ); ?></th><td><code><?php echo esc_html( $test_result['ability'] ); ?></code></td></tr>
				<?php endif; ?>
					<tr><th scope="row"><?php echo esc_html__( 'Esito', 'wp-ai-publisher' ); ?></th><td><?php echo ! empty( $test_result['success'] ) ? esc_html__( 'Successo', 'wp-ai-publisher' ) : esc_html__( 'Errore', 'wp-ai-publisher' ); ?></td></tr>
				<tr><th scope="row"><?php echo esc_html__( 'Tipo risposta', 'wp-ai-publisher' ); ?></th><td><?php echo esc_html( $test_result['response_type'] ); ?></td></tr>
				<?php if ( empty( $test_result['success'] ) ) : ?>
					<tr><th scope="row"><?php echo esc_html__( 'Errore', 'wp-ai-publisher' ); ?></th><td><?php echo esc_html( $test_result['error'] ); ?></td></tr>
				<?php endif; ?>
				<tr><th scope="row"><?php echo esc_html__( 'Estratto risposta', 'wp-ai-publisher' ); ?></th><td><pre><?php echo esc_html( $test_result['excerpt'] ); ?></pre></td></tr>
			</tbody>
		</table>
	<?php else : ?>
		<p><?php echo esc_html__( 'Nessun test eseguito in questa visualizzazione.', 'wp-ai-publisher' ); ?></p>
	<?php endif; ?>

	<h2><?php echo esc_html__( '11. Bridge manuale consigliato', 'wp-ai-publisher' ); ?></h2>
	<p><?php echo esc_html__( 'WP AI Publisher supporta un bridge manuale tramite filtri. Usa questo approccio quando la diagnostica mostra che il sistema AI è installato ma non espone una funzione di generazione direttamente invocabile dal plugin.', 'wp-ai-publisher' ); ?></p>
	<ul class="ul-disc">
		<li><?php echo esc_html__( 'Nuovo filtro:', 'wp-ai-publisher' ); ?> <code>wpai_publisher_generate_structured_content_dry_run</code></li>
		<li><?php echo esc_html__( 'Filtro legacy:', 'wp-ai-publisher' ); ?> <code>wpai_publisher_structured_content_dry_run</code></li>
	</ul>
	<pre><code><?php echo esc_html("add_filter(\n  'wpai_publisher_generate_structured_content_dry_run',\n  function ( \$result, \$payload, \$schema ) {\n      // Qui collegare la funzione reale esposta dal sistema AI WordPress.\n      return \$result;\n  },\n  10,\n  3\n);"); ?></code></pre>
</div>
