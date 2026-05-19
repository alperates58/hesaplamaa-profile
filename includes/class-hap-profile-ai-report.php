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

		// Name Resolution Logic
		$first_name = $profile['first_name'] ?? get_user_meta( $user_id, 'first_name', true );
		$last_name  = $profile['last_name'] ?? get_user_meta( $user_id, 'last_name', true );
		$nickname   = $profile['nickname'] ?? get_user_meta( $user_id, 'nickname', true );
		
		$wp_user = get_userdata( $user_id );
		$display_name = $wp_user ? $wp_user->display_name : '';

		// Remove technical/role names
		$forbidden = array( 'admin', 'administrator', 'site admin', 'yönetici' );
		if ( in_array( strtolower( trim( $display_name ) ), $forbidden, true ) ) {
			$display_name = '';
		}

		$preferred_name    = '';
		$report_title_name = '';

		if ( ! empty( $first_name ) ) {
			$preferred_name = $first_name;
			$report_title_name = ! empty( $last_name ) ? $first_name . ' ' . $last_name : $first_name;
		} elseif ( ! empty( $nickname ) ) {
			$preferred_name = $nickname;
			$report_title_name = $nickname;
		} elseif ( ! empty( $display_name ) ) {
			$preferred_name = $display_name;
			$report_title_name = $display_name;
		} else {
			// Absolute fallback
			$preferred_name = ''; // Will result in just "Merhaba"
			$report_title_name = 'Kullanıcı';
		}

		$context['preferred_name']    = $preferred_name;
		$context['report_title_name'] = $report_title_name;

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
		if ( ! empty( $profile['current_city'] ) ) {
			$context['Yaşadığı Şehir'] = $profile['current_city'];
		}
		if ( ! empty( $profile['relationship_status'] ) ) {
			$context['İlişki Durumu'] = $profile['relationship_status'];
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
			// Skip results explicitly marked as not safe for AI report
			if ( isset( $result['ai_safe_for_report'] ) && ! $result['ai_safe_for_report'] ) {
				continue;
			}

			$section = 'Diğer Kişisel Sonuçlar';
			$title   = $slug;
			if ( isset( $module_map[ $slug ] ) ) {
				$section = $module_map[ $slug ]['section'] ?: 'Diğer Kişisel Sonuçlar';
				$title   = $module_map[ $slug ]['title'];
			}
			
			if ( ! isset( $categorized[ $section ] ) ) {
				$categorized[ $section ] = array();
			}
			
			$categorized[ $section ][] = array(
				'title'   => $title,
				'value'   => $result['value'] ?? '',
				'unit'    => $result['unit'] ?? '',
				'label'   => $result['label'] ?? '',
				'summary' => $result['summary'] ?? '',
				'status'  => $result['status'] ?? '',
				'meaning' => $result['meaning'] ?? '',
			);
		}
		
		return $categorized;
	}

	public function get_result_hash( $user_id, $profile, $categorized_results ) {
		// Create a hash based on the input data that matters
		$data = array(
			'prompt_version' => 'v2', // Increment this if prompt structure changes significantly to invalidate old cache
			'profile'        => $profile,
			'results'        => $categorized_results,
		);
		return md5( wp_json_encode( $data ) );
	}

	public function build_prompt( $user_id, $profile, $categorized_results, $settings ) {
		$preferred_name    = $profile['preferred_name'] ?? 'Kullanıcı';
		$report_title_name = $profile['report_title_name'] ?? 'Kullanıcı';

		$system_prompt = "Sen Hesaplamaa.com için çalışan, profesyonel, bilge ve empatik bir kişisel analiz danışmanısın.\n";
		$system_prompt .= "Görevin: Sana verilen deterministic hesaplama sonuçlarını (Astroloji, Numeroloji, Sağlık, Tarot vb.) profesyonel bir dille YORUMLAMAKTIR.\n\n";
		
		$system_prompt .= "KATI KURALLAR:\n";
		$system_prompt .= "1. Tıbbi teşhis yok: Sağlık bölümünde hastalık iddiasında bulunma, ilaç önerme. Mutlaka şu cümleyi geçir: 'Bu bölüm bilgilendirme amaçlıdır; tıbbi teşhis veya tedavi önerisi değildir.'\n";
		$system_prompt .= "2. Kesin kader dili yok: 'Kesin böylesin', 'mutlaka olacak' gibi ifadeler kullanma. 'Öne çıkabilir', 'potansiyelin var' gibi yumuşak ifadeler kullan.\n";
		$system_prompt .= "3. Ham tablo/liste tekrarı YASAK: Sonuçları kopyalayıp mekanik liste halinde sunma. Sonuçları birbirine bağla, ortak temalar çıkar ve anlamlı paragraflar halinde yaz.\n";
		$system_prompt .= "4. Veri uydurma: Sadece sana verilen sonuçları yorumla.\n";
		$system_prompt .= "5. Hesaplama yapma: Mevcut sonuçlar üzerinden yorum yap.\n";

		if ( ! empty( $settings['ds_custom_prompt'] ) ) {
			$system_prompt .= "\nÖzel Sistem Talimatları (Admin):\n" . $settings['ds_custom_prompt'] . "\n";
		}

		$user_prompt = "Lütfen aşağıdaki profil verileri ve hesaplama sonuçları ışığında $preferred_name için kapsamlı, sıcak ve profesyonel bir kişisel analiz raporu hazırla.\n\n";
		
		$user_prompt .= "YAZIM VE FORMAT AYARLARI:\n";
		$user_prompt .= "- Ton: " . ( $settings['ds_tone'] ?? 'Dost canlısı ve profesyonel' ) . "\n";
		$user_prompt .= "- Detay Seviyesi: " . ( $settings['ds_detail'] ?? 'Çok detaylı' ) . " (Yüzeysel özet geçme. Rakamların, gezegenlerin, sembollerin ne anlama geldiğini ve kişinin hayatına etkisini derinlemesine anlat.)\n";
		$user_prompt .= "- Uzunluk: " . ( $settings['ds_length'] ?? 'Uzun' ) . "\n";
		
		if ( ! empty( $settings['ds_use_name'] ) ) {
			$user_prompt .= "- Başlık Formatı: \"$report_title_name için Kişisel Analiz Raporu\" şeklinde başla.\n";
			$user_prompt .= "- Hitap: Girişte ve raporun içinde ara ara \"$preferred_name\" diyerek kişiye ismen hitap et.\n";
		}

		$user_prompt .= "- Format: Markdown kullan. Bölüm başlıklarında mutlaka ikon/emoji kullan (Örn: ✨ Genel Tema, 🌙 Astroloji, 🔢 Numeroloji, 💚 Sağlık, 🏃 Aktivite, 🧭 Yol Haritası).\n";

		$user_prompt .= "\nRAPOR İSKELETİ BEKLENTİSİ:\n";
		$user_prompt .= "1. Giriş: Sıcak karşılama.\n";
		$user_prompt .= "2. ✨ Genel Tema Haritası: Çıkan sonuçların tümüne bakarak kişide baskın olan 3-5 ana temayı (örn: duygusal derinlik, dönüşüm gücü vs.) tespit edip yorumla.\n";
		$user_prompt .= "3. [Kategoriler]: Astroloji, Numeroloji, Sembolik, İlişki, Sağlık, Aktivite vb. sana verilen kategorileri sırayla, birbirleriyle bağ kurarak, paragraf paragraf yorumla.\n";
		$user_prompt .= "4. 🧭 Yol Haritası & Farkındalık: Küçük pratik alışkanlık önerileri ve içsel farkındalık sorularıyla kapanış.\n\n";

		$user_prompt .= "--- VERİLER ---\n\n";
		
		$user_prompt .= "Kullanıcı Profili:\n";
		foreach ( $profile as $k => $v ) {
			if ( in_array( $k, array( 'first_name', 'last_name', 'nickname', 'preferred_name', 'report_title_name' ), true ) ) {
				continue; // Skip raw names in prompt body, we already used them in instructions
			}
			$user_prompt .= "- $k: $v\n";
		}
		
		$user_prompt .= "\nHazır Hesaplama Sonuçları:\n";
		if ( empty( $categorized_results ) ) {
			$user_prompt .= "(Henüz yeterli sonuç yok. Profil bilgilerine dayanarak genel bir yorum yap.)\n";
		} else {
			foreach ( $categorized_results as $section => $results ) {
				$user_prompt .= "Kategori: [$section]\n";
				foreach ( $results as $r ) {
					$val     = $r['value'] . ( $r['unit'] ? ' ' . $r['unit'] : '' );
					$summary = ! empty( $r['summary'] ) ? ' - Özet: ' . $r['summary'] : '';
					$meaning = ! empty( $r['meaning'] ) ? ' - Anlamı: ' . $r['meaning'] : '';
					$user_prompt .= "  • " . $r['title'] . ": " . $val . " (" . $r['label'] . ")" . $summary . $meaning . "\n";
				}
				$user_prompt .= "\n";
			}
		}

		$user_prompt .= "\nLütfen şimdi raporu yazmaya başla.";

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
