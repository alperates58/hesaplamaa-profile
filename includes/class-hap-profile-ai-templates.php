<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HAP_Profile_AI_Templates {

	private $option_key = 'hap_profile_ai_templates';

	private $template_keys = array(
		'overview_prompt'           => 'Genel Bakış',
		'health_prompt'             => 'Sağlık & Yaşam',
		'sport_prompt'              => 'Spor & Aktivite',
		'astrology_prompt'          => 'Astroloji',
		'numerology_prompt'         => 'Numeroloji',
		'chinese_astrology_prompt'  => 'Çin Astrolojisi',
		'symbolic_prompt'           => 'Sembolik',
		'tarot_prompt'              => 'Tarot',
	);

	private $default_templates = array(
		'overview_prompt'          => 'Kullanıcının genel profilini analiz et. Doğum tarihi: {birth_date}. Eğlence ve kişisel farkındalık amaçlı, pozitif bir dille yorum yap.',
		'health_prompt'            => 'Kullanıcının sağlık verilerini değerlendir. Bu analiz yalnızca bilgilendirme amaçlıdır; tıbbi tavsiye niteliği taşımaz.',
		'sport_prompt'             => 'Kullanıcının aktivite düzeyini ve spor verilerini değerlendir.',
		'astrology_prompt'         => 'Bu yorum eğlence ve kişisel farkındalık amaçlıdır. Kesin öneriler yapma.',
		'numerology_prompt'        => 'Numeroloji yorumu eğlence ve kişisel farkındalık amaçlıdır.',
		'chinese_astrology_prompt' => 'Çin astrolojisi yorumu eğlence amaçlıdır.',
		'symbolic_prompt'          => 'Sembolik analiz kişisel farkındalık amaçlıdır.',
		'tarot_prompt'             => 'Tarot yorumu eğlence ve kişisel farkındalık amaçlıdır.',
	);

	public function get_template_keys() {
		return $this->template_keys;
	}

	public function get_templates() {
		$saved = get_option( $this->option_key, null );
		if ( null === $saved ) {
			return $this->default_templates;
		}
		return wp_parse_args( $saved, $this->default_templates );
	}

	public function get_template( $key ) {
		$templates = $this->get_templates();
		return $templates[ $key ] ?? '';
	}

	public function save_templates( array $data ) {
		$sanitized = array();
		foreach ( $this->template_keys as $key => $label ) {
			$sanitized[ $key ] = sanitize_textarea_field( $data[ $key ] ?? '' );
		}
		update_option( $this->option_key, $sanitized );
		return true;
	}
}
