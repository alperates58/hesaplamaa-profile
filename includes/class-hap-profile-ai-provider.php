<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * AI rapor üretim sağlayıcısı.
 * API key hardcode edilmez, loglanmaz.
 * Ayar yoksa sistem bozulmaz; status pending_configuration döner.
 */
class HAP_Profile_AI_Provider {

	const SETTINGS_OPTION = 'hap_profile_ai_settings';

	// -------------------------------------------------------
	// Ayarlar
	// -------------------------------------------------------

	/**
	 * @return array
	 */
	public static function get_settings() {
		$defaults = array(
			'ai_enabled'                        => 0,
			'ai_provider'                       => 'deepseek',
			'ai_model'                          => 'deepseek-chat',
			'ai_temperature'                    => 0.7,
			'ai_max_tokens'                     => 4000,
			'ai_report_length'                  => 'medium',
			'ai_auto_generate_after_onboarding' => 0,
			'ai_api_key_option_name'            => 'hap_ai_api_key',
		);
		$saved = get_option( self::SETTINGS_OPTION, array() );
		if ( ! is_array( $saved ) ) {
			$saved = array();
		}
		return array_merge( $defaults, $saved );
	}

	/**
	 * @param array $data
	 * @return bool
	 */
	public static function save_settings( $data ) {
		$settings = self::get_settings();
		$allowed  = array( 'ai_enabled', 'ai_provider', 'ai_model', 'ai_temperature', 'ai_max_tokens', 'ai_report_length', 'ai_auto_generate_after_onboarding', 'ai_api_key_option_name' );
		foreach ( $allowed as $key ) {
			if ( isset( $data[ $key ] ) ) {
				$settings[ $key ] = $data[ $key ];
			}
		}
		return update_option( self::SETTINGS_OPTION, $settings, false );
	}

	/**
	 * AI etkin mi?
	 */
	public static function is_enabled() {
		$settings = self::get_settings();
		return ! empty( $settings['ai_enabled'] ) && '' !== self::get_api_key();
	}

	// -------------------------------------------------------
	// Rapor üretimi
	// -------------------------------------------------------

	/**
	 * Ana rapor üretme metodu.
	 * Başarılı olursa normalize edilmiş yanıt döner.
	 * Başarısız olursa ['error' => '...', 'status' => '...'] döner.
	 *
	 * @param array $payload  build_ai_payload çıktısı
	 * @return array
	 */
	public static function generate_report( $payload ) {
		if ( ! self::is_enabled() ) {
			$key = self::get_api_key();
			$status = '' === $key ? 'pending_configuration' : 'ai_disabled';
			return array( 'error' => 'AI devre dışı veya yapılandırılmamış.', 'status' => $status );
		}

		$settings = self::get_settings();
		$prompt   = self::build_prompt( $payload, $settings );

		switch ( $settings['ai_provider'] ) {
			case 'deepseek':
				$response = self::call_deepseek( $prompt, $settings );
				break;
			default:
				return array( 'error' => 'Bilinmeyen AI sağlayıcı.', 'status' => 'pending_configuration' );
		}

		if ( isset( $response['error'] ) ) {
			return $response;
		}

		return self::sanitize_ai_response( $response );
	}

	/**
	 * Kullanıcı profil verisinden Türkçe prompt oluşturur.
	 *
	 * @param array $payload
	 * @param array $settings
	 * @return string
	 */
	public static function build_prompt( $payload, $settings = array() ) {
		if ( empty( $settings ) ) {
			$settings = self::get_settings();
		}

		$length_map = array(
			'short'  => '800-1200',
			'medium' => '1500-2500',
			'long'   => '2500-3500',
		);
		$target_words = $length_map[ $settings['ai_report_length'] ?? 'medium' ] ?? '1500-2500';

		$profile_lines = array();
		foreach ( $payload['profile_fields'] ?? array() as $key => $value ) {
			$profile_lines[] = "- {$key}: {$value}";
		}

		$results_lines = array();
		foreach ( $payload['ready_results'] ?? array() as $slug => $result ) {
			$value = $result['value'] ?? ( $result['result'] ?? '' );
			if ( $value ) {
				$results_lines[] = "- {$slug}: {$value}";
			}
		}

		$pending = implode( ', ', array_slice( $payload['future_results_summary'] ?? array(), 0, 10 ) );

		$profile_text  = implode( "\n", $profile_lines ) ?: '(Profil verisi yok)';
		$results_text  = implode( "\n", $results_lines ) ?: '(Henüz hesaplanmış sonuç yok)';
		$pending_text  = $pending ?: 'Yok';

		$prompt = <<<PROMPT
Sen, kişisel analiz asistanısın. Aşağıdaki kullanıcı profil verilerini ve hesaplama sonuçlarını kullanarak kapsamlı, sıcak ve ilham verici Türkçe bir kişisel analiz raporu oluştur.

**KURALLAR:**
- Tıbbi, finansal veya hukuki kesin tavsiye verme.
- Sağlık bilgilerini yalnızca bilgilendirme amaçlı yorum yap.
- Astroloji ve numeroloji bulgularını eğlence ve kişisel farkındalık perspektifinden sun.
- Korkutucu, kaderci veya manipülatif dil kullanma.
- Kullanıcının güçlü yönlerini, gelişim alanlarını ve pratik önerilerini vurgula.
- Yanıtı kategorilere böl ve her kategori için ayrı başlık kullan.
- Hedef uzunluk: {$target_words} kelime.

**KULLANICI PROFİLİ:**
{$profile_text}

**HESAPLAMA SONUÇLARI:**
{$results_text}

**YAKINDA GELECEKSİ ANALİZLER (dahil etme, yalnızca göster):**
{$pending_text}

**RAPOR FORMATI:**
Yanıtını şu JSON formatında döndür:
{
  "summary": "2-3 cümlelik özet",
  "sections": {
    "genel_bakis": "...",
    "saglik_yasam": "...",
    "astroloji": "...",
    "numeroloji": "...",
    "oneriler": "..."
  },
  "full_report": "Tüm bölümleri içeren tam rapor metni"
}
PROMPT;

		return $prompt;
	}

