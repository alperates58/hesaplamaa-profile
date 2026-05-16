<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$auth_settings = wp_parse_args(
	get_option( 'hap_profile_auth_settings', array() ),
	HAP_Profile_Auth::defaults()
);

$register_url = '';
if ( ! empty( $auth_settings['profile_register_page_id'] ) ) {
	$register_url = get_permalink( absint( $auth_settings['profile_register_page_id'] ) );
}
if ( ! $register_url ) {
	$register_url = wp_registration_url();
}

$redirect_url = '';
if ( ! empty( $auth_settings['redirect_after_login_page_id'] ) ) {
	$redirect_url = get_permalink( absint( $auth_settings['redirect_after_login_page_id'] ) );
}
if ( ! $redirect_url ) {
	$redirect_url = home_url( '/' );
}

$error_msg   = '';
$success_msg = '';

/**
 * Aksiyon: hap_process_login_form
 * Faz 2'de bu hook üzerinden giriş işlemi gerçekleştirilecek.
 *
 * @param string $error_msg   Referans: Hata mesajı
 * @param string $success_msg Referans: Başarı mesajı
 */
do_action_ref_array( 'hap_process_login_form', array( &$error_msg, &$success_msg ) );
?>
<div class="hap-auth-wrap hap-login-wrap">

	<?php if ( $error_msg ) : ?>
	<div class="hap-auth-alert hap-auth-alert-error"><?php echo wp_kses_post( $error_msg ); ?></div>
	<?php endif; ?>

	<?php if ( $success_msg ) : ?>
	<div class="hap-auth-alert hap-auth-alert-success"><?php echo wp_kses_post( $success_msg ); ?></div>
	<?php endif; ?>

	<!-- SOSYAL GİRİŞ — hook noktası -->
	<?php if ( ! empty( $auth_settings['enable_google_login_hint'] ) ) : ?>
	<div class="hap-social-login">
		<?php
		/**
		 * Aksiyon: hap_google_login_button
		 * Google OAuth butonu Faz 2'de buraya eklenir.
		 */
		do_action( 'hap_google_login_button', 'login' );
		?>
		<?php if ( ! has_action( 'hap_google_login_button' ) ) : ?>
		<button type="button" class="hap-btn hap-btn-google hap-btn-placeholder" disabled title="Yakında aktif olacak">
			<span class="hap-google-icon">
				<svg width="18" height="18" viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg">
					<path d="M43.6 20.3H42V20H24v8h11.3C33.7 32.6 29.3 36 24 36c-6.6 0-12-5.4-12-12s5.4-12 12-12c3.1 0 5.8 1.1 8 2.9l5.7-5.7C34.5 6.5 29.5 4 24 12.9 4 4 12.9 4 24s8.9 20 20 20 20-8.9 20-20c0-1.3-.1-2.6-.4-3.7z" fill="#FFC107"/>
					<path d="M6.3 14.7l6.6 4.8C14.7 16 19.1 12 24 12c3.1 0 5.8 1.1 8 2.9l5.7-5.7C34.5 6.5 29.5 4 24 4c-7.7 0-14.3 4.3-17.7 10.7z" fill="#FF3D00"/>
					<path d="M24 44c5.3 0 10.1-1.9 13.8-5.1l-6.4-5.4C29.2 35.5 26.7 36 24 36c-5.3 0-9.7-3.4-11.3-8l-6.6 5.1C9.7 39.7 16.4 44 24 44z" fill="#4CAF50"/>
					<path d="M43.6 20.3H42V20H24v8h11.3c-.8 2.2-2.2 4.1-4 5.5l6.4 5.4C43.1 34.8 44 29.7 44 24c0-1.3-.1-2.6-.4-3.7z" fill="#1976D2"/>
				</svg>
			</span>
			Google ile Giriş Yap
			<span class="hap-coming-soon">Yakında</span>
		</button>
		<?php endif; ?>
		<div class="hap-divider"><span>veya e-posta ile giriş yap</span></div>
	</div>
	<?php endif; ?>

	<!-- GİRİŞ FORMU -->
	<form class="hap-auth-form" id="hap-login-form" method="post" novalidate>
		<?php wp_nonce_field( 'hap_login_action', 'hap_login_nonce' ); ?>
		<input type="hidden" name="hap_action" value="hap_login">
		<input type="hidden" name="redirect_to" value="<?php echo esc_attr( $redirect_url ); ?>">

		<div class="hap-form-field">
			<label for="hap-login-user">E-posta veya Kullanıcı Adı <span class="hap-req-star">*</span></label>
			<input type="text" id="hap-login-user" name="hap_username"
			       value="<?php echo esc_attr( $_POST['hap_username'] ?? '' ); ?>"
			       class="hap-input" required autocomplete="username" placeholder="e-posta veya kullanıcı adı">
		</div>

		<div class="hap-form-field">
			<label for="hap-login-password">Şifre <span class="hap-req-star">*</span></label>
			<div class="hap-input-wrap">
				<input type="password" id="hap-login-password" name="hap_password"
				       class="hap-input" required autocomplete="current-password" placeholder="••••••••">
				<button type="button" class="hap-pw-toggle" aria-label="Şifreyi göster/gizle">👁</button>
			</div>
		</div>

		<div class="hap-form-field hap-remember-row">
			<label class="hap-checkbox-label">
				<input type="checkbox" name="hap_rememberme" value="1">
				<span>Beni hatırla</span>
			</label>
			<a href="<?php echo esc_url( wp_lostpassword_url( get_permalink() ) ); ?>" class="hap-forgot-link">
				Şifremi unuttum
			</a>
		</div>

		<!-- TURNSTILE — hook noktası -->
		<?php if ( ! empty( $auth_settings['enable_turnstile_hint'] ) ) : ?>
		<div class="hap-turnstile-wrap">
			<?php
			/**
			 * Aksiyon: hap_turnstile_field
			 * Turnstile widget Faz 2'de buraya eklenir.
			 */
			do_action( 'hap_turnstile_field', 'login' );
			?>
			<?php if ( ! has_action( 'hap_turnstile_field' ) ) : ?>
			<div class="hap-turnstile-placeholder">
				<span class="hap-turnstile-icon">🛡</span>
				<span>Bot koruması (Turnstile) Faz 2'de aktif olacak</span>
			</div>
			<?php endif; ?>
		</div>
		<?php endif; ?>

		<div class="hap-form-footer">
			<button type="submit" class="hap-btn hap-btn-primary hap-btn-full">
				Giriş Yap
			</button>
		</div>

		<?php if ( ! empty( $auth_settings['enable_profile_registration_page'] ) && $register_url ) : ?>
		<p class="hap-auth-switch">
			Hesabınız yok mu?
			<a href="<?php echo esc_url( $register_url ); ?>">Ücretsiz kayıt olun</a>
		</p>
		<?php endif; ?>

		<!-- Hata/başarı alanı — JS ile doldurulur -->
		<div id="hap-login-msg" class="hap-ajax-msg" aria-live="polite"></div>
	</form>

</div>
