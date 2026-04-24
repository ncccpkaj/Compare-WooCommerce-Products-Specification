<?php
defined( 'ABSPATH' ) || exit;

/**
 * WPC_AI_Settings — v2
 * Multi-provider AI config under Settings → General.
 * PHP 7.4+ compatible (no match(), no arrow functions).
 */
class WPC_AI_Settings {

    const OPTION_KEY = 'wpc_ai_config';

    // ── Model catalogue ───────────────────────────────────────────────────────

    public static function model_catalogue(): array {
        return [
            'openrouter' => [
                'label'  => 'OpenRouter',
                'models' => [
                    // ── Top-ranked paid models ────────────────────────────────
                    'anthropic/claude-sonnet-4-6'         => 'Claude Sonnet 4.6 — #6 ranked, best quality',
                    'anthropic/claude-opus-4-6'           => 'Claude Opus 4.6 — #7 ranked, most powerful',
                    'anthropic/claude-haiku-4-5'          => 'Claude Haiku 4.5 — #20 ranked, fastest Claude',
                    'anthropic/claude-sonnet-4.5'         => 'Claude Sonnet 4.5 — #15 ranked',
                    'google/gemini-flash-1-5'             => 'Gemini 2.5 Flash — #10 ranked, fast Google',
                    'google/gemini-flash-1-5-8b'          => 'Gemini 2.5 Flash Lite — #12 ranked, lightweight',
                    'google/gemini-2.0-flash-001'         => 'Gemini 2.0 Flash — reliable Google',
                    'deepseek/deepseek-chat'              => 'DeepSeek V3.2 — #4 ranked, cost-effective',
                    'moonshotai/kimi-k2'                  => 'Kimi K2.5 — #8 ranked',
                    'minimax/minimax-m2'                  => 'MiniMax M2.5 — #2 ranked',
                    // ── Free tier ────────────────────────────────────────────
                    'stepfun/step-3.5-flash:free'                  => 'Step 3.5 Flash — #3 free, 200 RPD',
                    'arcee-ai/trinity-large-preview:free'          => 'Trinity Large Preview — free',
                    'nvidia/nemotron-3-super-120b-a12b:free'       => 'Nemotron 3 Super 120B — #18 free',
                    'google/gemma-3-27b-it:free'                   => 'Gemma 3 27B — Google free',
                    'google/gemma-3-12b-it:free'                   => 'Gemma 3 12B — Google free (lighter)',
                    'openrouter/free'                              => 'Auto-select best free model',
                ],
                'key_hint'  => 'sk-or-...',
                'key_label' => 'OpenRouter API Key',
                'key_url'   => 'https://openrouter.ai/keys',
            ],
            'groq' => [
                'label'  => 'Groq',
                'models' => [
                    'llama-3.3-70b-versatile'                   => 'Llama 3.3 70B — best quality (1K RPD)',
                    'meta-llama/llama-4-scout-17b-16e-instruct' => 'Llama 4 Scout 17B — 1K RPD, 500K TPM',
                    'llama-3.1-8b-instant'                      => 'Llama 3.1 8B Instant — fastest (14.4K RPD)',
                    'qwen/qwen3-32b'                            => 'Qwen3 32B — 1K RPD, 500K TPM',
                    'moonshotai/kimi-k2-instruct'               => 'Kimi K2 — 1K RPD, 300K TPM',
                ],
                'key_hint'  => 'gsk_...',
                'key_label' => 'Groq API Key',
                'key_url'   => 'https://console.groq.com/keys',
            ],
            'gemini' => [
                'label'  => 'Gemini (Google)',
                'models' => [
                    'gemini-2.0-flash'      => 'Gemini 2.0 Flash — 15 RPM / 1500 RPD (free)',
                    'gemini-2.0-flash-lite' => 'Gemini 2.0 Flash Lite — 30 RPM / 1500 RPD (free)',
                    'gemini-1.5-flash'      => 'Gemini 1.5 Flash — 15 RPM / 1500 RPD (free)',
                    'gemini-1.5-flash-8b'   => 'Gemini 1.5 Flash 8B — 15 RPM / 1500 RPD (free)',
                ],
                'key_hint'  => 'AIza...',
                'key_label' => 'Google AI Studio API Key',
                'key_url'   => 'https://aistudio.google.com/app/apikey',
            ],
            'openai' => [
                'label'  => 'ChatGPT (OpenAI)',
                'models' => [
                    'gpt-4o-mini'   => 'GPT-4o Mini — fastest & cheapest',
                    'gpt-4o'        => 'GPT-4o — best quality',
                    'gpt-3.5-turbo' => 'GPT-3.5 Turbo — legacy / budget',
                ],
                'key_hint'  => 'sk-...',
                'key_label' => 'OpenAI API Key',
                'key_url'   => 'https://platform.openai.com/api-keys',
            ],
            'claude' => [
                'label'  => 'Claude (Anthropic)',
                'models' => [
                    'claude-haiku-4-5'           => 'Claude Haiku 4.5 — fastest',
                    'claude-sonnet-4-6'          => 'Claude Sonnet 4.6 — best quality',
                    'claude-opus-4-6'            => 'Claude Opus 4.6 — most powerful',
                    'claude-3-5-haiku-20241022'  => 'Claude 3.5 Haiku — budget fast',
                    'claude-3-5-sonnet-20241022' => 'Claude 3.5 Sonnet — reliable',
                ],
                'key_hint'  => 'sk-ant-...',
                'key_label' => 'Anthropic API Key',
                'key_url'   => 'https://console.anthropic.com/settings/keys',
            ],
        ];
    }

