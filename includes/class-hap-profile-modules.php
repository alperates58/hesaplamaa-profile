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
			'profile_status'       => '',
			'section'              => '',
			'availability_status'  => '',
			'search'               => '',
			'orderby'              => 'sort_order',
			'order'                => 'ASC',
			'limit'                => 50,
			'offset'               => 0,
		);
		$args = wp_parse_args( $args, $defaults );

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
		if ( ! empty( $args['search'] ) ) {
			$where[]  = '(title LIKE %s OR slug LIKE %s)';
			$like     = '%' . $wpdb->esc_like( $args['search'] ) . '%';
			$params[] = $like;
			$params[] = $like;
		}

		$where_sql  = implode( ' AND ', $where );
		$orderby    = in_array( $args['orderby'], array( 'sort_order', 'title', 'section', 'created_at' ), true ) ? $args['orderby'] : 'sort_order';
		$order      = strtoupper( $args['order'] ) === 'DESC' ? 'DESC' : 'ASC';
		$limit      = absint( $args['limit'] );
		$offset     = absint( $args['offset'] );

		if ( ! empty( $params ) ) {
			$sql = $wpdb->prepare(
				"SELECT * FROM {$table} WHERE {$where_sql} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d",
				array_merge( $params, array( $limit, $offset ) )
			);
		} else {
			$sql = $wpdb->prepare(
				"SELECT * FROM {$table} WHERE {$where_sql} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d",
				$limit, $offset
			);
		}

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

		$where_sql = implode( ' AND ', $where );

		if ( ! empty( $params ) ) {
			$sql = $wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE {$where_sql}",
				$params
			);
		} else {
			$sql = "SELECT COUNT(*) FROM {$table} WHERE {$where_sql}";
		}

		return (int) $wpdb->get_var( $sql );
	}

	public function get_module_by_slug( $slug ) {
		global $wpdb;
		$table = $this->table();
		$slug  = sanitize_key( $slug );
		return $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE slug = %s", $slug ),
			ARRAY_A
		);
	}

	public function get_module_by_id( $id ) {
		global $wpdb;
		$table = $this->table();
		return $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", absint( $id ) ),
			ARRAY_A
		);
	}

	public function save_module( array $data, $id = null ) {
		global $wpdb;
		$table = $this->table();
		$now   = current_time( 'mysql' );

		$required_fields = isset( $data['required_fields'] ) ? $data['required_fields'] : array();
		if ( is_array( $required_fields ) ) {
			$required_fields_json = wp_json_encode( $required_fields );
		} else {
			$required_fields_json = $required_fields;
		}

		$row = array(
			'slug'                    => sanitize_key( $data['slug'] ?? '' ),
			'title'                   => sanitize_text_field( $data['title'] ?? '' ),
			'shortcode'               => sanitize_text_field( $data['shortcode'] ?? '' ),
			'section'                 => sanitize_key( $data['section'] ?? '' ),
			'profile_status'          => sanitize_key( $data['profile_status'] ?? 'disabled' ),
			'required_fields'         => $required_fields_json,
			'missing_fields_behavior' => sanitize_key( $data['missing_fields_behavior'] ?? 'show_prompt' ),
			'ai_enabled'              => ! empty( $data['ai_enabled'] ) ? 1 : 0,
			'sort_order'              => absint( $data['sort_order'] ?? 0 ),
			'notes'                   => sanitize_textarea_field( $data['notes'] ?? '' ),
			'source'                  => sanitize_key( $data['source'] ?? 'manual' ),
			'availability_status'     => sanitize_key( $data['availability_status'] ?? 'active' ),
			'updated_at'              => $now,
		);

		$format = array( '%s','%s','%s','%s','%s','%s','%s','%d','%d','%s','%s','%s','%s' );

		if ( $id ) {
			$wpdb->update( $table, $row, array( 'id' => absint( $id ) ), $format, array( '%d' ) );
			return absint( $id );
		} else {
			$existing = $this->get_module_by_slug( $row['slug'] );
			if ( $existing ) {
				$wpdb->update( $table, $row, array( 'slug' => $row['slug'] ), $format, array( '%s' ) );
				return (int) $existing['id'];
			}
			$row['created_at'] = $now;
			$insert_format     = array_merge( $format, array( '%s' ) );
			$wpdb->insert( $table, $row, $insert_format );
			return (int) $wpdb->insert_id;
		}
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

		$section_map = array(
			// İngilizce
			'astrology'          => 'astrology',
			'health'             => 'health_lifestyle',
			'health_lifestyle'   => 'health_lifestyle',
			'sport'              => 'sport_activity',
			'sport_activity'     => 'sport_activity',
			'numerology'         => 'numerology',
			'chinese'            => 'chinese_astrology',
			'chinese_astrology'  => 'chinese_astrology',
			'symbolic'           => 'symbolic',
			'tarot'              => 'tarot',
			'moon'               => 'moon_sky',
			'moon_sky'           => 'moon_sky',
			'houses'             => 'astrology_houses',
			'astrology_houses'   => 'astrology_houses',
			'overview'           => 'overview',
			// Türkçe yaklaşım
			'astroloji'          => 'astrology',
			'saglik_spor'        => 'health_lifestyle',
			'numeroloji_sembolik'=> 'numerology',
			'diger'              => 'overview',
			'spor'               => 'sport_activity',
			'numeroloji'         => 'numerology',
			'sembolik'           => 'symbolic',
			'cin_astrolojisi'    => 'chinese_astrology',
			'ay_gokyuzu'         => 'moon_sky',
		);

		$valid_sections = array_values( array_unique( $section_map ) );

		foreach ( $json_data as $item ) {
			if ( empty( $item['slug'] ) ) {
				$skipped++;
				continue;
			}

			$slug        = sanitize_key( $item['slug'] );
			$raw_section = sanitize_key( $item['section'] ?? '' );
			$section     = $section_map[ $raw_section ] ?? ( in_array( $raw_section, $valid_sections, true ) ? $raw_section : 'overview' );
			$existing    = $this->get_module_by_slug( $slug );

			// Shortcode uyumsuzluğu notu
			$given_sc    = sanitize_text_field( $item['shortcode'] ?? '' );
			$expected_sc = '[hc_' . str_replace( '-', '_', $slug ) . ']';
			$sc_note     = ( $given_sc && $given_sc !== $expected_sc )
				? '[import] shortcode_mismatch: given=' . $given_sc . '; '
				: '';

			$module_data = array(
				'slug'                    => $slug,
				'title'                   => sanitize_text_field( $item['title'] ?? '' ),
				'shortcode'               => $given_sc,
				'section'                 => $section,
				// Mevcut modülde profile_status/required_fields korunur, yoksa varsayılan
				'profile_status'          => $existing ? $existing['profile_status'] : 'tool_only',
				'required_fields'         => $existing ? json_decode( $existing['required_fields'], true ) ?: array() : array(),
				'missing_fields_behavior' => $existing ? $existing['missing_fields_behavior'] : 'show_prompt',
				'ai_enabled'              => $existing ? (int) $existing['ai_enabled'] : 0,
				'sort_order'              => $existing ? (int) $existing['sort_order'] : 0,
				'notes'                   => $sc_note . sanitize_textarea_field( $item['notes'] ?? ( $existing ? $existing['notes'] : '' ) ),
				'source'                  => 'json_import',
				'availability_status'     => sanitize_key( $item['availability_status'] ?? ( $existing ? $existing['availability_status'] : 'active' ) ),
			);

			$result = $this->save_module( $module_data, $existing ? (int) $existing['id'] : null );
			if ( $result ) {
				if ( $existing ) {
					$updated++;
				} else {
					$inserted++;
				}
			} else {
				$errors[] = $slug;
			}
		}

		$total  = $inserted + $updated + $skipped;
		$report = array(
			'inserted' => $inserted,
			'updated'  => $updated,
			'skipped'  => $skipped,
			'total'    => $total,
			'errors'   => $errors,
			'time'     => current_time( 'mysql' ),
		);
		update_option( 'hap_profile_last_import_report', $report, false );

		return $report;
	}

	public function get_sections_summary() {
		global $wpdb;
		$table = $this->table();
		$rows  = $wpdb->get_results(
			"SELECT section, profile_status, availability_status, COUNT(*) as cnt FROM {$table} GROUP BY section, profile_status, availability_status",
			ARRAY_A
		);
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
		$rows  = $wpdb->get_results(
			"SELECT profile_status, COUNT(*) as cnt FROM {$table} GROUP BY profile_status",
			ARRAY_A
		);
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
		if ( is_array( $raw ) ) {
			return $raw;
		}
		$decoded = json_decode( $raw, true );
		return is_array( $decoded ) ? $decoded : array();
	}
}
