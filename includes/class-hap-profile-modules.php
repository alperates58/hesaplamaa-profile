<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HAP_Profile_Modules {

	private function table() {
		global $wpdb;
		return $wpdb->prefix . HAP_TABLE_MODULES;
	}

	public function get_modules( $args = array() ) {
		global $wpdb;
		$table = $this->table();

		$defaults = array(
			'profile_status'            => '',
			'section'                   => '',
			'availability_status'       => '',
			'search'                    => '',
			'orderby'                   => 'sort_order',
			'order'                     => 'ASC',
			'limit'                     => 50,
			'offset'                    => 0,
			'result_enabled'            => null,
			'onboarding_prompt_enabled' => null,
		);
		$args = wp_parse_args( $args, $defaults );

		$where  = array( '1=1' );
		$params = array();

		if ( '' !== $args['profile_status'] ) {
			$where[]  = 'profile_status = %s';
			$params[] = $args['profile_status'];
		}
		if ( '' !== $args['section'] ) {
			$where[]  = 'section = %s';
			$params[] = $args['section'];
		}
		if ( '' !== $args['availability_status'] ) {
			$where[]  = 'availability_status = %s';
			$params[] = $args['availability_status'];
		}
		if ( '' !== $args['search'] ) {
			$where[]  = '(title LIKE %s OR slug LIKE %s)';
			$like     = '%' . $wpdb->esc_like( $args['search'] ) . '%';
			$params[] = $like;
			$params[] = $like;
		}
		if ( null !== $args['result_enabled'] ) {
			$where[]  = 'result_enabled = %d';
			$params[] = ! empty( $args['result_enabled'] ) ? 1 : 0;
		}
		if ( null !== $args['onboarding_prompt_enabled'] ) {
			$where[]  = 'onboarding_prompt_enabled = %d';
			$params[] = ! empty( $args['onboarding_prompt_enabled'] ) ? 1 : 0;
		}

		$where_sql = implode( ' AND ', $where );
		$orderby   = in_array( $args['orderby'], array( 'sort_order', 'title', 'section', 'created_at' ), true ) ? $args['orderby'] : 'sort_order';
		$order     = strtoupper( $args['order'] ) === 'DESC' ? 'DESC' : 'ASC';
		$limit     = absint( $args['limit'] );
		$offset    = absint( $args['offset'] );

		$query_params = array_merge( $params, array( $limit, $offset ) );
		$sql          = $wpdb->prepare(
			"SELECT * FROM {$table} WHERE {$where_sql} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d",
			$query_params
		);

		$rows = $wpdb->get_results( $sql, ARRAY_A );
		return $rows ? $rows : array();
	}

	public function count_modules( $args = array() ) {
		global $wpdb;
		$table = $this->table();

		$where  = array( '1=1' );
		$params = array();

		if ( ! empty( $args['profile_status'] ) ) {
			$where[]  = 'profile_status = %s';
			$params[] = $args['profile_status'];
		}
		if ( ! empty( $args['section'] ) ) {
			$where[]  = 'section = %s';
			$params[] = $args['section'];
		}
		if ( ! empty( $args['availability_status'] ) ) {
			$where[]  = 'availability_status = %s';
			$params[] = $args['availability_status'];
		}
		if ( isset( $args['result_enabled'] ) && null !== $args['result_enabled'] ) {
			$where[]  = 'result_enabled = %d';
			$params[] = ! empty( $args['result_enabled'] ) ? 1 : 0;
		}
		if ( isset( $args['onboarding_prompt_enabled'] ) && null !== $args['onboarding_prompt_enabled'] ) {
			$where[]  = 'onboarding_prompt_enabled = %d';
			$params[] = ! empty( $args['onboarding_prompt_enabled'] ) ? 1 : 0;
		}

		$where_sql = implode( ' AND ', $where );
		if ( ! empty( $params ) ) {
			return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE {$where_sql}", $params ) );
		}
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE {$where_sql}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	public function get_module_by_slug( $slug ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$this->table()} WHERE slug = %s", sanitize_key( $slug ) ), ARRAY_A );
	}

	public function get_module_by_id( $id ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$this->table()} WHERE id = %d", absint( $id ) ), ARRAY_A );
	}

	public function save_module( array $data, $id = null ) {
		global $wpdb;
		$table = $this->table();
		$now   = current_time( 'mysql' );

		$row = array(
			'slug'                       => sanitize_key( $data['slug'] ?? '' ),
			'title'                      => sanitize_text_field( $data['title'] ?? '' ),
			'shortcode'                  => sanitize_text_field( $data['shortcode'] ?? '' ),
			'section'                    => sanitize_key( $data['section'] ?? '' ),
			'profile_status'             => sanitize_key( $data['profile_status'] ?? 'disabled' ),
			'required_fields'            => wp_json_encode( $this->decode_fields_json( $data['required_fields'] ?? array() ) ),
			'optional_fields'            => wp_json_encode( $this->decode_fields_json( $data['optional_fields'] ?? array() ) ),
			'missing_fields_behavior'    => sanitize_key( $data['missing_fields_behavior'] ?? 'show_prompt' ),
			'ai_enabled'                 => ! empty( $data['ai_enabled'] ) ? 1 : 0,
			'sort_order'                 => absint( $data['sort_order'] ?? 0 ),
			'notes'                      => sanitize_textarea_field( $data['notes'] ?? '' ),
			'source'                     => sanitize_key( $data['source'] ?? 'manual' ),
			'availability_status'        => sanitize_key( $data['availability_status'] ?? 'active' ),
			'result_enabled'             => ! empty( $data['result_enabled'] ) ? 1 : 0,
			'onboarding_prompt_enabled'  => ! empty( $data['onboarding_prompt_enabled'] ) ? 1 : 0,
			'ai_include'                 => ! empty( $data['ai_include'] ) ? 1 : 0,
			'share_include_default'      => ! empty( $data['share_include_default'] ) ? 1 : 0,
			'updated_at'                 => $now,
		);

		foreach ( array( 'runner_type', 'input_mapping', 'output_mapping', 'runner_callback', 'ajax_action', 'result_selector', 'tool_url', 'runner_status', 'runner_notes' ) as $optional_column ) {
			if ( array_key_exists( $optional_column, $data ) ) {
				$row[ $optional_column ] = is_scalar( $data[ $optional_column ] ) ? (string) $data[ $optional_column ] : wp_json_encode( $data[ $optional_column ] );
			}
		}

		$formats = $this->build_formats_for_row( $row );

		if ( $id ) {
			$wpdb->update( $table, $row, array( 'id' => absint( $id ) ), $formats, array( '%d' ) );
			return absint( $id );
		}

		$existing = $this->get_module_by_slug( $row['slug'] );
		if ( $existing ) {
			$wpdb->update( $table, $row, array( 'slug' => $row['slug'] ), $formats, array( '%s' ) );
			return (int) $existing['id'];
		}

		$row['created_at'] = $now;
		$wpdb->insert( $table, $row, $this->build_formats_for_row( $row ) );
		return (int) $wpdb->insert_id;
	}

	public function delete_module( $id ) {
		global $wpdb;
		return $wpdb->delete( $this->table(), array( 'id' => absint( $id ) ), array( '%d' ) );
	}

	public function import_from_json( $json_data ) {
		if ( is_string( $json_data ) ) {
			$json_data = json_decode( $json_data, true );
		}
		if ( ! is_array( $json_data ) ) {
			return new WP_Error( 'invalid_json', 'Geçersiz JSON formatı.' );
		}

		$inserted = 0;
		$updated  = 0;
		$skipped  = 0;
		$errors   = array();

		foreach ( $json_data as $item ) {
			if ( empty( $item['slug'] ) ) {
				$skipped++;
				continue;
			}

			$slug     = sanitize_key( $item['slug'] );
			$existing = $this->get_module_by_slug( $slug );
			$module   = array(
				'slug'                      => $slug,
				'title'                     => sanitize_text_field( $item['title'] ?? '' ),
				'shortcode'                 => sanitize_text_field( $item['shortcode'] ?? '' ),
				'section'                   => sanitize_key( $item['section'] ?? ( $existing['section'] ?? 'overview' ) ),
				'profile_status'            => $existing ? $existing['profile_status'] : 'tool_only',
				'required_fields'           => $existing ? $this->decode_fields_json( $existing['required_fields'] ) : array(),
				'optional_fields'           => $existing ? $this->decode_fields_json( $existing['optional_fields'] ?? array() ) : array(),
				'missing_fields_behavior'   => $existing['missing_fields_behavior'] ?? 'show_prompt',
				'ai_enabled'                => $existing ? (int) $existing['ai_enabled'] : 0,
				'sort_order'                => $existing ? (int) $existing['sort_order'] : 0,
				'notes'                     => sanitize_textarea_field( $item['notes'] ?? ( $existing['notes'] ?? '' ) ),
				'source'                    => 'json_import',
				'availability_status'       => sanitize_key( $item['availability_status'] ?? ( $existing['availability_status'] ?? 'active' ) ),
				'result_enabled'            => isset( $existing['result_enabled'] ) ? (int) $existing['result_enabled'] : 1,
				'onboarding_prompt_enabled' => isset( $existing['onboarding_prompt_enabled'] ) ? (int) $existing['onboarding_prompt_enabled'] : 1,
				'ai_include'                => isset( $existing['ai_include'] ) ? (int) $existing['ai_include'] : 1,
				'share_include_default'     => isset( $existing['share_include_default'] ) ? (int) $existing['share_include_default'] : 0,
			);

			$result = $this->save_module( $module, $existing ? (int) $existing['id'] : null );
			if ( $result ) {
				$existing ? $updated++ : $inserted++;
			} else {
				$errors[] = $slug;
			}
		}

		$report = array(
			'inserted' => $inserted,
			'updated'  => $updated,
			'skipped'  => $skipped,
			'total'    => $inserted + $updated + $skipped,
			'errors'   => $errors,
			'time'     => current_time( 'mysql' ),
		);
		update_option( 'hap_profile_last_import_report', $report, false );
		return $report;
	}

	public function get_sections_summary() {
		global $wpdb;
		$table = $this->table();
		$rows  = $wpdb->get_results( "SELECT section, profile_status, availability_status, COUNT(*) as cnt FROM {$table} GROUP BY section, profile_status, availability_status", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$summary = array();
		foreach ( (array) $rows as $row ) {
			$sec = $row['section'];
			if ( ! isset( $summary[ $sec ] ) ) {
				$summary[ $sec ] = array( 'total' => 0, 'core' => 0, 'optional' => 0, 'disabled' => 0, 'planned' => 0 );
			}
			$summary[ $sec ]['total'] += (int) $row['cnt'];
			if ( $row['profile_status'] === 'profile_core' ) {
				$summary[ $sec ]['core'] += (int) $row['cnt'];
			} elseif ( $row['profile_status'] === 'profile_optional' ) {
				$summary[ $sec ]['optional'] += (int) $row['cnt'];
			} elseif ( $row['profile_status'] === 'disabled' ) {
				$summary[ $sec ]['disabled'] += (int) $row['cnt'];
			}
			if ( $row['availability_status'] === 'planned' ) {
				$summary[ $sec ]['planned'] += (int) $row['cnt'];
			}
		}
		return $summary;
	}

	public function get_status_counts() {
		global $wpdb;
		$table = $this->table();
		$rows  = $wpdb->get_results( "SELECT profile_status, COUNT(*) as cnt FROM {$table} GROUP BY profile_status", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$counts = array(
			'profile_core'     => 0,
			'profile_optional' => 0,
			'tool_only'        => 0,
			'disabled'         => 0,
		);
		foreach ( (array) $rows as $row ) {
			if ( isset( $counts[ $row['profile_status'] ] ) ) {
				$counts[ $row['profile_status'] ] = (int) $row['cnt'];
			}
		}
		return $counts;
	}

	public function decode_required_fields( $raw ) {
		return $this->decode_fields_json( $raw );
	}

	public function decode_fields_json( $raw ) {
		if ( is_array( $raw ) ) {
			return array_values( array_filter( array_map( 'sanitize_key', $raw ) ) );
		}
		$decoded = json_decode( (string) $raw, true );
		return is_array( $decoded ) ? array_values( array_filter( array_map( 'sanitize_key', $decoded ) ) ) : array();
	}

	/**
	 * Suite tablosundaki manifest verilerini wp_hap_profile_modules'a toplu senkronize eder.
	 *
	 * @param array $args {
	 *     @type bool     $dry_run                   Varsayılan false.
	 *     @type string[] $statuses                  Varsayılan ['profile_core','profile_optional'].
	 *     @type bool     $update_result_enabled      Varsayılan true.
	 *     @type bool     $preserve_manual_overrides  Varsayılan true (şimdilik bilgi amaçlı).
	 *     @type bool     $include_tool_only          Varsayılan false.
	 *     @type bool     $include_disabled           Varsayılan false.
	 * }
	 * @return array Rapor dizisi.
	 */
	public static function sync_modules_from_suite( $args = array() ) {
		global $wpdb;

		$defaults = array(
			'dry_run'                   => false,
			'statuses'                  => array( 'profile_core', 'profile_optional' ),
			'update_result_enabled'     => true,
			'preserve_manual_overrides' => true,
			'include_tool_only'         => false,
			'include_disabled'          => false,
		);
		$args = wp_parse_args( $args, $defaults );

		if ( ! class_exists( 'HAP_Suite_Module_Fields' ) || ! HAP_Suite_Module_Fields::table_exists() ) {
			return array( 'error' => 'Suite tablosu bulunamadı.' );
		}

		$modules_table = $wpdb->prefix . HAP_TABLE_MODULES;
		$statuses      = array_values( (array) $args['statuses'] );
		if ( $args['include_tool_only'] ) {
			$statuses[] = 'tool_only';
		}
		if ( $args['include_disabled'] ) {
			$statuses[] = 'disabled';
		}
		$statuses = array_unique( $statuses );

		$placeholders = implode( ', ', array_fill( 0, count( $statuses ), '%s' ) );
		$modules      = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM `{$modules_table}` WHERE profile_status IN ({$placeholders}) ORDER BY profile_status, slug", // phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
				$statuses
			),
			ARRAY_A
		);

		$report = array(
			'total'           => count( $modules ),
			'updated'         => 0,
			'backend_enabled' => 0,
			'result_disabled' => 0,
			'pending_adapter' => 0,
			'skipped'         => 0,
			'dry_run'         => (bool) $args['dry_run'],
			'details'         => array(),
		);

		$now = current_time( 'mysql' );

		foreach ( $modules as $module ) {
			$slug     = $module['slug'];
			$manifest = HAP_Suite_Module_Fields::get_module_manifest( $slug );

			if ( ! $manifest ) {
				$report['skipped']++;
				continue;
			}

			$backend_supported = (bool) $manifest['backend_supported'];
			$profile_status    = $module['profile_status'];

			$runner_type   = $backend_supported ? 'calculate_api' : 'pending_adapter';
			$runner_status = $backend_supported ? 'ok' : 'pending_adapter';

			// result_enabled kuralı
			$result_enabled = (int) $module['result_enabled'];
			if ( $args['update_result_enabled'] ) {
				if ( in_array( $profile_status, array( 'profile_core', 'profile_optional' ), true ) ) {
					$new_result_enabled = $backend_supported ? 1 : 0;
				} else {
					$new_result_enabled = 0;
				}
				if ( $new_result_enabled < $result_enabled ) {
					$report['result_disabled']++;
				}
				$result_enabled = $new_result_enabled;
			}

			// onboarding_prompt_enabled kuralı
			$onboarding_prompt_enabled = (int) $module['onboarding_prompt_enabled'];
			if ( ! in_array( $profile_status, array( 'profile_core', 'profile_optional' ), true ) ) {
				$onboarding_prompt_enabled = 0;
			} elseif ( ! $backend_supported ) {
				$onboarding_prompt_enabled = 1;
			}

			// ai_include kuralı: null/boş ise otomatik belirle
			$ai_include = (int) $module['ai_include'];
			if ( '' === (string) $module['ai_include'] || null === $module['ai_include'] ) {
				$ai_include = ( $manifest['ai_useful'] && in_array( $profile_status, array( 'profile_core', 'profile_optional' ), true ) ) ? 1 : 0;
			}

			if ( $backend_supported ) {
				$report['backend_enabled']++;
			}
			if ( 'pending_adapter' === $runner_status ) {
				$report['pending_adapter']++;
			}

			$required_fields_json = wp_json_encode( $manifest['required_fields'] );
			$input_mapping_json   = wp_json_encode( $manifest['input_mapping'] );

			$report['details'][] = array(
				'slug'              => $slug,
				'profile_status'    => $profile_status,
				'backend_supported' => $backend_supported,
				'result_enabled'    => $result_enabled,
				'runner_type'       => $runner_type,
				'runner_status'     => $runner_status,
			);
			$report['updated']++;

			if ( ! $args['dry_run'] ) {
				$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
					$modules_table,
					array(
						'suite_source'             => 'hc_module_fields',
						'suite_last_synced_at'     => $now,
						'suite_backend_supported'  => $backend_supported ? 1 : 0,
						'suite_section'            => $manifest['section'] ?? '',
						'suite_required_fields'    => $required_fields_json,
						'suite_input_mapping'      => $input_mapping_json,
						'required_fields'          => $required_fields_json,
						'input_mapping'            => $input_mapping_json,
						'runner_type'              => $runner_type,
						'runner_status'            => $runner_status,
						'result_enabled'           => $result_enabled,
						'onboarding_prompt_enabled' => $onboarding_prompt_enabled,
						'ai_include'               => $ai_include,
					),
					array( 'id' => (int) $module['id'] ),
					array( '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%d' ),
					array( '%d' )
				);
			}
		}

		return $report;
	}

	private function build_formats_for_row( array $row ) {
		$formats = array();
		foreach ( $row as $key => $value ) {
			if ( in_array( $key, array( 'ai_enabled', 'sort_order', 'result_enabled', 'onboarding_prompt_enabled', 'ai_include', 'share_include_default' ), true ) ) {
				$formats[] = '%d';
			} else {
				$formats[] = '%s';
			}
		}
		return $formats;
	}
}