    // ── Default prompts ───────────────────────────────────────────────────────

    public static function default_prompt_desc(): string {
        return 'You are an expert eCommerce copywriter specializing in gadget and electronics products.
Generate a high-converting product description using the following structure.
Product Title: {title}
Category: {category}

Instructions:
- Write in clear, simple, and professional English
- Keep content concise and mobile-friendly
- Use bullet points where needed
- Focus on benefits, not just features
- Do NOT include specifications (they are handled separately)
- Avoid fluff or overly generic phrases

Structure:

1. Intro
- 2–3 lines
- Clearly explain what the product is and its main benefit

2. Key Features
- 4–6 bullet points
- Short, powerful, benefit-focused

3. Overview
- 3–5 lines
- Explain who it\'s for and why it\'s useful

4. Feature Details
- Use short sections with headings
- Explain key features in more detail (performance, design, usability, etc.)

5. Use Cases
- 3–5 bullet points
- Real-life usage scenarios

6. Compatibility (if applicable)
- List supported devices/platforms

7. Package Includes
- Bullet list of box contents

8. Warranty
- 1–2 lines (generic if not provided)

9. FAQs
- 3–4 common customer questions with short answers

Formatting:
- Use clear headings
- Use bullet points where appropriate
- Avoid long paragraphs
- Keep tone natural and persuasive

Output only the final formatted description. Use HTML formatting. Be informative and SEO-friendly.';
    }

    public static function default_prompt_short(): string {
        return 'You are an expert eCommerce copywriter specializing in gadgets and electronics. Generate a short, catchy, and persuasive product description for the product below. Keep it concise (1–2 sentences, max 40 words), focus on key benefits, and make it SEO-friendly. Use simple and clear English.
Product Name: {title}
Category: {category}
Requirements:
- Output only the short description. Plain text only if need then use bulet point.
- Highlight the main benefit and who it is for
- Natural, persuasive tone
- Output ONLY the summary text. No labels, no preamble, no quotation marks.';
    }

