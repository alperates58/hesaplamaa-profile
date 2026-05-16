<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$auth_settings = wp_parse_args(
	get_option( 'hap_profile_auth_settings', array() ),
	HAP_Profile_Auth::defaults()
);

$login_url = '';
if ( ! empty( $auth_settings['profile_login_page_id'] ) ) {
	$login_url = get_permalink( absint( $auth_settings['profile_login_page_id'] ) );
}
if ( ! $login_url ) {
	$login_url = wp_login_url();
}

$error_msg   = '';
$success_msg = '';

/**
 * Aksiyon: hap_process_register_form
 * Faz 2'de bu hook üzerinden kayıt işlemi gerçekleştirilecek.
 *
 * @param string $error_msg   Referans: Hata mesajı
 * @param string $success_msg Referans: Başarı mesajı
 */
do_action_ref_array( 'hap_process_register_form', array( &$error_msg, &$success_msg ) );
?>
<div class="hap-auth-wrap hap-register-wrap">

	<?php if ( $error_msg ) : ?>
	<div class="hap-auth-alert hap-auth-alert-error"><?php echo wp_kses_post( $error_msg ); ?></div>
	<?php endif; ?>

	<?php if ( $success_msg ) : ?>
	<div class="hap-auth-alert hap-auth-alert-success"><?php echo wp_kses_post( $success_msg ); ?></div>
	<?php endif; ?>

	<?php if ( ! $success_msg ) : ?>

	<!-- SOSYAL GİRİŞ — hook noktası, Faz 2'de Google OAuth buraya eklenir -->
	<?php if ( ! empty( $auth_settings['enable_google_login_hint'] ) ) : ?>
	<div class="hap-social-login">
		<?php
		/**
		 * Aksiyon: hap_google_login_button
		 * Google OAuth butonu Faz 2'de buraya eklenir.
		 * Örnek kullanım: add_action('hap_google_login_button', 'my_google_oauth_button');
		 */
		do_action( 'hap_google_login_button', 'register' );
		?>
		<?php if ( ! has_action( 'hap_google_login_button' ) ) : ?>
		<button type="button" class="hap-btn hap-btn-google hap-btn-placeholder" disabled title="Yakında aktif olacak">
			<span class="hap-google-icon">
				<svg width="18" height="18" viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg">
					<path d="M43.6 20.3H42V20H24v8h11.3C33.7 32.6 29.3 36 24 36c-6.6 0-12-5.4-12-12s5.4-12 12-12c3.1 0 5.8 1.1 8 2.9l5.7-5.7C34.5 6.5 29.5 4 24 4 12.9 4 4 12.9 4 24s8.9 20 20 20 20-8.9 20-20c0-1.3-.1-2.6-.4-3.7z" fill="#FFC107"/>
					<path d="M6.3 14.7l6.6 4.8C14.7 16 19.1 12 24 12c3.1 0 5.8 1.1 8 2.9l5.7-5.7C34.5 6.5 29.5 4 24 4c-7.7 0-14.3 4.3-17.7 10.7z" fill="#FF3D00"/>
					<path d="M24 44c5.3 0 10.1-1.9 13.8-5.1l-6.4-5.4C29.2 35.5 26.7 36 24 36c-5.3 0-9.7-3.4-11.3-8l-6.6 5.1C9.7 39.7 16.4 44 24 44z" fill="#4CAF50"/>
					<path d="M43.6 20.3H42V20H24v8h11.3c-.8 2.2-2.2 4.1-4 5.5l6.4 5.4C43.1 34.8 44 29.7 44 24c0-1.3-.1-2.6-.4-3.7z" fill="#1976D2"/>
				</svg>
			</span>
			Google ile Kayıt Ol
			<span class="hap-coming-soon">Yakında</span>
		</button>
		<?php endif; ?>
		<div class="hap-divider"><span>veya e-posta ile kayıt ol</span></div>
	</div>
	<?php endif; ?>

	<!-- KAYIT FORMU -->
	<form class="hap-auth-form" id="hap-register-form" method="post" novalidate>
		<?php wp_nonce_field( 'hap_register_action', 'hap_register_nonce' ); ?>
		<input type="hidden" name="hap_action" value="hap_register">

		<div class="hap-form-field">
			<label for="hap-reg-email">E-posta Adresi <span class="hap-req-star">*</span></label>
			<input type="email" id="hap-reg-email" name="hap_email"
			       value="<?php echo esc_attr( $_POST['hap_email'] ?? '' ); ?>"
			       class="hap-input" required autocomplete="email" placeholder="ornek@email.com">
		</div>

		<div class="hap-form-field">
			<label for="hap-reg-username">Kullanıcı Adı <span class="hap-req-star">*</span></label>
			<input type="text" id="hap-reg-username" name="hap_username"
			       value="<?php echo esc_attr( $_POST['hap_username'] ?? '' ); ?>"
			       class="hap-input" required autocomplete="username" placeholder="kullaniciadi">
		</div>

		<div class="hap-form-field">
			<label for="hap-reg-password">Şifre <span class="hap-req-star">*</span></label>
			<div class="hap-input-wrap">
				<input type="password" id="hap-reg-password" name="hap_password"
				       class="hap-input" required autocomplete="new-password" placeholder="••••••••">
				<button type="button" class="hap-pw-toggle" aria-label="Şifreyi göster/gizle">👁</button>
			</div>
		</div>

		<!-- TURNSTILE — hook noktası, Faz 2'de Cloudflare Turnstile widget eklenir -->
		<?php if ( ! empty( $auth_settings['enable_turnstile_hint'] ) ) : ?>
		<div class="hap-turnstile-wrap">
			<?php
			/**
			 * Aksiyon: hap_turnstile_field
			 * Turnstile widget Faz 2'de buraya eklenir.
			 * Örnek: add_action('hap_turnstile_field', 'my_turnstile_render', 10, 1);
			 */
			do_action( 'hap_turnstile_field', 'register' );
			?>
			<?php if ( ! has_action( 'hap_turnstile_field' ) ) : ?>
			<div class="hap-turnstile-placeholder">
				<span class="hap-turnstile-icon">🛡</span>
				<span>Bot koruması (Turnstile) Faz 2'de aktif olacak</span>
			</div>
			<?php endif; ?>
		</div>
		<?php endif; ?>

		<!-- GİZLİLİK ONAYI -->
		<div class="hap-form-field hap-consent-field">
			<label class="hap-checkbox-label">
				<input type="checkbox" name="hap_consent" value="1" required>
				<span>
					<a href="<?php echo esc_url( get_privacy_policy_url() ?: '#' ); ?>" target="_blank">Gizlilik Politikası</a>'nı
					ve <a href="#" target="_blank">Kullanım Koşulları</a>'nı okudum, kabul ediyorum.
					<span class="hap-req-star">*</span>
				</span>
			</label>
		</div>

		<?php if ( ! empty( $auth_settings['require_email_verification'] ) ) : ?>
		<div class="hap-info-box">
			📧 Kayıt sonrası e-posta adresinizi doğrulamanız gerekecektir.
		</div>
		<?php endif; ?>

		<div class="hap-form-footer">
			<button type="submit" class="hap-btn hap-btn-primary hap-btn-full">
				Hesap Oluştur
			</button>
		</div>

		<p class="hap-auth-switch">
			Zaten hesabınız var mı?
			<a href="<?php echo esc_url( $login_url ); ?>">Giriş yapın</a>
		</p>

		<!-- Gizlilik uyarısı — hassas veri toplamıyoruz bildirimi -->
		<p class="hap-auth-disclaimer">
			Şifreniz güvenli şekilde şifrelenerek saklanır. Kişisel verileriniz üçüncü şahıslarla paylaşılmaz.
		</p>

		<!-- Hata/başarı alanı — JS ile doldurulur -->
		<div id="hap-register-msg" class="hap-ajax-msg" aria-live="polite"></div>
	</form>

	<?php endif; ?>
</div>
