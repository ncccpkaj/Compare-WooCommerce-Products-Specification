<?php
defined( 'ABSPATH' ) || exit;

/**
 * WPC_AI_Ajax — v2
 * Routes AI generation requests to the correct provider + model.
 * Supports: Gemini, OpenAI, Claude, OpenRouter, Groq.
 * PHP 7.4+ compatible (no match(), no arrow functions).
 */
class WPC_AI_Ajax {

    public static function init() {
        add_action( 'wp_ajax_wpc_ai_generate', array( __CLASS__, 'generate' ) );
    }

    public static function generate() {
        check_ajax_referer( 'wpc_ai_nonce', 'nonce' );
        if ( ! current_user_can( 'edit_products' ) ) {
            wp_send_json_error( array( 'message' => 'Unauthorized.' ) );
        }
        if ( ! WPC_AI_Settings::is_active() ) {
            wp_send_json_error( array( 'message' => 'No AI provider is active. Configure one in Settings → General.' ) );
        }

        $type     = sanitize_key( isset( $_POST['type'] )     ? $_POST['type']     : '' );
        $provider = sanitize_key( isset( $_POST['provider'] ) ? $_POST['provider'] : '' );
        $model    = sanitize_text_field( isset( $_POST['model'] )   ? $_POST['model']   : '' );
        $prompt   = sanitize_textarea_field( isset( $_POST['prompt'] ) ? $_POST['prompt'] : '' );

        if ( ! $prompt ) {
            wp_send_json_error( array( 'message' => 'Prompt is empty.' ) );
        }

        $active = WPC_AI_Settings::get_active_providers();
        if ( ! isset( $active[ $provider ] ) ) {
            wp_send_json_error( array( 'message' => 'Selected AI provider is not active or configured.' ) );
        }
        if ( ! $active[ $provider ]['has_key'] ) {
            wp_send_json_error( array( 'message' => 'API key for ' . $active[ $provider ]['label'] . ' is not set. Add it in Settings → General.' ) );
        }

        $cfg     = WPC_AI_Settings::get();
        $api_key = isset( $cfg['providers'][ $provider ]['api_key'] ) ? $cfg['providers'][ $provider ]['api_key'] : '';

        $catalog      = WPC_AI_Settings::model_catalogue();
        $valid_models = array_keys( isset( $catalog[ $provider ]['models'] ) ? $catalog[ $provider ]['models'] : array() );
        if ( ! in_array( $model, $valid_models, true ) ) {
            $model = $active[ $provider ]['default_model'];
        }

        if ( $type === 'spec' ) {
            $prompt .= "\n\nCRITICAL: Your entire response must be ONLY a valid JSON object. No markdown, no code fences (```), no explanation text before or after. Start with { and end with }.";
        }

        $result = self::call_provider( $provider, $api_key, $model, $prompt, $type );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array( 'message' => $result->get_error_message() ) );
        }

        if ( $type === 'spec' ) {
            $json = self::extract_json( $result );
            if ( $json === null ) {
                wp_send_json_error( array(
                    'message' => "AI returned invalid JSON. Try again or adjust your prompt.\n\nRaw response (first 400 chars):\n" . mb_substr( $result, 0, 400 ),
                ) );
            }
            wp_send_json_success( array( 'type' => $type, 'content' => $json ) );
        }

        $content = self::clean_text( $result, $type );
        wp_send_json_success( array( 'type' => $type, 'content' => $content ) );
    }

    // ── Route to provider ─────────────────────────────────────────────────────

    private static function call_provider( $provider, $api_key, $model, $prompt, $type ) {
        $max_tokens = $type === 'desc' ? 1500 : ( $type === 'spec' ? 2000 : 400 );
        switch ( $provider ) {
            case 'gemini':
                return self::call_gemini( $api_key, $model, $prompt, $max_tokens );
            case 'openai':
                return self::call_openai( $api_key, $model, $prompt, $max_tokens );
            case 'claude':
                return self::call_claude( $api_key, $model, $prompt, $max_tokens );
            case 'openrouter':
                return self::call_openrouter( $api_key, $model, $prompt, $max_tokens );
            case 'groq':
                return self::call_groq( $api_key, $model, $prompt, $max_tokens );
            default:
                return new WP_Error( 'invalid_provider', 'Unknown provider: ' . $provider );
        }
    }

    // ── Gemini ────────────────────────────────────────────────────────────────

    private static function call_gemini( $api_key, $model, $prompt, $max_tokens ) {
        $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . urlencode( $model ) . ':generateContent?key=' . urlencode( $api_key );

        $response = wp_remote_post( $url, array(
            'timeout' => 90,
            'headers' => array( 'Content-Type' => 'application/json' ),
            'body'    => wp_json_encode( array(
                'contents'         => array( array( 'parts' => array( array( 'text' => $prompt ) ) ) ),
                'generationConfig' => array( 'maxOutputTokens' => $max_tokens ),
            ) ),
        ) );

        return self::parse( $response, 'gemini',
            array(
                'RESOURCE_EXHAUSTED' => 'Gemini rate limit reached. Try a key from a different Google account or wait for quota reset.',
                'PERMISSION_DENIED'  => 'Gemini API key is invalid or missing permissions.',
                'UNAVAILABLE'        => 'Gemini is temporarily unavailable. Try again shortly.',
                'NOT_FOUND'          => 'Gemini model not found. Select a different model in Settings → General.',
            )
        );
    }

    // ── OpenAI ────────────────────────────────────────────────────────────────

    private static function call_openai( $api_key, $model, $prompt, $max_tokens ) {
        $response = wp_remote_post( 'https://api.openai.com/v1/chat/completions', array(
            'timeout' => 90,
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type'  => 'application/json',
            ),
            'body' => wp_json_encode( array(
                'model'      => $model,
                'messages'   => array( array( 'role' => 'user', 'content' => $prompt ) ),
                'max_tokens' => $max_tokens,
            ) ),
        ) );

        return self::parse( $response, 'openai',
            array(
                'rate_limit_exceeded'     => 'OpenAI rate limit reached. Please wait and try again.',
                'insufficient_quota'      => 'OpenAI quota exhausted. Check your billing at platform.openai.com.',
                'invalid_api_key'         => 'Invalid OpenAI API key. Check Settings → General.',
                'context_length_exceeded' => 'Prompt is too long for this model. Shorten your prompt.',
                'model_not_found'         => 'OpenAI model not found. Try a different model.',
            )
        );
    }

    // ── Claude ────────────────────────────────────────────────────────────────

    private static function call_claude( $api_key, $model, $prompt, $max_tokens ) {
        $response = wp_remote_post( 'https://api.anthropic.com/v1/messages', array(
            'timeout' => 90,
            'headers' => array(
                'x-api-key'         => $api_key,
                'anthropic-version' => '2023-06-01',
                'Content-Type'      => 'application/json',
            ),
            'body' => wp_json_encode( array(
                'model'      => $model,
                'max_tokens' => $max_tokens,
                'messages'   => array( array( 'role' => 'user', 'content' => $prompt ) ),
            ) ),
        ) );

        return self::parse( $response, 'claude',
            array(
                'rate_limit_error'      => 'Claude rate limit reached. Please wait and try again.',
                'authentication_error'  => 'Invalid Claude API key. Check Settings → General.',
                'overloaded_error'      => 'Claude is overloaded. Try again shortly.',
                'invalid_request_error' => 'Claude request error. Check your model or prompt.',
            )
        );
    }

    // ── OpenRouter ────────────────────────────────────────────────────────────

    private static function call_openrouter( $api_key, $model, $prompt, $max_tokens ) {
        $response = wp_remote_post( 'https://openrouter.ai/api/v1/chat/completions', array(
            'timeout' => 120,
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type'  => 'application/json',
                'HTTP-Referer'  => home_url(),
                'X-Title'       => get_bloginfo( 'name' ),
            ),
            'body' => wp_json_encode( array(
                'model'      => $model,
                'messages'   => array( array( 'role' => 'user', 'content' => $prompt ) ),
                'max_tokens' => $max_tokens,
            ) ),
        ) );

        if ( is_wp_error( $response ) ) {
            $msg = $response->get_error_message();
            if ( stripos( $msg, 'timed out' ) !== false || stripos( $msg, 'Operation timed out' ) !== false ) {
                return new WP_Error( 'timeout', 'OpenRouter request timed out. Free-tier models can be slow — please try again or switch to a faster model.' );
            }
            return new WP_Error( 'network', 'Network error: ' . $msg );
        }

        $code = (int) wp_remote_retrieve_response_code( $response );
        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( $code !== 200 ) {
            $status  = isset( $body['error']['status'] )  ? $body['error']['status']  : '';
            $type    = isset( $body['error']['type'] )    ? $body['error']['type']    : '';
            $code_s  = isset( $body['error']['code'] )    ? $body['error']['code']    : '';
            $message = isset( $body['error']['message'] ) ? $body['error']['message'] : ( 'API error HTTP ' . $code );

            $friendly_map = array(
                'rate_limit'         => 'OpenRouter rate limit reached (free tier: 20 req/min, 200 req/day). Please wait and try again.',
                'invalid_api_key'    => 'Invalid OpenRouter API key. Check Settings → General.',
                'insufficient_quota' => 'OpenRouter credits exhausted. Check your balance at openrouter.ai.',
                'No endpoints found' => 'This OpenRouter model has no active providers right now. Please select a different model.',
                'Provider returned'  => 'The upstream model provider returned an error. Temporary issue — please try again or switch model.',
            );

            foreach ( $friendly_map as $key => $friendly ) {
                if ( strcasecmp( $status, $key ) === 0
                  || strcasecmp( $type,   $key ) === 0
                  || strcasecmp( $code_s, $key ) === 0
                  || stripos( $message,   $key ) !== false ) {
                    return new WP_Error( 'ai_error', $friendly );
                }
            }

            return new WP_Error( 'ai_error', sanitize_text_field( $message ) );
        }

        if ( isset( $body['error'] ) ) {
            $inner = isset( $body['error']['message'] ) ? $body['error']['message'] : 'OpenRouter returned an error in the response body.';
            return new WP_Error( 'ai_error', sanitize_text_field( $inner ) );
        }

        $content = isset( $body['choices'][0]['message']['content'] ) ? $body['choices'][0]['message']['content'] : null;
        if ( $content === null || trim( $content ) === '' ) {
            return new WP_Error( 'empty', 'AI returned an empty response. Try again or pick a different model.' );
        }

        return trim( $content );
    }

    // ── Groq ──────────────────────────────────────────────────────────────────

    private static function call_groq( $api_key, $model, $prompt, $max_tokens ) {
        $response = wp_remote_post( 'https://api.groq.com/openai/v1/chat/completions', array(
            'timeout' => 60,
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type'  => 'application/json',
            ),
            'body' => wp_json_encode( array(
                'model'      => $model,
                'messages'   => array( array( 'role' => 'user', 'content' => $prompt ) ),
                'max_tokens' => $max_tokens,
            ) ),
        ) );

        return self::parse( $response, 'groq',
            array(
                'rate_limit_exceeded' => 'Groq rate limit reached. Please wait and try again.',
                'invalid_api_key'     => 'Invalid Groq API key. Check Settings → General.',
                'model_not_found'     => 'Groq model not found. Try a different model.',
                'decommissioned'      => 'This Groq model has been decommissioned. Please select a different model in Settings → General.',
                'no longer supported' => 'This Groq model is no longer supported. Please select a different model.',
            )
        );
    }

    // ── Shared response parser ────────────────────────────────────────────────

    private static function parse( $response, $provider_type, $errors ) {
        if ( is_wp_error( $response ) ) {
            return new WP_Error( 'network', 'Network error: ' . $response->get_error_message() );
        }

        $code = (int) wp_remote_retrieve_response_code( $response );
        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( $code !== 200 ) {
            $status  = isset( $body['error']['status'] )  ? $body['error']['status']  : '';
            $type    = isset( $body['error']['type'] )    ? $body['error']['type']    : '';
            $code_s  = isset( $body['error']['code'] )    ? $body['error']['code']    : '';
            $message = isset( $body['error']['message'] ) ? $body['error']['message'] : ( 'API error HTTP ' . $code );

            foreach ( $errors as $key => $friendly ) {
                if ( strcasecmp( $status, $key ) === 0
                  || strcasecmp( $type,   $key ) === 0
                  || strcasecmp( $code_s, $key ) === 0
                  || stripos( $message,   $key ) !== false ) {
                    return new WP_Error( 'ai_error', $friendly );
                }
            }

            return new WP_Error( 'ai_error', sanitize_text_field( $message ) );
        }

        // Extract content based on provider response format
        $content = null;
        if ( $provider_type === 'gemini' ) {
            $content = isset( $body['candidates'][0]['content']['parts'][0]['text'] )
                ? $body['candidates'][0]['content']['parts'][0]['text']
                : null;
        } elseif ( $provider_type === 'claude' ) {
            $content = isset( $body['content'][0]['text'] )
                ? $body['content'][0]['text']
                : null;
        } else {
            // openai, openrouter, groq — all use choices[0].message.content
            $content = isset( $body['choices'][0]['message']['content'] )
                ? $body['choices'][0]['message']['content']
                : null;
        }

        if ( $content === null || trim( $content ) === '' ) {
            return new WP_Error( 'empty', 'AI returned an empty response. Try again.' );
        }

        return trim( $content );
    }

    // ── Post-processing helpers ───────────────────────────────────────────────

    private static function extract_json( $text ) {
        // 1. Strip markdown code fences
        $text = preg_replace( '/^```(?:json)?\s*/im', '', $text );
        $text = preg_replace( '/```\s*$/m', '', $text );
        $text = trim( $text );

        // 2. Direct parse
        $decoded = json_decode( $text, true );
        if ( is_array( $decoded ) ) return $decoded;

        // 3. Find all {...} blocks, try longest first
        if ( preg_match_all( '/\{[\s\S]*?\}/u', $text, $matches ) ) {
            $candidates = $matches[0];
            usort( $candidates, array( __CLASS__, 'sort_by_length_desc' ) );
            foreach ( $candidates as $candidate ) {
                $decoded = json_decode( $candidate, true );
                if ( is_array( $decoded ) ) return $decoded;
            }
        }

        // 4. Greedy match first { to last }
        $start = strpos( $text, '{' );
        $end   = strrpos( $text, '}' );
        if ( $start !== false && $end !== false && $end > $start ) {
            $decoded = json_decode( substr( $text, $start, $end - $start + 1 ), true );
            if ( is_array( $decoded ) ) return $decoded;
        }

        // 5. Repair truncated JSON
        return self::repair_json( $text );
    }

    // Comparison callback for usort — PHP 7.4 compatible (no arrow function)
    private static function sort_by_length_desc( $a, $b ) {
        return strlen( $b ) - strlen( $a );
    }

    private static function repair_json( $text ) {
        $start = strpos( $text, '{' );
        if ( $start === false ) return null;
        $text = substr( $text, $start );
        $text = rtrim( $text );
        $text = preg_replace( '/,\s*$/', '', $text );
        $text = preg_replace( '/,\s*"[^"]*"\s*:\s*"[^"]*$/', '', $text );
        $text = preg_replace( '/,\s*"[^"]*"\s*:\s*$/',        '', $text );

        $open = substr_count( $text, '{' ) - substr_count( $text, '}' );
        if ( $open > 0 ) {
            $text .= str_repeat( '}', $open );
        }

        $decoded = json_decode( $text, true );
        return is_array( $decoded ) ? $decoded : null;
    }

    private static function clean_text( $text, $type ) {
        $text = preg_replace( '/```(?:html|plain|text)?\s*/i', '', $text );
        $text = preg_replace( '/```/', '', $text );
        $text = preg_replace(
            '/^(Here\s*\'?s?\s*(a|the|is|your)?|Below\s+is|Sure[,!]|Certainly[,!]|Of\s+course[,!]|I\'?ve\s+generated|As\s+requested).{0,150}[:\n]/is',
            '',
            $text
        );
        return trim( $text );
    }
}
