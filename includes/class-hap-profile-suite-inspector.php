<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Hesaplama Suite modül dosyalarını salt-okuma modunda inceler.
 * Suite dosyalarını değiştirmez, sadece okur.
 */
class HAP_Profile_Suite_Inspector {

	private static $suite_modules_path = null;

	// -------------------------------------------------------
	// Yol tespiti
	// -------------------------------------------------------

	public static function get_suite_modules_path() {
		if ( null !== self::$suite_modules_path ) {
			return self::$suite_modules_path;
		}

		// HC_PLUGIN_DIR Suite aktifse tanımlıdır.
		if ( defined( 'HC_PLUGIN_DIR' ) ) {
			$path = rtrim( HC_PLUGIN_DIR, '/\\' ) . DIRECTORY_SEPARATOR . 'modules' . DIRECTORY_SEPARATOR;
			if ( is_dir( $path ) ) {
				self::$suite_modules_path = $path;
				return $path;
			}
		}

		// Fallback: WP_PLUGIN_DIR altında ara.
		if ( defined( 'WP_PLUGIN_DIR' ) ) {
			foreach ( glob( WP_PLUGIN_DIR . '/*/modules', GLOB_ONLYDIR ) ?: array() as $dir ) {
				$plugin_dir = dirname( $dir );
				if ( file_exists( $plugin_dir . '/hesaplama-suite.php' )
					|| file_exists( $plugin_dir . '/includes/class-calculator-loader.php' ) ) {
					$path = $dir . DIRECTORY_SEPARATOR;
					self::$suite_modules_path = $path;
					return $path;
				}
			}
		}

		self::$suite_modules_path = false;
		return false;
	}

	public static function get_suite_module_path( $slug ) {
		$base = self::get_suite_modules_path();
		if ( ! $base ) {
			return null;
		}
		$dir = $base . sanitize_title( $slug ) . DIRECTORY_SEPARATOR;
		return is_dir( $dir ) ? $dir : null;
	}

	// -------------------------------------------------------
	// Dosya varlık kontrolleri
	// -------------------------------------------------------

	public static function has_calculator_php( $slug ) {
		$path = self::get_suite_module_path( $slug );
		return $path && file_exists( $path . 'calculator.php' );
	}

	public static function has_calculator_js( $slug ) {
		$path = self::get_suite_module_path( $slug );
		return $path && file_exists( $path . 'calculator.js' );
	}

	public static function has_meta_json( $slug ) {
		$path = self::get_suite_module_path( $slug );
		return $path && file_exists( $path . 'meta.json' );
	}

	// -------------------------------------------------------
	// İçerik okuma
	// -------------------------------------------------------

	public static function read_meta_json( $slug ) {
		$path = self::get_suite_module_path( $slug );
		if ( ! $path ) {
			return null;
		}
		$file = $path . 'meta.json';
		if ( ! file_exists( $file ) ) {
			return null;
		}
		$raw = file_get_contents( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		return $raw ? json_decode( $raw, true ) : null;
	}

	/**
	 * Dosya içeriğini okuyup beklenebilir PHP fonksiyon adını döner.
	 * Kod EXECUTE edilmez — sadece string taraması yapılır.
	 */
	public static function detect_php_callable( $slug ) {
		if ( ! self::has_calculator_php( $slug ) ) {
			return null;
		}
		$file    = self::get_suite_module_path( $slug ) . 'calculator.php';
		$content = file_get_contents( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		if ( ! $content ) {
			return null;
		}

		$key = str_replace( '-', '_', sanitize_title( $slug ) );

		// Hesaplama Suite render fonksiyonu — sadece HTML render, hesaplama JS'te.
		$render_fn = 'hc_render_' . $key;
		if ( strpos( $content, 'function ' . $render_fn ) !== false ) {
			return $render_fn; // render fonksiyonu — backend calculate yok
		}

		// Gerçek calculate fonksiyonu mevcut mu?
		if ( preg_match( '/function\s+(hc_calculate_[a-z0-9_]+)\s*\(/i', $content, $m ) ) {
			return $m[1];
		}

		return null;
	}

	public static function detect_shortcode( $slug ) {
		$meta = self::read_meta_json( $slug );
		if ( $meta && ! empty( $meta['shortcode'] ) ) {
			return $meta['shortcode'];
		}
		$key = str_replace( '-', '_', sanitize_title( $slug ) );
		return '[hc_' . $key . ']';
	}

	// -------------------------------------------------------
	// Kapasite tespiti
	// -------------------------------------------------------

	public static function get_capability_status( $slug ) {
		if ( ! self::has_calculator_php( $slug ) ) {
			return 'no_php';
		}

		$callable = self::detect_php_callable( $slug );

		// Gerçek hc_calculate_ fonksiyonu varsa backend callable sayılır.
		if ( $callable && 0 === strpos( $callable, 'hc_calculate_' ) ) {
			return 'php_callable';
		}

		// Sadece render fonksiyonu var; hesaplama JS'te yapılıyor.
		if ( self::has_calculator_js( $slug ) ) {
			return 'frontend_js_only';
		}

		return 'render_only';
	}

	// -------------------------------------------------------
	// Ana inspection metodu
	// -------------------------------------------------------

	public static function inspect_module( $slug ) {
		$slug       = sanitize_title( $slug );
		$meta       = self::read_meta_json( $slug );
		$callable   = self::detect_php_callable( $slug );
		$capability = self::get_capability_status( $slug );

		$notes = array(
			'frontend_js_only' => 'Hesaplama JavaScript tarafında yapılıyor. Backend callable yok; Suite\'e calculate API eklenmesi gerekiyor.',
			'php_callable'     => 'PHP callable mevcut — backend entegrasyonu mümkün.',
			'render_only'      => 'PHP sadece form render ediyor; hesaplama JS\'te.',
			'no_php'           => 'calculator.php bulunamadı.',
		);

		return array(
			'slug'               => $slug,
			'module_path'        => self::get_suite_module_path( $slug ),
			'has_meta_json'      => self::has_meta_json( $slug ),
			'has_calculator_php' => self::has_calculator_php( $slug ),
			'has_calculator_js'  => self::has_calculator_js( $slug ),
			'meta_name'          => $meta['name'] ?? null,
			'shortcode'          => self::detect_shortcode( $slug ),
			'php_callable'       => $callable,
			'capability'         => $capability,
			'notes'              => $notes[ $capability ] ?? '',
		);
	}

	/**
	 * Tüm aktif profil modüllerini (profile_core + profile_optional) inceler.
	 */
	public static function inspect_all_profile_modules() {
		$modules_obj = new HAP_Profile_Modules();
		$modules     = $modules_obj->get_modules( array( 'limit' => 500 ) );

		$results = array();
		foreach ( $modules as $module ) {
			if ( ! in_array( $module['profile_status'], array( 'profile_core', 'profile_optional', 'tool_only' ), true ) ) {
				continue;
			}
			$results[] = self::inspect_module( $module['slug'] );
		}
		return $results;
	}

	/**
	 * Suite yolu çözümlenemiyorsa true döner.
	 */
	public static function suite_unavailable() {
		return false === self::get_suite_modules_path();
	}
}
