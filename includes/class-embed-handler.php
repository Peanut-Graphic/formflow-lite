<?php
/**
 * Embed Handler
 *
 * Handles embeddable widget functionality for external websites.
 * Generates embed codes and serves forms via iframe or JavaScript injection.
 *
 * @package FormFlow
 * @since 2.1.0
 */

namespace FFFL;

use FFFL\Database\Database;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class EmbedHandler
 */
class EmbedHandler {

    /**
     * Database instance
     */
    private Database $db;

    /**
     * Constructor
     */
    public function __construct() {
        $this->db = new Database();
    }

    /**
     * Initialize embed handler
     */
    public function init(): void {
        // Register REST API endpoints for embed
        add_action('rest_api_init', [$this, 'register_rest_routes']);

        // Handle iframe embed requests
        add_action('template_redirect', [$this, 'handle_iframe_request']);

        // Add CORS headers for embed requests
        add_action('rest_api_init', [$this, 'add_cors_headers']);

        // Register embed assets
        add_action('wp_enqueue_scripts', [$this, 'register_embed_assets']);
    }

    /**
     * Register REST API routes for embed
     */
    public function register_rest_routes(): void {
        register_rest_route('fffl/v1', '/embed/config/(?P<token>[a-zA-Z0-9]+)', [
            'methods' => 'GET',
            'callback' => [$this, 'get_embed_config'],
            'permission_callback' => '__return_true',
            'args' => [
                'token' => [
                    'required' => true,
                    'validate_callback' => function($param) {
                        return preg_match('/^[a-zA-Z0-9]{16,64}$/', $param);
                    },
                ],
            ],
        ]);

        // /embed/submit, /embed/validate and /embed/schedule were removed: Lite
        // never issued embed tokens, /submit called a FormHandler method that
        // does not exist, and /validate was an un-nonced account -> PII lookup.
        // Embeds render the real (session-bound) form in the iframe instead.
    }

