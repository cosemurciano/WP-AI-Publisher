<?php
/**
 * Job queue foundation.
 *
 * @package WPAIPublisher
 */

namespace WPAIPublisher;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Read/write access to the WP AI Publisher jobs table.
 */
class Job_Queue {
	/**
	 * Database service.
	 *
	 * @var DB
	 */
	private $db;

	/**
	 * Constructor.
	 *
	 * @param DB $db Database service.
	 */
	public function __construct( DB $db ) {
		$this->db = $db;
	}

	/**
	 * Return supported job type keys.
	 *
	 * @return array<int,string>
	 */
	public function get_supported_job_types() {
		return array(
			'generate_article',
			'generate_featured_image',
			'generate_internal_images',
			'apply_seo',
			'apply_internal_links',
			'index_post',
			'process_ai_file',
			'create_content_idea',
			'generate_draft_from_idea',
		);
	}

	/**
	 * Create a new queued job.
	 *
	 * This only records queue metadata. It does not execute AI calls, publish content,
	 * create media, or start a processor.
	 *
	 * @param string        $job_type       Job type key.
	 * @param array<string,mixed> $payload  Optional payload.
	 * @param int           $priority       Lower values can be processed first in future phases.
	 * @param int|null      $post_id        Optional related post ID.
	 * @param float|null    $estimated_cost Optional estimated cost.
	 * @return int|false Inserted job ID on success, false on failure or unsupported type.
	 */
	public function create_job( $job_type, $payload = array(), $priority = 10, $post_id = null, $estimated_cost = null ) {
		global $wpdb;

		$job_type = sanitize_key( (string) $job_type );
		if ( ! in_array( $job_type, $this->get_supported_job_types(), true ) ) {
			return false;
		}

		$payload_json = null;
		if ( ! empty( $payload ) ) {
			$payload_json = wp_json_encode( $payload );
			if ( false === $payload_json ) {
				$payload_json = null;
			}
		}

		$data = array(
			'job_type'   => $job_type,
			'status'     => 'pending',
			'priority'   => max( 0, absint( $priority ) ),
			'payload'    => $payload_json,
			'created_at' => current_time( 'mysql' ),
		);

		$format = array( '%s', '%s', '%d', '%s', '%s' );

		if ( null !== $post_id ) {
			$data['post_id'] = absint( $post_id );
			$format[]        = '%d';
		}

		if ( null !== $estimated_cost ) {
			$data['estimated_cost'] = (float) $estimated_cost;
			$format[]              = '%f';
		}

		$inserted = $wpdb->insert( $this->db->get_jobs_table_name(), $data, $format );
		if ( false === $inserted ) {
			return false;
		}

		return (int) $wpdb->insert_id;
	}

