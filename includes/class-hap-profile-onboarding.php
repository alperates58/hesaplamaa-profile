<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HAP_Profile_Onboarding {

	private $fields;
	private $user_data;

	public function __construct( HAP_Profile_Fields $fields, HAP_Profile_User_Data $user_data ) {
		$this->fields    = $fields;
		$this->user_data = $user_data;
	}

	public function get_steps() {
		return array(
			'basic'      => array(
				'id'          => 'basic',
				'number'      => 1,
				'title'       => 'Kisisel profilini olusturalim',
				'subtitle'    => 'Birkac temel bilgiyle sana ozel analiz panelini hazirlayacagiz.',
				'description' => 'Ilk dashboard kilidini acmak icin temel profil bilgilerini tamamla.',
				'fields'      => array( 'nickname', 'birth_date', 'gender', 'city' ),
				'optional'    => false,
				'why'         => 'Bu bilgiler temel analiz kategorilerini acmak ve paneli sana gore hazirlamak icin kullanilir.',
				'cta'         => 'Kaydet ve Devam Et',
			),
			'health'     => array(
				'id'          => 'health',
				'number'      => 2,
				'title'       => 'Gunluk yasam analizlerini acalim',
				'subtitle'    => 'Saglik ve aktivite kartlarini kullanabilmek icin yasam verilerini ekle.',
				'description' => 'Boy, kilo ve aktivite duzeyi bilgileri saglik ve spor analizleri icin onceliklidir.',
				'fields'      => array( 'height', 'weight', 'activity_level', 'sleep_hours', 'daily_steps' ),
				'optional'    => true,
				'why'         => 'Ozellikle boy, kilo ve aktivite seviyesi bilgileri olmadan saglik ve spor kartlari kilitli kalir.',
				'cta'         => 'Saglik Bilgilerini Kaydet',
			),
			'astrology'  => array(
				'id'          => 'astrology',
				'number'      => 3,
				'title'       => 'Dogum haritani detaylandiralim',
				'subtitle'    => 'Astrolojik detaylari acmak icin dogum saati ve dogum yeri gerekiyor.',
				'description' => 'Dogum saati ve yeri, yukselen burc ve ev yerlesimleri gibi detayli analizler icin gereklidir.',
				'fields'      => array( 'birth_time', 'birth_place' ),
				'optional'    => true,
				'why'         => 'Temel burc yorumlari icin dogum tarihi yeterli olabilir; ama detayli harita yorumlari icin bu adim gerekir.',
				'cta'         => 'Astroloji Bilgilerini Kaydet',
			),
			'numerology' => array(
				'id'          => 'numerology',
				'number'      => 4,
				'title'       => 'Numeroloji profilini olusturalim',
				'subtitle'    => 'Isim temelli numeroloji kartlari icin ad ve soyad bilgilerini ekle.',
				'description' => 'Ad ve soyad bilgileri isim temelli numeroloji analizleri icin kullanilir.',
				'fields'      => array( 'first_name', 'last_name' ),
				'optional'    => true,
				'why'         => 'Isim bazli numeroloji kartlari bu bilgi olmadan hazir durumuna gecmez.',
				'cta'         => 'Numeroloji Bilgilerini Kaydet',
			),
			'complete'   => array(
				'id'          => 'complete',
				'number'      => 5,
				'title'       => 'Profilin hazir',
				'subtitle'    => 'Artik kisisel analiz panelini acabiliriz.',
				'description' => 'Temel profilin tamamlandi. Diledigin zaman eksik alanlari panel icinden tamamlayabilirsin.',
				'fields'      => array(),
				'optional'    => false,
				'why'         => 'Ana panelde hangi analizlerin hazir oldugunu ve hangi bilgilerle yeni kartlar acabilecegini goreceksin.',
				'cta'         => 'Analiz panelimi ac',
			),
		);
	}

	public function get_step_order() {
		return array_keys( $this->get_steps() );
	}

	public function get_step( $step_id ) {
		$steps = $this->get_steps();
		return $steps[ $step_id ] ?? null;
	}

	public function get_field_options( $key ) {
		switch ( $key ) {
			case 'gender':
				return array(
					''           => '- Seciniz -',
					'male'       => 'Erkek',
					'female'     => 'Kadin',
					'other'      => 'Diger',
					'prefer_not' => 'Belirtmek istemiyorum',
				);
			case 'activity_level':
				return array(
					''            => '- Seciniz -',
					'sedentary'   => 'Hareketsiz',
					'light'       => 'Hafif aktif',
					'moderate'    => 'Orta aktif',
					'active'      => 'Aktif',
					'very_active' => 'Cok aktif',
				);
			default:
				return array();
		}
	}

	public function get_step_fields( $step_id ) {
		$step = $this->get_step( $step_id );
		if ( ! $step ) {
			return array();
		}

		$fields = array();
		foreach ( $step['fields'] as $key ) {
			$field = $this->fields->get_field_by_key( $key );
			if ( $field ) {
				$fields[] = $field;
			}
		}

		return $fields;
	}

	public function get_initial_step( $user_id ) {
		$user_id = absint( $user_id );
		if ( ! $this->user_data->is_minimum_profile_complete( $user_id ) ) {
			return 'basic';
		}

		$profile = $this->user_data->get_user_profile_data( $user_id );
		foreach ( array( 'health', 'astrology', 'numerology' ) as $step_id ) {
			$has_content = false;
			foreach ( $this->get_step( $step_id )['fields'] as $field_key ) {
				if ( $this->user_data->is_field_filled( $field_key, $profile ) ) {
					$has_content = true;
					break;
				}
			}
			if ( ! $has_content ) {
				return $step_id;
			}
		}

		return 'complete';
	}

	public function handle_save_step() {
		check_ajax_referer( 'hap_profile_nonce', 'nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => 'Giris yapmaniz gerekiyor.' ) );
		}

		$step_id = isset( $_POST['step'] ) ? sanitize_key( wp_unslash( $_POST['step'] ) ) : '';
		$step    = $this->get_step( $step_id );

		if ( ! $step || 'complete' === $step_id ) {
			wp_send_json_error( array( 'message' => 'Gecersiz onboarding adimi.' ) );
		}

		$payload = isset( $_POST['profile_data'] ) ? (array) $_POST['profile_data'] : array();
		$data    = array();
		foreach ( $step['fields'] as $field_key ) {
			if ( array_key_exists( $field_key, $payload ) ) {
				$data[ $field_key ] = wp_unslash( $payload[ $field_key ] );
			}
		}

		$user_id = get_current_user_id();
		$this->user_data->save_user_data( $user_id, $data );

		$profile = $this->user_data->get_user_profile_data( $user_id );
		if ( 'basic' === $step_id ) {
			$missing = $this->user_data->get_minimum_profile_missing_fields( $user_id, $profile );
			if ( ! empty( $missing ) ) {
				wp_send_json_error(
					array(
						'message'        => 'Temel profil alanlarini tamamlaman gerekiyor.',
						'missing_fields' => $missing,
					)
				);
			}
		}

		$order         = $this->get_step_order();
		$current_index = array_search( $step_id, $order, true );
		$next_step     = false !== $current_index && isset( $order[ $current_index + 1 ] ) ? $order[ $current_index + 1 ] : 'complete';

		wp_send_json_success(
			array(
				'message'              => 'Onboarding adimi kaydedildi.',
				'next_step'            => $next_step,
				'minimum_complete'     => $this->user_data->is_minimum_profile_complete( $user_id, $profile ),
				'minimum_completion'   => $this->user_data->get_minimum_profile_completion( $user_id, $profile ),
				'missing_minimum'      => $this->user_data->get_minimum_profile_missing_fields( $user_id, $profile ),
			)
		);
	}
}
