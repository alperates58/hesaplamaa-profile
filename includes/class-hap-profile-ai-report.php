<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HAP_Profile_AI_Report {

	public function get_settings() {
		return get_option( 'hap_profile_settings', array() );
	}

	public function is_enabled() {
		$settings = $this->get_settings();
		return ! empty( $settings['ds_ai_active'] ) && ! empty( $settings['ds_api_key'] );
	}

	public function user_has_ai_consent( $user_id ) {
		if ( ! class_exists( 'HAP_Profile_Consents' ) ) {
			return false;
		}
		return HAP_Profile_Consents::has_ai_consent( $user_id );
	}

	public function collect_profile_context( $user_id ) {
		try {
			if ( ! class_exists( 'HAP_Profile_Fields' ) || ! class_exists( 'HAP_Profile_User_Data' ) ) {
				return new WP_Error( 'missing_classes', 'Profil verileri okunamadı. Lütfen daha sonra tekrar deneyin.' );
			}
			$fields_obj = new HAP_Profile_Fields();
			$user_data  = new HAP_Profile_User_Data( $fields_obj );
			$profile    = $user_data->get_user_profile_data( $user_id );
		} catch ( Exception $e ) {
			return new WP_Error( 'profile_error', 'Profil verileri okunamadı. Lütfen daha sonra tekrar deneyin.' );
		} catch ( Error $e ) {
			return new WP_Error( 'profile_error', 'Profil verileri okunamadı. Lütfen daha sonra tekrar deneyin.' );
		}
		
		if ( ! is_array( $profile ) ) {
			$profile = array();
		}
		
		// Map some readable names
		$context = array();
		$context['Cinsiyet'] = isset( $profile['gender'] ) ? ( $profile['gender'] === 'female' ? 'Kadın' : 'Erkek' ) : 'Belirtilmedi';
		
		if ( ! empty( $profile['birth_date'] ) ) {
			$context['Doğum Tarihi'] = $profile['birth_date'];
			try {
				$dob = new DateTime( $profile['birth_date'] );
				$now = new DateTime();
				$age = $now->diff( $dob )->y;
				$context['Yaş'] = $age;
			} catch ( Exception $e ) {
				// skip
			}
		}
		if ( ! empty( $profile['birth_time'] ) ) {
			$context['Doğum Saati'] = $profile['birth_time'];
		}
		if ( ! empty( $profile['birth_place'] ) ) {
			$context['Doğum Yeri'] = $profile['birth_place'];
		}
		
		return $context;
	}

	public function collect_ready_results( $user_id ) {
		if ( ! class_exists( 'HAP_Profile_Results_Store' ) ) {
			return array();
		}
		return HAP_Profile_Results_Store::get_all_results( $user_id );
	}

	public function categorize_results( $results ) {
		// Attempt to categorize results based on module definitions or heuristics
		$categorized = array();
		$modules = new HAP_Profile_Modules();
		$all_modules = $modules->get_modules();
		
		$module_map = array();
		foreach ( $all_modules as $m ) {
			$module_map[ $m['slug'] ] = $m;
		}

		foreach ( $results as $slug => $result ) {
			$section = 'Diğer';
			$title   = $slug;
			if ( isset( $module_map[ $slug ] ) ) {
				$section = $module_map[ $slug ]['section'] ?: 'Diğer';
				$title   = $module_map[ $slug ]['title'];
			}
			
			if ( ! isset( $categorized[ $section ] ) ) {
				$categorized[ $section ] = array();
			}
			$categorized[ $section ][] = array(
				'title' => $title,
				'value' => $result['value'] ?? '',
				'unit'  => $result['unit'] ?? '',
				'label' => $result['label'] ?? '',
			);
		}
		
		return $categorized;
	}

	public function get_result_hash( $user_id, $profile, $categorized_results ) {
		// Create a hash based on the input data that matters
		$data = array(
			'profile' => $profile,
			'results' => $categorized_results,
		);
		return md5( wp_json_encode( $data ) );
	}

	public function build_prompt( $user_id, $profile, $categorized_results, $settings ) {
		$user_info = get_userdata( $user_id );
		$name = $settings['ds_use_name'] ? ( $user_info->first_name ?: $user_info->display_name ) : 'Kullanıcı';

		$system_prompt = "Sen Hesaplamaa.com kişisel analiz asistanısın. Sana verilen deterministic hesaplama sonuçlarını yorumlarsın. Yeni hesaplama yapmazsın, veri uydurmazsın, tıbbi teşhis veya tedavi önerisi vermezsin, kesin kader yorumu yapmazsın. Türkçe, güvenli, anlaşılır ve kullanıcı dostu yazarsın.\n";
		
		$system_prompt .= "\nKurallar:\n";
		$system_prompt .= "- Sağlık bölümünde mutlaka şu cümle yer alsın: 'Bu bölüm bilgilendirme amaçlıdır; tıbbi teşhis veya tedavi önerisi değildir.'\n";
		$system_prompt .= "- Astroloji, numeroloji veya sembolik alanlarda 'Kesin böylesin', 'mutlaka olacak', 'kaderinde var' gibi kesin kader dili kullanma.\n";
		$system_prompt .= "- Hesaplama yapma.\n";
		$system_prompt .= "- Sadece sana verilen sonuçları yorumla, verilmeyen sonucu uydurma.\n";
		
		if ( ! empty( $settings['ds_custom_prompt'] ) ) {
			$system_prompt .= "\nEk Talimatlar:\n" . $settings['ds_custom_prompt'] . "\n";
		}

		$user_prompt = "Aşağıdaki profile ve hazır hesaplama sonuçlarına dayanarak detaylı bir kişisel analiz raporu oluştur.\n\n";
		
		// Yazım Ayarları
		$user_prompt .= "Yazım Ayarları:\n";
		$user_prompt .= "- Ton: " . $settings['ds_tone'] . "\n";
		$user_prompt .= "- Detay Seviyesi: " . $settings['ds_detail'] . " (Yüzeysel özet geçme, her kategori için açıklayıcı paragraflar yaz.)\n";
		$user_prompt .= "- Hedef Uzunluk: " . $settings['ds_length'] . "\n";
		if ( $settings['ds_use_name'] ) {
			$user_prompt .= "- Kullanıcıya adıyla hitap et (Adı: $name).\n";
		}
		if ( $settings['ds_single_results'] ) {
			$user_prompt .= "- Her kategorideki sonuçları (en az 3-5 tanesini) birbirine bağlayarak detaylıca yorumla.\n";
		}
		if ( $settings['ds_use_headers'] ) {
			$user_prompt .= "- Rapor iskeletini ve Kategori başlıklarını belirgin kullan.\n";
		}
		if ( $settings['ds_add_tips'] ) {
			$user_prompt .= "- Raporun sonunda uygulanabilir farkındalık önerileri ekle.\n";
		}
		
		$user_prompt .= "\nRapor İskeleti:\n";
		$user_prompt .= "1. Giriş\n2. Genel Profil Özeti\n3. [Kategoriler]\n4. Güçlü Temalar\n5. Dikkat Edilebilecek Noktalar\n6. Kapanış ve Farkındalık Önerileri\n\n";

		// Profil verisi
		$user_prompt .= "Kullanıcı Profili:\n";
		foreach ( $profile as $k => $v ) {
			$user_prompt .= "- $k: $v\n";
		}
		
		// Sonuçlar
		$user_prompt .= "\nHazır Sonuçlar:\n";
		foreach ( $categorized_results as $section => $results ) {
			$user_prompt .= "### Kategori: $section\n";
			foreach ( $results as $r ) {
				$val = $r['value'] . ( $r['unit'] ? ' ' . $r['unit'] : '' );
				$user_prompt .= "- " . $r['title'] . ": " . $val . " (" . $r['label'] . ")\n";
			}
			$user_prompt .= "\n";
		}

		return array(
			array( 'role' => 'system', 'content' => $system_prompt ),
			array( 'role' => 'user', 'content' => $user_prompt ),
		);
	}

	public function call_deepseek( $messages ) {
		$settings = $this->get_settings();
		$api_key = $settings['ds_api_key'] ?? '';
		$model = $settings['ds_model'] ?? 'deepseek-v4-flash';
		$temperature = (float) ( $settings['ds_temperature'] ?? 0.7 );
		$max_tokens = absint( $settings['ds_max_tokens'] ?? 6000 );

		if ( empty( $api_key ) ) {
			return new WP_Error( 'missing_api_key', 'DeepSeek API Key bulunamadı.' );
		}

		$body = array(
			'model'       => $model,
			'messages'    => $messages,
			'temperature' => $temperature,
			'max_tokens'  => $max_tokens,
		);

		$timeout = absint( $settings['ds_api_timeout'] ?? 180 );

		$response = wp_remote_post( 'https://api.deepseek.com/chat/completions', array(
			'headers' => array(
				'Content-Type'  => 'application/json',
				'Authorization' => 'Bearer ' . $api_key,
			),
			'body'    => wp_json_encode( $body ),
			'timeout' => $timeout,
		) );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( $code !== 200 || empty( $data['choices'][0]['message']['content'] ) ) {
			return new WP_Error( 'api_error', 'DeepSeek API hatası: ' . ( $data['error']['message'] ?? 'Bilinmeyen hata' ) );
		}

		return $data['choices'][0]['message']['content'];
	}

	public function start_report_job( $user_id, $force_regenerate = false ) {
		if ( ! $this->is_enabled() ) {
			return new WP_Error( 'ai_disabled', 'AI özelliği kapalı.' );
		}

		if ( ! $this->user_has_ai_consent( $user_id ) ) {
			return new WP_Error( 'no_consent', 'AI kişisel analiz için yapay zeka işleme izni gerekiyor. Lütfen profil ayarlarından onayı tamamlayın.' );
		}

		// Check if there is already a processing job
		$status = get_user_meta( $user_id, '_hap_ai_report_status', true );
		$lock = get_user_meta( $user_id, '_hap_ai_report_lock', true );
		
		if ( in_array( $status, array( 'queued', 'processing' ), true ) ) {
			// Lock süresi dolmuş mu? (15 dakika = 900 saniye)
			if ( $lock && ( time() - $lock < 900 ) ) {
				return new WP_Error( 'already_processing', 'AI raporun zaten hazırlanıyor. Lütfen tamamlanmasını bekleyin.' );
			}
		}

		$settings = $this->get_settings();
		$profile = $this->collect_profile_context( $user_id );
		
		if ( is_wp_error( $profile ) ) {
			return $profile;
		}
		
		$results = $this->collect_ready_results( $user_id );
		$categorized = $this->categorize_results( $results );

		$hash = $this->get_result_hash( $user_id, $profile, $categorized );

		if ( ! $force_regenerate && ! empty( $settings['ds_cache_active'] ) ) {
			$cached = $this->get_cached_report( $user_id, $hash );
			if ( $cached ) {
				return array(
					'success' => true,
					'status'  => 'completed',
					'cached'  => true,
					'report'  => $cached,
				);
			}
		}

		// Yeni job oluştur
		$job_id = uniqid( 'ai_', true );
		
		update_user_meta( $user_id, '_hap_ai_report_job', $job_id );
		update_user_meta( $user_id, '_hap_ai_report_status', 'queued' );
		update_user_meta( $user_id, '_hap_ai_report_hash', $hash );
		update_user_meta( $user_id, '_hap_ai_report_lock', time() );
		update_user_meta( $user_id, '_hap_ai_report_started_at', current_time( 'mysql' ) );
		delete_user_meta( $user_id, '_hap_ai_report_error' );
		delete_user_meta( $user_id, '_hap_ai_report_completed_at' );
		
		// WP-Cron'u tetikle
		wp_schedule_single_event( time() + 2, 'hap_process_ai_report_job', array( $user_id, $job_id ) );

		return array(
			'success' => true,
			'job_id'  => $job_id,
			'status'  => 'queued',
			'message' => 'AI raporun hazırlanıyor.',
		);
	}

	public function get_report_status( $user_id, $job_id ) {
		$current_job_id = get_user_meta( $user_id, '_hap_ai_report_job', true );
		
		if ( $current_job_id !== $job_id ) {
			return new WP_Error( 'invalid_job', 'Geçersiz job ID veya yeni bir job başlatıldı.' );
		}

		$status = get_user_meta( $user_id, '_hap_ai_report_status', true );
		$lock = get_user_meta( $user_id, '_hap_ai_report_lock', true );
		
		// Fallback: Eğer WP-Cron çalışmamışsa ve lock > 60 saniye ise, arka planda hemen çalıştır.
		// Sadece kuyruktaysa bunu yapabiliriz, process ediliyorsa biraz daha bekleyelim.
		if ( 'queued' === $status && $lock && ( time() - $lock > 60 ) ) {
			// Senkron olarak dene
			$this->process_report_job( $user_id, $job_id );
			$status = get_user_meta( $user_id, '_hap_ai_report_status', true );
		}

		if ( 'completed' === $status ) {
			$hash = get_user_meta( $user_id, '_hap_ai_report_hash', true );
			return array(
				'success'      => true,
				'status'       => 'completed',
				'report'       => $this->get_cached_report( $user_id, $hash ),
				'generated_at' => get_user_meta( $user_id, '_hap_ai_report_completed_at', true ),
			);
		} elseif ( 'failed' === $status ) {
			$error = get_user_meta( $user_id, '_hap_ai_report_error', true );
			return array(
				'success' => false,
				'status'  => 'failed',
				'message' => $error ?: 'AI raporu oluşturulamadı. Lütfen daha sonra tekrar deneyin.',
			);
		} else {
			return array(
				'success' => true,
				'status'  => $status, // queued, processing
				'message' => 'Raporun hazırlanıyor...',
			);
		}
	}

	public function process_report_job( $user_id, $job_id ) {
		$current_job_id = get_user_meta( $user_id, '_hap_ai_report_job', true );
		if ( $current_job_id !== $job_id ) {
			return; // Job iptal edilmiş veya değişmiş
		}

		$status = get_user_meta( $user_id, '_hap_ai_report_status', true );
		if ( 'completed' === $status ) {
			return; // Zaten bitmiş
		}

		update_user_meta( $user_id, '_hap_ai_report_status', 'processing' );
		update_user_meta( $user_id, '_hap_ai_report_lock', time() );

		$settings = $this->get_settings();
		$profile = $this->collect_profile_context( $user_id );
		
		if ( is_wp_error( $profile ) ) {
			$this->mark_job_failed( $user_id, $profile->get_error_message() );
			return;
		}

		$results = $this->collect_ready_results( $user_id );
		$categorized = $this->categorize_results( $results );

		$hash = get_user_meta( $user_id, '_hap_ai_report_hash', true );
		
		try {
			$messages = $this->build_prompt( $user_id, $profile, $categorized, $settings );
			$report = $this->call_deepseek( $messages );

			if ( is_wp_error( $report ) ) {
				$this->mark_job_failed( $user_id, $report->get_error_message() );
			} else {
				$this->save_cached_report( $user_id, $hash, $report );
				update_user_meta( $user_id, '_hap_ai_report_status', 'completed' );
				update_user_meta( $user_id, '_hap_ai_report_completed_at', current_time( 'mysql' ) );
			}
		} catch ( Exception $e ) {
			$this->mark_job_failed( $user_id, 'Beklenmeyen bir hata oluştu: ' . $e->getMessage() );
		}
	}

	private function mark_job_failed( $user_id, $error_message ) {
		update_user_meta( $user_id, '_hap_ai_report_status', 'failed' );
		update_user_meta( $user_id, '_hap_ai_report_error', $error_message );
		update_user_meta( $user_id, '_hap_ai_report_lock', 0 ); // Lock'u serbest bırak
	}

	public function get_cached_report( $user_id, $hash ) {
		$saved_hash = get_user_meta( $user_id, '_hap_ai_report_hash', true );
		if ( $saved_hash === $hash ) {
			return get_user_meta( $user_id, '_hap_ai_report_cache', true );
		}
		return false;
	}

	public function save_cached_report( $user_id, $hash, $report ) {
		update_user_meta( $user_id, '_hap_ai_report_hash', $hash );
		update_user_meta( $user_id, '_hap_ai_report_cache', $report );
		update_user_meta( $user_id, '_hap_ai_report_generated_at', current_time( 'mysql' ) );
	}
}