	public function get_job( $job_id ) {
		global $wpdb;
		$job_id = absint( $job_id );
		if ( 0 === $job_id ) {
			return null;
		}
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$this->db->get_jobs_table_name()} WHERE id = %d", $job_id ) );
	}

	public function get_active_draft_job_for_idea( $idea_id ) {
		global $wpdb;
		$idea_id = absint( $idea_id );
		if ( 0 === $idea_id ) {
			return null;
		}
		$like = '%' . $wpdb->esc_like( '"idea_id":' . $idea_id ) . '%';
		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$this->db->get_jobs_table_name()} WHERE job_type = %s AND status IN ('pending','running') AND payload LIKE %s ORDER BY id DESC LIMIT 1",
				'generate_draft_from_idea',
				$like
			)
		);
	}

	public function get_next_pending_job() {
		global $wpdb;
		return $wpdb->get_row( "SELECT * FROM {$this->db->get_jobs_table_name()} WHERE status = 'pending' ORDER BY priority ASC, created_at ASC, id ASC LIMIT 1" );
	}

	public function claim_job( $job_id, $timeout_minutes = 15 ) {
		global $wpdb;

		$job_id = absint( $job_id );
		if ( 0 === $job_id ) {
			return false;
		}

		$table           = $this->db->get_jobs_table_name();
		$now             = current_time( 'mysql' );
		$timeout_seconds = max( 60, absint( $timeout_minutes ) * MINUTE_IN_SECONDS );
		$stale_cutoff    = gmdate( 'Y-m-d H:i:s', strtotime( $now ) - $timeout_seconds );

		// Atomic claim: only a single concurrent worker (WP-Cron or manual "Esegui ora")
		// can flip a claimable job to running. A job is claimable when it is pending,
		// or it is running but stale, and it still has attempts left. Conditioning the
		// UPDATE on the current status closes the read-then-update race that previously
		// allowed two workers to process the same job and create duplicate drafts.
		$claimed = $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$table}
				SET status = 'running', attempts = attempts + 1, started_at = %s, finished_at = NULL, error_message = NULL
				WHERE id = %d
					AND attempts < GREATEST( 1, max_attempts )
					AND (
						status = 'pending'
						OR ( status = 'running' AND started_at IS NOT NULL AND started_at < %s )
					)",
				$now,
				$job_id,
				$stale_cutoff
			)
		);

		if ( 1 === (int) $claimed ) {
			return true;
		}

		if ( false === $claimed ) {
			return false;
		}

		// Not claimed: if the job is still open but has exhausted its attempts, fail it
		// so the queue does not keep a dead job pending forever.
		$job = $this->get_job( $job_id );
		if ( $job
			&& in_array( sanitize_key( (string) $job->status ), array( 'pending', 'running' ), true )
			&& absint( $job->attempts ) >= max( 1, absint( $job->max_attempts ) )
		) {
			$this->fail_job( $job_id, __( 'Numero massimo di tentativi superato.', 'wp-ai-publisher' ), array( 'error_code' => 'max_attempts_exceeded', 'step_failed' => 'queue' ) );
		}

		return false;
	}

	public function complete_job( $job_id, $output = array(), $post_id = 0 ) {
		global $wpdb;
		$output_json = wp_json_encode( is_array( $output ) ? $output : array() );
		return false !== $wpdb->update(
			$this->db->get_jobs_table_name(),
			array( 'status' => 'completed', 'finished_at' => current_time( 'mysql' ), 'output' => false === $output_json ? null : $output_json, 'post_id' => absint( $post_id ), 'error_message' => null ),
			array( 'id' => absint( $job_id ) ),
			array( '%s', '%s', '%s', '%d', '%s' ),
			array( '%d' )
		);
	}

	public function fail_job( $job_id, $message, $output = array(), $status = 'failed' ) {
		global $wpdb;
		$output_json = wp_json_encode( is_array( $output ) ? $output : array() );
		$status = in_array( sanitize_key( $status ), array( 'failed', 'timeout' ), true ) ? sanitize_key( $status ) : 'failed';
		return false !== $wpdb->update(
			$this->db->get_jobs_table_name(),
			array( 'status' => $status, 'finished_at' => current_time( 'mysql' ), 'error_message' => sanitize_text_field( (string) $message ), 'output' => false === $output_json ? null : $output_json ),
			array( 'id' => absint( $job_id ) ),
			array( '%s', '%s', '%s', '%s' ),
			array( '%d' )
		);
	}

	public function is_job_stale( $job, $timeout_minutes = 15 ) {
		$started = ! empty( $job->started_at ) ? strtotime( (string) $job->started_at ) : 0;
		return $started > 0 && ( time() - $started ) > max( 60, absint( $timeout_minutes ) * MINUTE_IN_SECONDS );
	}

	public function get_queue_diagnostics() {
		global $wpdb;
		$table = $this->db->get_jobs_table_name();
		$counts = $this->count_by_status();
		$stale = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE status = 'running' AND started_at < %s", gmdate( 'Y-m-d H:i:s', time() - 15 * MINUTE_IN_SECONDS ) ) );
		$last = (string) $wpdb->get_var( "SELECT finished_at FROM {$table} WHERE finished_at IS NOT NULL ORDER BY finished_at DESC LIMIT 1" );
		return array( 'pending' => (int) ( $counts['pending'] ?? 0 ), 'running' => (int) ( $counts['running'] ?? 0 ), 'failed' => (int) ( $counts['failed'] ?? 0 ), 'timeout' => (int) ( $counts['timeout'] ?? 0 ), 'stale' => $stale, 'last_finished_at' => $last );
	}

	/**
	 * Return most recently created jobs.
	 *
	 * @param int $limit Maximum rows.
	 * @return array<int,object>
	 */
	public function get_recent_jobs( $limit = 20 ) {
		global $wpdb;

		$limit = min( 100, max( 1, absint( $limit ) ) );

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$this->db->get_jobs_table_name()} ORDER BY created_at DESC, id DESC LIMIT %d",
				$limit
			)
		);
	}

	/**
	 * Count jobs grouped by status.
	 *
	 * @return array<string,int>
	 */
	public function count_by_status() {
		global $wpdb;

		$counts = array_fill_keys( array( 'pending', 'running', 'completed', 'failed', 'cancelled', 'timeout' ), 0 );
		$rows   = $wpdb->get_results( "SELECT status, COUNT(*) AS total FROM {$this->db->get_jobs_table_name()} GROUP BY status" );

		foreach ( $rows as $row ) {
			$status = sanitize_key( (string) $row->status );
			if ( array_key_exists( $status, $counts ) ) {
				$counts[ $status ] = (int) $row->total;
			}
		}

		return $counts;
	}

	/**
	 * Return translated status label.
	 *
	 * @param string $status Status key.
	 * @return string
	 */
	public function get_status_label( $status ) {
		$labels = array(
			'pending'   => __( 'In attesa', 'wp-ai-publisher' ),
			'running'   => __( 'In corso', 'wp-ai-publisher' ),
			'completed' => __( 'Completato', 'wp-ai-publisher' ),
			'failed'    => __( 'Fallito', 'wp-ai-publisher' ),
			'cancelled' => __( 'Annullato', 'wp-ai-publisher' ),
			'timeout'   => __( 'Scaduto', 'wp-ai-publisher' ),
		);

		$status = sanitize_key( (string) $status );

		return $labels[ $status ] ?? $status;
	}

	/**
	 * Return translated job type label.
	 *
	 * @param string $job_type Job type key.
	 * @return string
	 */
	public function get_job_type_label( $job_type ) {
		$labels = array(
			'generate_article'          => __( 'Generazione articolo', 'wp-ai-publisher' ),
			'generate_featured_image'   => __( 'Generazione immagine in evidenza', 'wp-ai-publisher' ),
			'generate_internal_images'  => __( 'Generazione immagini interne', 'wp-ai-publisher' ),
			'apply_seo'                 => __( 'Applicazione SEO', 'wp-ai-publisher' ),
			'apply_internal_links'      => __( 'Applicazione link interni', 'wp-ai-publisher' ),
			'index_post'                => __( 'Indicizzazione articolo', 'wp-ai-publisher' ),
			'process_ai_file'           => __( 'Elaborazione file AI', 'wp-ai-publisher' ),
			'create_content_idea'       => __( 'Creazione idea contenuto', 'wp-ai-publisher' ),
			'generate_draft_from_idea'=> __( 'Generazione bozza da idea', 'wp-ai-publisher' ),
		);

		$job_type = sanitize_key( (string) $job_type );

		return $labels[ $job_type ] ?? $job_type;
	}
}