    public static function default_prompt_spec(): string {
        return 'You are a structured product data assistant for eCommerce. Your task is to generate realistic and relevant specification values for a product based on its name and category.
Product Name: {title}
Category: {category}

Instructions:
- Only include specifications that are relevant to this product type
- Skip any irrelevant or unknown fields completely
- Do NOT guess highly specific technical details (e.g., exact chipset model) unless very commonly known for this product
- Keep each value short, clear, and in a single line
- Use simple, standard formatting (e.g., "6.7-inch AMOLED", "5000mAh", "Bluetooth 5.3")
- Ensure values are realistic and consistent with the product category

Specification Keys (use only if applicable):
{spec_keys}

Output Rules:
- Return ONLY a valid JSON object
- Do NOT include any explanation, text, or markdown
- Do NOT include empty, null, or placeholder values
- Each key must map to a short string value

Example Output:
{"Brand":"Samsung","Display Size":"6.1-inch","Battery Info":"4500mAh","Connectivity":"Wi-Fi, Bluetooth 5.3"}';
    }

    // ── Settings API ──────────────────────────────────────────────────────────

    public static function init() {
        add_action( 'admin_init', array( __CLASS__, 'register_fields' ) );
        add_action( 'admin_enqueue_scripts', array( __CLASS__, 'settings_page_assets' ) );
    }

    public static function settings_page_assets( $hook ) {
        if ( $hook !== 'options-general.php' ) return;
    }

    public static function register_fields() {
        register_setting( 'general', self::OPTION_KEY, array( 'sanitize_callback' => array( __CLASS__, 'sanitize' ) ) );

        add_settings_section( 'wpc_ai_section',
            __( 'Product AI Generator', 'woo-product-compare' ),
            array( __CLASS__, 'section_desc' ), 'general' );

        add_settings_field( 'wpc_ai_providers_field', __( 'AI Providers', 'woo-product-compare' ),
            array( __CLASS__, 'field_providers' ), 'general', 'wpc_ai_section' );

        add_settings_field( 'wpc_ai_prompt_desc_field', __( 'Description Prompt', 'woo-product-compare' ),
            array( __CLASS__, 'field_prompt_desc' ), 'general', 'wpc_ai_section' );

        add_settings_field( 'wpc_ai_prompt_short_field', __( 'Short Description Prompt', 'woo-product-compare' ),
            array( __CLASS__, 'field_prompt_short' ), 'general', 'wpc_ai_section' );

        add_settings_field( 'wpc_ai_prompt_spec_field', __( 'Specification Prompt', 'woo-product-compare' ),
            array( __CLASS__, 'field_prompt_spec' ), 'general', 'wpc_ai_section' );
    }

    public static function section_desc() {
        echo '<p>' . esc_html__( 'Configure AI providers and prompts for auto-generating product content. Variables: {title}, {category}, {spec_keys} (spec only). | Shortcodes for Compare Feature:[wpc_specs] Show Spec for single product page,[woo_compare] Show Compare tabe.', 'woo-product-compare' ) . '</p>';
    }

    // ── Provider cards ────────────────────────────────────────────────────────