	/**
	 * DeepSeek API'yi çağırır.
	 * API key loglara yazdırılmaz.
	 *
	 * @param string $prompt
	 * @param array  $settings
	 * @return array
	 */
	public static function call_deepseek( $prompt, $settings ) {
		$api_key = self::get_api_key();
		if ( '' === $api_key ) {
			return array( 'error' => 'API anahtarı bulunamadı.', 'status' => 'pending_configuration' );
		}

		$body = wp_json_encode(
			array(
				'model'       => sanitize_text_field( $settings['ai_model'] ?? 'deepseek-chat' ),
				'messages'    => array(
					array( 'role' => 'user', 'content' => $prompt ),
				),
				'temperature' => (float) ( $settings['ai_temperature'] ?? 0.7 ),
				'max_tokens'  => absint( $settings['ai_max_tokens'] ?? 4000 ),
			)
		);

		$response = wp_remote_post(
			'https://api.deepseek.com/v1/chat/completions',
			array(
				'timeout' => 90,
				'headers' => array(
					'Authorization' => 'Bearer ' . $api_key,
					'Content-Type'  => 'application/json',
				),
				'body'    => $body,
			)
		);

		if ( is_wp_error( $response ) ) {
			return array( 'error' => 'API bağlantı hatası.', 'status' => 'failed' );
		}

		$code = wp_remote_retrieve_response_code( $response );
		if ( 200 !== (int) $code ) {
			return array( 'error' => "API HTTP {$code} hatası.", 'status' => 'failed' );
		}

		$body_raw = wp_remote_retrieve_body( $response );
		$data     = json_decode( $body_raw, true );
		if ( ! is_array( $data ) ) {
			return array( 'error' => 'API yanıtı geçersiz JSON.', 'status' => 'failed' );
		}

		$content = $data['choices'][0]['message']['content'] ?? '';
		if ( '' === $content ) {
			return array( 'error' => 'API boş içerik döndürdü.', 'status' => 'failed' );
		}

		return array(
			'raw_content'       => $content,
			'tokens_prompt'     => absint( $data['usage']['prompt_tokens'] ?? 0 ),
			'tokens_completion' => absint( $data['usage']['completion_tokens'] ?? 0 ),
			'model'             => sanitize_text_field( $data['model'] ?? $settings['ai_model'] ?? '' ),
		);
	}

	/**
	 * AI yanıtını güvenli hale getirir; doğrudan gösterilmeden önce çağrılmalıdır.
	 *
	 * @param array $response  call_deepseek çıktısı
	 * @return array
	 */
	public static function sanitize_ai_response( $response ) {
		$content = $response['raw_content'] ?? '';

		// JSON bloğunu temizle (```json ... ``` işaretlerini kaldır)
		$content = preg_replace( '/^```json\s*/i', '', trim( $content ) );
		$content = preg_replace( '/\s*```$/', '', $content );

		$parsed = json_decode( trim( $content ), true );

		if ( ! is_array( $parsed ) ) {
			// JSON parse başarısız olduysa tüm metni full_report olarak sakla.
			$parsed = array(
				'summary'     => '',
				'sections'    => array(),
				'full_report' => $content,
			);
		}

		$sections = array();
		if ( is_array( $parsed['sections'] ?? null ) ) {
			foreach ( $parsed['sections'] as $k => $v ) {
				$sections[ sanitize_key( $k ) ] = wp_kses_post( (string) $v );
			}
		}

		return array(
			'summary'           => sanitize_textarea_field( $parsed['summary'] ?? '' ),
			'full_report'       => wp_kses_post( $parsed['full_report'] ?? $content ),
			'sections'          => $sections,
			'model'             => sanitize_text_field( $response['model'] ?? '' ),
			'language'          => 'tr',
			'tone'              => 'friendly',
			'tokens_prompt'     => absint( $response['tokens_prompt'] ?? 0 ),
			'tokens_completion' => absint( $response['tokens_completion'] ?? 0 ),
		);
	}

	// -------------------------------------------------------
	// Yardımcı
	// -------------------------------------------------------

	/**
	 * API anahtarını güvenli option'dan okur. Loglanmaz.
	 *
	 * @return string
	 */
	private static function get_api_key() {
		$settings     = self::get_settings();
		$option_name  = sanitize_key( $settings['ai_api_key_option_name'] ?? 'hap_ai_api_key' );
		$key          = (string) get_option( $option_name, '' );
		return trim( $key );
	}
}
