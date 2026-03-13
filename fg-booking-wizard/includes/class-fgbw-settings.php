<?php
if (!defined('ABSPATH')) exit;

class FGBW_Settings {
    public function init(): void {
        add_action('admin_menu',  [$this, 'menu']);
        add_action('admin_init',  [$this, 'register_settings']);
        // Enqueue WP media library on our settings page so the logo picker works
        add_action('admin_enqueue_scripts', [$this, 'enqueue_media']);
    }

    public function menu(): void {
        add_options_page(
            'FG Booking Wizard',
            'FG Booking Wizard',
            'manage_options',
            'fgbw-settings',
            [$this, 'render']
        );
    }

    public function enqueue_media(string $hook): void {
        if ($hook !== 'settings_page_fgbw-settings') return;
        wp_enqueue_media();
        // Inline JS that wires the "Choose Logo" button to the WP media frame
        wp_add_inline_script('media-upload', "
jQuery(function($){
    var mediaFrame;
    $(document).on('click', '#fgbw-choose-logo', function(e){
        e.preventDefault();
        if (mediaFrame) { mediaFrame.open(); return; }
        mediaFrame = wp.media({
            title: 'Select Email Logo',
            button: { text: 'Use this image' },
            multiple: false,
            library: { type: 'image' }
        });
        mediaFrame.on('select', function(){
            var attachment = mediaFrame.state().get('selection').first().toJSON();
            $('#fgbw_email_logo_url').val(attachment.url);
            $('#fgbw-logo-preview').attr('src', attachment.url).show();
        });
        mediaFrame.open();
    });
    $(document).on('click', '#fgbw-remove-logo', function(e){
        e.preventDefault();
        $('#fgbw_email_logo_url').val('');
        $('#fgbw-logo-preview').hide().attr('src','');
    });
});
        ");
    }

    public function register_settings(): void {
        register_setting('fgbw_settings_group', 'fgbw_settings', [
            'type'              => 'array',
            'sanitize_callback' => [$this, 'sanitize_settings'],
            'default'           => [],
        ]);

        // ── Section: General ──────────────────────────────────────────────────
        add_settings_section('fgbw_api', 'General Settings', '__return_false', 'fgbw-settings');

        add_settings_field('google_places_key', 'Google Places API Key',        [$this, 'field_google'],      'fgbw-settings', 'fgbw_api');
        add_settings_field('aviationstack_key', 'Aviationstack API Key',         [$this, 'field_aviation'],    'fgbw-settings', 'fgbw_api');
        add_settings_field('admin_email',       'Admin Notification Email',      [$this, 'field_admin_email'], 'fgbw-settings', 'fgbw_api');

        // ── Section: Email ────────────────────────────────────────────────────
        add_settings_section('fgbw_email_sect', 'Email Settings', [$this, 'email_section_desc'], 'fgbw-settings');

        add_settings_field('email_logo_url',       'Email Logo',           [$this, 'field_email_logo'],      'fgbw-settings', 'fgbw_email_sect');
        add_settings_field('email_customer_subject','Customer Email Subject',[$this, 'field_customer_subject'],'fgbw-settings', 'fgbw_email_sect');
        add_settings_field('email_admin_subject',  'Admin Email Subject',  [$this, 'field_admin_subject'],   'fgbw-settings', 'fgbw_email_sect');
    }

    public function sanitize_settings($input): array {
        $input = is_array($input) ? $input : [];

        $out = [];
        $out['google_places_key']      = sanitize_text_field($input['google_places_key'] ?? '');
        $out['aviationstack_key']      = sanitize_text_field($input['aviationstack_key'] ?? '');
        $out['admin_email']            = sanitize_email($input['admin_email'] ?? get_option('admin_email'));
        $out['email_logo_url']         = esc_url_raw($input['email_logo_url'] ?? '');
        $out['email_customer_subject'] = sanitize_text_field($input['email_customer_subject'] ?? 'Your Reservation Was Successfully Submitted!');
        $out['email_admin_subject']    = sanitize_text_field($input['email_admin_subject'] ?? 'New Reservation Submitted - {name}');

        // Preserve legacy body keys if they exist (so old stored values are not deleted on save)
        $out['email_customer_body'] = $input['email_customer_body'] ?? (get_option('fgbw_settings')['email_customer_body'] ?? '');
        $out['email_admin_body']    = $input['email_admin_body']    ?? (get_option('fgbw_settings')['email_admin_body']    ?? '');

        return $out;
    }

    public function render(): void {
        if (!current_user_can('manage_options')) return;
        echo '<div class="wrap"><h1>FG Booking Wizard Settings</h1>';
        echo '<form method="post" action="options.php">';
        settings_fields('fgbw_settings_group');
        do_settings_sections('fgbw-settings');
        submit_button('Save Settings');
        echo '</form></div>';
    }

    // ── Field renderers ───────────────────────────────────────────────────────

    public function field_google(): void {
        $v = esc_attr(fgbw_get_option('google_places_key', ''));
        echo "<input type='text' class='regular-text' name='fgbw_settings[google_places_key]' value='{$v}' />";
        echo "<p class='description'>Exposed to frontend JS — required for address autocomplete &amp; airport search.</p>";
    }

    public function field_aviation(): void {
        $v = esc_attr(fgbw_get_option('aviationstack_key', ''));
        echo "<input type='password' class='regular-text' name='fgbw_settings[aviationstack_key]' value='{$v}' autocomplete='new-password' />";
        echo "<p class='description'>Server-side only — used to validate flight numbers.</p>";
    }

    public function field_admin_email(): void {
        $v = esc_attr(fgbw_get_option('admin_email', get_option('admin_email')));
        echo "<input type='email' class='regular-text' name='fgbw_settings[admin_email]' value='{$v}' />";
        echo "<p class='description'>New booking notifications are sent to this address.</p>";
    }

    public function email_section_desc(): void {
        echo "<p style='color:#6b7280;'>These settings control how notification emails look and are addressed. The email body is generated automatically from the built-in template — no manual editing required.</p>";
    }

    public function field_email_logo(): void {
        $v       = esc_url(fgbw_get_option('email_logo_url', ''));
        $default = FGBW_PLUGIN_URL . 'assets/images/email-logo.png';
        $preview = $v ?: $default;
        ?>
        <div style="display:flex;align-items:flex-start;gap:16px;flex-wrap:wrap;">
          <div>
            <img id="fgbw-logo-preview"
                 src="<?php echo esc_url($preview); ?>"
                 alt="Logo preview"
                 style="max-height:64px;max-width:200px;border:1px solid #ddd;border-radius:4px;padding:6px;background:#fff;display:block;margin-bottom:8px;" />
            <input type="hidden"
                   id="fgbw_email_logo_url"
                   name="fgbw_settings[email_logo_url]"
                   value="<?php echo esc_attr($v); ?>" />
            <button type="button" id="fgbw-choose-logo" class="button button-secondary">
              <?php echo $v ? 'Change Logo' : 'Choose Logo'; ?>
            </button>
            <?php if ($v) : ?>
              <button type="button" id="fgbw-remove-logo" class="button button-link-delete" style="margin-left:8px;">
                Remove
              </button>
            <?php endif; ?>
          </div>
          <div>
            <p class="description" style="margin-top:0;">Select your logo from the <strong>Media Library</strong>.<br>
            Recommended: PNG or WebP, transparent background, max 300px wide.<br>
            If left empty, the default Optimus Fleets logo is used.</p>
          </div>
        </div>
        <?php
    }

    public function field_customer_subject(): void {
        $v = esc_attr(fgbw_get_option('email_customer_subject', 'Your Reservation Was Successfully Submitted!'));
        echo "<input type='text' class='large-text' name='fgbw_settings[email_customer_subject]' value='{$v}' />";
        echo "<p class='description'>Subject line for the confirmation email sent to the customer. You may use <code>{name}</code> and <code>{booking_id}</code>.</p>";
    }

    public function field_admin_subject(): void {
        $v = esc_attr(fgbw_get_option('email_admin_subject', 'New Reservation Submitted - {name}'));
        echo "<input type='text' class='large-text' name='fgbw_settings[email_admin_subject]' value='{$v}' />";
        echo "<p class='description'>Subject line for the admin notification email. You may use <code>{name}</code> and <code>{booking_id}</code>.</p>";
    }
}