    public static function field_providers() {
        $cfg     = self::get();
        $catalog = self::model_catalogue();

        $icons = array(
            'openrouter' => '⟁',
            'groq'       => '⚡',
            'gemini'     => '✦',
            'openai'     => '⊕',
            'claude'     => '◈',
        );

        echo '<div class="wpc-ai-providers-wrap">';

        foreach ( $catalog as $provider_id => $provider ) {
            $saved         = isset( $cfg['providers'][ $provider_id ] ) ? $cfg['providers'][ $provider_id ] : array();
            $is_active     = ! empty( $saved['active'] );
            $api_key       = isset( $saved['api_key'] ) ? $saved['api_key'] : '';
            $model_keys    = array_keys( $provider['models'] );
            $default_model = isset( $saved['default_model'] ) ? $saved['default_model'] : $model_keys[0];
            $name          = self::OPTION_KEY . '[providers][' . $provider_id . ']';
            $icon          = isset( $icons[ $provider_id ] ) ? $icons[ $provider_id ] : '◉';

            echo '<div class="wpc-ai-provider-card' . ( $is_active ? ' wpc-provider-active' : '' ) . '">';

            // ── Header ──
            echo '<div class="wpc-provider-header">';
            echo '<label class="wpc-provider-toggle">';
            echo '<input type="checkbox" name="' . esc_attr( $name ) . '[active]" value="1"' . checked( $is_active, true, false ) . '>';
            echo '<span class="wpc-toggle-slider"></span>';
            echo '</label>';
            echo '<span class="wpc-provider-icon">' . esc_html( $icon ) . '</span>';
            echo '<strong class="wpc-provider-name">' . esc_html( $provider['label'] ) . '</strong>';
            if ( $is_active && $api_key ) {
                echo '<span class="wpc-provider-badge wpc-badge-ok">✓ Active</span>';
            } elseif ( $is_active && ! $api_key ) {
                echo '<span class="wpc-provider-badge wpc-badge-warn">⚠ Key missing</span>';
            }
            echo '</div>'; // .wpc-provider-header

            // ── Body ──
            echo '<div class="wpc-provider-body">';

            // API key row
            echo '<div class="wpc-provider-row">';
            echo '<label>' . esc_html( $provider['key_label'] ) . '</label>';
            echo '<div class="wpc-key-row-inner">';
            echo '<input type="password"'
               . ' name="' . esc_attr( $name ) . '[api_key]"'
               . ' value="' . esc_attr( $api_key ) . '"'
               . ' class="wpc-api-key-input"'
               . ' autocomplete="new-password"'
               . ' placeholder="' . esc_attr( $provider['key_hint'] ) . '">';
            echo '<a href="' . esc_url( $provider['key_url'] ) . '" target="_blank" class="wpc-get-key-link">Get key ↗</a>';
            echo '</div>';
            echo '</div>';

            // Default model row
            echo '<div class="wpc-provider-row">';
            echo '<label>' . esc_html__( 'Default Model', 'woo-product-compare' ) . '</label>';
            echo '<select name="' . esc_attr( $name ) . '[default_model]">';
            foreach ( $provider['models'] as $model_id => $model_label ) {
                echo '<option value="' . esc_attr( $model_id ) . '"'
                   . selected( $default_model, $model_id, false ) . '>'
                   . esc_html( $model_label ) . '</option>';
            }
            echo '</select>';
            echo '</div>';

            echo '</div>'; // .wpc-provider-body
            echo '</div>'; // .wpc-ai-provider-card
        }

        echo '</div>'; // .wpc-ai-providers-wrap
    }

    public static function field_prompt_desc() {
        $cfg = self::get();
        echo '<textarea name="' . esc_attr( self::OPTION_KEY ) . '[prompt_desc]" rows="10" class="large-text">'
           . esc_textarea( isset( $cfg['prompt_desc'] ) ? $cfg['prompt_desc'] : self::default_prompt_desc() ) . '</textarea>';
        echo '<p class="description">' . esc_html__( 'Variables: {title}, {category}', 'woo-product-compare' ) . '</p>';
    }

    public static function field_prompt_short() {
        $cfg = self::get();
        echo '<textarea name="' . esc_attr( self::OPTION_KEY ) . '[prompt_short]" rows="5" class="large-text">'
           . esc_textarea( isset( $cfg['prompt_short'] ) ? $cfg['prompt_short'] : self::default_prompt_short() ) . '</textarea>';
        echo '<p class="description">' . esc_html__( 'Variables: {title}, {category}', 'woo-product-compare' ) . '</p>';
    }

    public static function field_prompt_spec() {
        $cfg = self::get();
        echo '<textarea name="' . esc_attr( self::OPTION_KEY ) . '[prompt_spec]" rows="8" class="large-text">'
           . esc_textarea( isset( $cfg['prompt_spec'] ) ? $cfg['prompt_spec'] : self::default_prompt_spec() ) . '</textarea>';
        echo '<p class="description">' . esc_html__( 'Variables: {title}, {category}, {spec_keys}', 'woo-product-compare' ) . '</p>';
    }

