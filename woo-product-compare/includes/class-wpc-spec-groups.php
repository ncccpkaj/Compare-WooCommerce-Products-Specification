<?php
defined( 'ABSPATH' ) || exit;

/**
 * WPC_Spec_Groups
 * Handles all read/write operations for spec groups stored in wp_options.
 *
 * Data shape (JSON in wp_options key 'wpc_spec_groups'):
 * {
 *   "phone": { "label": "Phone", "keys": ["Brand","Model",...] },
 *   "watch": { "label": "Watch", "keys": ["Brand","OS",...] }
 * }
 */
class WPC_Spec_Groups {

    const OPTION_KEY = 'wpc_spec_groups';

    // ── Read ──────────────────────────────────────────────────────────────────

    // public static function get_all(): array {
    //     $raw = get_option( self::OPTION_KEY, '{}' );
    //     $data = json_decode( $raw, true );
    //     return is_array( $data ) ? $data : [];
    // }
    
    public static function get_all( $limit_keys = false ): array {
        $raw = get_option( self::OPTION_KEY, '{}' );
        $data = json_decode( $raw, true );
        if ( ! is_array( $data ) ) return [];
    
        if ( $limit_keys ) {
            foreach ( $data as &$group ) {
                if ( isset( $group['keys'] ) ) {
                    $group['keys'] = array_slice( $group['keys'], 0, 15 );
                }
            }
        }
    
        return $data;
    }

    public static function get_group( string $slug ): ?array {
        $all = self::get_all();
        return $all[ $slug ] ?? null;
    }

    public static function get_keys( string $slug ): array {
        $group = self::get_group( $slug );
        return $group['keys'] ?? [];
    }

    // ── Write ─────────────────────────────────────────────────────────────────

    public static function save_group( string $slug, string $label, array $keys ): bool {
        $slug  = sanitize_key( $slug );
        $label = sanitize_text_field( $label );
        $keys  = array_values( array_filter( array_map( 'sanitize_text_field', $keys ) ) );

        if ( empty( $slug ) || empty( $label ) ) return false;

        $all = self::get_all();
        $all[ $slug ] = [ 'label' => $label, 'keys' => $keys ];
        return update_option( self::OPTION_KEY, wp_json_encode( $all ) );
    }

    public static function delete_group( string $slug ): bool {
        $slug = sanitize_key( $slug );
        $all  = self::get_all();
        if ( ! isset( $all[ $slug ] ) ) return false;
        unset( $all[ $slug ] );
        return update_option( self::OPTION_KEY, wp_json_encode( $all ) );
    }

    public static function add_key( string $slug, string $key ): bool {
        $slug  = sanitize_key( $slug );
        $key   = sanitize_text_field( $key );
        $group = self::get_group( $slug );
        if ( ! $group ) return false;
        if ( in_array( $key, $group['keys'], true ) ) return true; // already exists
        $group['keys'][] = $key;
        return self::save_group( $slug, $group['label'], $group['keys'] );
    }

    public static function remove_key( string $slug, string $key ): bool {
        $slug  = sanitize_key( $slug );
        $key   = sanitize_text_field( $key );
        $group = self::get_group( $slug );
        if ( ! $group ) return false;
        $group['keys'] = array_values( array_filter( $group['keys'], fn( $k ) => $k !== $key ) );
        return self::save_group( $slug, $group['label'], $group['keys'] );
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    public static function slug_exists( string $slug ): bool {
        return isset( self::get_all()[ sanitize_key( $slug ) ] );
    }

    public static function label_to_slug( string $label ): string {
        return sanitize_key( str_replace( ' ', '_', strtolower( $label ) ) );
    }
}