    /**
     * Add CORS headers for embed requests
     */
    public function add_cors_headers(): void {
        // Only for embed endpoints
        if (strpos($_SERVER['REQUEST_URI'] ?? '', '/fffl/v1/embed') === false) {
            return;
        }

        $allowed_origins = $this->get_allowed_origins();
        $origin = sanitize_text_field($_SERVER['HTTP_ORIGIN'] ?? '');

        foreach (self::cors_headers_for($origin, $allowed_origins) as $cors_header) {
            header($cors_header);
        }

        // Handle preflight requests
        if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
            status_header(200);
            exit;
        }
    }

    /**
     * Decide the CORS response headers for an embed request.
     *
     * Security invariant: a reflected/wildcard origin is NEVER combined with
     * `Access-Control-Allow-Credentials: true`. Reflecting an arbitrary Origin
     * while allowing credentials would let any site read authenticated
     * responses, and browsers reject `Allow-Origin: *` + credentials outright.
     *
     * - Wildcard/open allowlist (the default): emit `Allow-Origin: *` WITHOUT
     *   credentials and do NOT reflect the request Origin.
     * - Explicit allowlist: only reflect the Origin and allow credentials when
     *   that exact Origin is configured.
     *
     * Pure/deterministic — no I/O — so it is unit-testable in isolation.
     *
     * @param string             $origin          The request Origin header.
     * @param array<int,string>  $allowed_origins Configured allowlist (may contain '*').
     * @return array<int,string> Ordered list of header strings to emit.
     */
    public static function cors_headers_for(string $origin, array $allowed_origins): array {
        if (in_array('*', $allowed_origins, true)) {
            return [
                'Access-Control-Allow-Origin: *',
                'Access-Control-Allow-Methods: GET, POST, OPTIONS',
                'Access-Control-Allow-Headers: Content-Type, X-Embed-Token',
            ];
        }

        if ($origin !== '' && in_array($origin, $allowed_origins, true)) {
            return [
                'Access-Control-Allow-Origin: ' . $origin,
                'Vary: Origin',
                'Access-Control-Allow-Methods: GET, POST, OPTIONS',
                'Access-Control-Allow-Headers: Content-Type, X-Embed-Token',
                'Access-Control-Allow-Credentials: true',
            ];
        }

        return [];
    }

    /**
     * Get allowed origins for CORS
     *
     * @return array
     */
    private function get_allowed_origins(): array {
        $settings = get_option('fffl_settings', []);
        $origins = $settings['embed_allowed_origins'] ?? '*';

        if ($origins === '*') {
            return ['*'];
        }

        return array_filter(array_map('trim', explode("\n", $origins)));
    }

    /**
     * Handle iframe embed request
     */
    public function handle_iframe_request(): void {
        if (!isset($_GET['fffl_embed']) || !isset($_GET['token'])) {
            return;
        }

        $token = sanitize_text_field($_GET['token']);
        $instance = $this->get_instance_by_embed_token($token);

        if (!$instance) {
            wp_die(__('Invalid embed token', 'formflow-lite'), '', ['response' => 403]);
        }

        // Never let a page cache store the iframe page (per-visitor nonce).
        SessionGuard::mark_page_uncacheable();

        // Output minimal iframe page
        $this->render_iframe_page($instance);
        exit;
    }

    /**
     * Render iframe embed page
     *
     * @param array $instance
     */
    private function render_iframe_page(array $instance): void {
        $branding = Branding::instance();

        ?>
        <!DOCTYPE html>
        <html <?php language_attributes(); ?>>
        <head>
            <meta charset="<?php bloginfo('charset'); ?>">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <title><?php echo esc_html($instance['name']); ?></title>
            <style>
                :root {
                    --ff-primary: <?php echo esc_attr($branding->get('primary_color')); ?>;
                    --ff-secondary: <?php echo esc_attr($branding->get('secondary_color')); ?>;
                }
                * { box-sizing: border-box; }
                body {
                    margin: 0;
                    padding: 20px;
                    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
                    background: transparent;
                }
                .ff-embed-container {
                    max-width: 100%;
                }
            </style>
            <?php
            // Enqueue form styles
            wp_enqueue_style('ff-forms');
            wp_print_styles('ff-forms');
            ?>
        </head>
        <body>
            <div class="ff-embed-container">
                <?php
                // Render the form
                $public = new Frontend\Frontend();
                echo $public->render_form_shortcode(['instance' => $instance['slug']]);
                ?>
            </div>
            <?php
            // Enqueue form scripts
            wp_enqueue_script('ff-enrollment');
            wp_print_scripts('ff-enrollment');
            ?>
            <script>
                // Notify parent frame of height changes
                (function() {
                    function sendHeight() {
                        var height = document.body.scrollHeight;
                        parent.postMessage({ type: 'ff-resize', height: height }, '*');
                    }

                    // Send initial height
                    sendHeight();

                    // Observe DOM changes
                    var observer = new MutationObserver(sendHeight);
                    observer.observe(document.body, { childList: true, subtree: true });

                    // Send on window resize
                    window.addEventListener('resize', sendHeight);
                })();
            </script>
        </body>
        </html>
        <?php
    }

    /**
     * Get embed config via REST API
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function get_embed_config(\WP_REST_Request $request): \WP_REST_Response {
        $token = $request->get_param('token');
        $instance = $this->get_instance_by_embed_token($token);

        if (!$instance) {
            return new \WP_REST_Response(['error' => 'Invalid token'], 403);
        }

        $branding = Branding::instance();
        $settings = json_decode($instance['settings'] ?? '{}', true);
        $features = $settings['features'] ?? [];

        $config = [
            'instance_id' => $instance['id'],
            'name' => $instance['name'],
            'form_type' => $instance['form_type'],
            'utility' => $instance['utility'],
            'branding' => [
                'primary_color' => $branding->get('primary_color'),
                'secondary_color' => $branding->get('secondary_color'),
                'logo_url' => $branding->get('logo_url'),
                'form_title' => $branding->get('form_title'),
                'powered_by' => $branding->get('show_powered_by') ? $branding->get('powered_by_text') : null,
            ],
            'features' => [
                'inline_validation' => !empty($features['inline_validation']['enabled']),
                'auto_save' => !empty($features['auto_save']['enabled']),
                'spanish_translation' => !empty($features['spanish_translation']['enabled']),
            ],
        ];

        /**
         * Filter embed configuration
         *
         * @param array $config Embed configuration
         * @param array $instance Instance data
         */
        $config = apply_filters('fffl_embed_config', $config, $instance);

        return new \WP_REST_Response($config);
    }

    /**
     * Get instance by embed token
     *
     * @param string $token
     * @return array|null
     */
    private function get_instance_by_embed_token(string $token): ?array {
        global $wpdb;

        $table = $wpdb->prefix . FFFL_TABLE_INSTANCES;

        $instance = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE embed_token = %s AND is_active = 1",
                $token
            ),
            ARRAY_A
        );

        return $instance ?: null;
    }

    /**
     * Generate embed token for instance
     *
     * @param int $instance_id
     * @return string
     */
    public function generate_embed_token(int $instance_id): string {
        $token = bin2hex(random_bytes(24));

        global $wpdb;
        $table = $wpdb->prefix . FFFL_TABLE_INSTANCES;

        $wpdb->update(
            $table,
            ['embed_token' => $token],
            ['id' => $instance_id],
            ['%s'],
            ['%d']
        );

        return $token;
    }

    /**
     * Get embed code for instance
     *
     * @param int $instance_id
     * @param string $type 'iframe' or 'script'
     * @return array
     */
    public function get_embed_code(int $instance_id, string $type = 'script'): array {
        $instance = $this->db->get_instance($instance_id);

        if (!$instance) {
            return ['error' => 'Instance not found'];
        }

        // Generate token if not exists
        $token = $instance['embed_token'] ?? '';
        if (empty($token)) {
            $token = $this->generate_embed_token($instance_id);
        }

        $embed_url = add_query_arg([
            'fffl_embed' => '1',
            'token' => $token,
        ], home_url('/'));

        $script_url = FFFL_PLUGIN_URL . 'public/assets/js/embed.js';

        if ($type === 'iframe') {
            $code = sprintf(
                '<iframe src="%s" width="100%%" height="800" frameborder="0" style="border: none; max-width: 100%%;"></iframe>',
                esc_url($embed_url)
            );
        } else {
            $code = sprintf(
                '<div id="ff-form-%s" data-ff-token="%s"></div>' . "\n" .
                '<script src="%s" async></script>',
                esc_attr($instance_id),
                esc_attr($token),
                esc_url($script_url)
            );
        }

        return [
            'code' => $code,
            'token' => $token,
            'iframe_url' => $embed_url,
            'script_url' => $script_url,
        ];
    }

    /**
     * Register embed assets
     */
    public function register_embed_assets(): void {
        wp_register_script(
            'ff-embed',
            FFFL_PLUGIN_URL . 'public/assets/js/embed.js',
            [],
            FFFL_VERSION,
            true
        );
    }
}