    // ── Sanitize ──────────────────────────────────────────────────────────────

    public static function sanitize( $input ): array {
        $catalog   = self::model_catalogue();
        $providers = array();

        foreach ( $catalog as $provider_id => $provider ) {
            $raw          = isset( $input['providers'][ $provider_id ] ) ? $input['providers'][ $provider_id ] : array();
            $valid_models = array_keys( $provider['models'] );
            $model        = sanitize_text_field( isset( $raw['default_model'] ) ? $raw['default_model'] : '' );
            $providers[ $provider_id ] = array(
                'active'        => ! empty( $raw['active'] ) ? 1 : 0,
                'api_key'       => sanitize_text_field( isset( $raw['api_key'] ) ? $raw['api_key'] : '' ),
                'default_model' => in_array( $model, $valid_models, true ) ? $model : $valid_models[0],
            );
        }

        return array(
            'providers'    => $providers,
            'prompt_desc'  => sanitize_textarea_field( isset( $input['prompt_desc'] )  ? $input['prompt_desc']  : '' ),
            'prompt_short' => sanitize_textarea_field( isset( $input['prompt_short'] ) ? $input['prompt_short'] : '' ),
            'prompt_spec'  => sanitize_textarea_field( isset( $input['prompt_spec'] )  ? $input['prompt_spec']  : '' ),
        );
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    public static function get(): array {
        $saved = get_option( self::OPTION_KEY, array() );
        if ( ! is_array( $saved ) ) $saved = array();

        // Migrate old single-provider format
        if ( isset( $saved['provider'] ) && ! isset( $saved['providers'] ) ) {
            $saved['providers'] = array(
                $saved['provider'] => array(
                    'active'        => isset( $saved['active'] ) ? $saved['active'] : 0,
                    'api_key'       => isset( $saved['api_key'] ) ? $saved['api_key'] : '',
                    'default_model' => '',
                ),
            );
        }

        if ( ! isset( $saved['prompt_desc'] ) )  $saved['prompt_desc']  = self::default_prompt_desc();
        if ( ! isset( $saved['prompt_short'] ) ) $saved['prompt_short'] = self::default_prompt_short();
        if ( ! isset( $saved['prompt_spec'] ) )  $saved['prompt_spec']  = self::default_prompt_spec();

        return $saved;
    }

    /** Returns array of active+configured providers for JS. */
    public static function get_active_providers(): array {
        $cfg     = self::get();
        $catalog = self::model_catalogue();
        $result  = array();

        foreach ( $catalog as $provider_id => $provider ) {
            $saved = isset( $cfg['providers'][ $provider_id ] ) ? $cfg['providers'][ $provider_id ] : array();
            if ( empty( $saved['active'] ) ) continue;

            $key   = isset( $saved['api_key'] ) ? $saved['api_key'] : '';
            $model_keys = array_keys( $provider['models'] );
            $model = isset( $saved['default_model'] ) ? $saved['default_model'] : $model_keys[0];

            $result[ $provider_id ] = array(
                'label'         => $provider['label'],
                'has_key'       => ! empty( $key ),
                'default_model' => $model,
                'models'        => $provider['models'],
            );
        }

        return $result;
    }

    public static function is_active(): bool {
        return ! empty( self::get_active_providers() );
    }

    public static function get_prompt( string $type ): string {
        $cfg = self::get();
        switch ( $type ) {
            case 'desc':
                return isset( $cfg['prompt_desc'] )  ? $cfg['prompt_desc']  : self::default_prompt_desc();
            case 'short':
                return isset( $cfg['prompt_short'] ) ? $cfg['prompt_short'] : self::default_prompt_short();
            case 'spec':
                return isset( $cfg['prompt_spec'] )  ? $cfg['prompt_spec']  : self::default_prompt_spec();
            default:
                return '';
        }
    }
}
