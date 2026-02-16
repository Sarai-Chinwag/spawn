<?php

namespace {
    if ( ! function_exists( 'add_action' ) ) {
        function add_action( $hook, $callback, $priority = 10, $args = 1 ) {
            return true;
        }
    }

    if ( ! function_exists( 'add_filter' ) ) {
        function add_filter( $hook, $callback, $priority = 10, $args = 1 ) {
            return true;
        }
    }

    if ( ! function_exists( 'get_option' ) ) {
        function get_option( $option, $default = false ) {
            return $default;
        }
    }

    if ( ! function_exists( 'wp_mail' ) ) {
        function wp_mail( $to, $subject, $message, $headers = '', $attachments = array() ) {
            return true;
        }
    }

    if ( ! function_exists( 'wp_remote_request' ) ) {
        function wp_remote_request( $url, $args = array() ) {
            return new \WP_Error( 'http_request_failed', 'Mocked wp_remote_request' );
        }
    }

    if ( ! function_exists( 'wp_remote_retrieve_response_code' ) ) {
        function wp_remote_retrieve_response_code( $response ) {
            if ( is_wp_error( $response ) ) {
                return 0;
            }
            return 200;
        }
    }

    if ( ! function_exists( 'wp_remote_retrieve_body' ) ) {
        function wp_remote_retrieve_body( $response ) {
            return '{}';
        }
    }

    if ( ! function_exists( 'error_log' ) ) {
        function error_log( $message ) {
            echo $message . "\n";
        }
    }

    if ( ! function_exists( 'current_time' ) ) {
        function current_time( $type = 'mysql', $gmt = false ) {
            return date( 'Y-m-d H:i:s' );
        }
    }

    if ( ! function_exists( 'wp_date' ) ) {
        function wp_date( $format, $timestamp = null ) {
            if ( null === $timestamp ) {
                $timestamp = time();
            }
            return date( $format, $timestamp );
        }
    }

    if ( ! function_exists( 'sanitize_text_field' ) ) {
        function sanitize_text_field( $str ) {
            return trim( strip_tags( $str ) );
        }
    }

    if ( ! function_exists( 'wp_json_encode' ) ) {
        function wp_json_encode( $data, $options = 0, $depth = 512 ) {
            return json_encode( $data, $options, $depth );
        }
    }

    if ( ! function_exists( 'is_wp_error' ) ) {
        function is_wp_error( $thing ) {
            return ( $thing instanceof \WP_Error );
        }
    }

    if ( ! function_exists( 'filter_var' ) ) {
        function filter_var( $var, $filter = FILTER_DEFAULT, $options = null ) {
            if ( $options !== null ) {
                return filter_var( $var, $filter, $options );
            }
            return filter_var( $var, $filter );
        }
    }

    if ( ! function_exists( '__' ) ) {
        function __( $text, $domain = 'default' ) {
            return $text;
        }
    }

    if ( ! function_exists( 'esc_html__' ) ) {
        function esc_html__( $text, $domain = 'default' ) {
            return $text;
        }
    }

    if ( ! function_exists( 'esc_attr__' ) ) {
        function esc_attr__( $text, $domain = 'default' ) {
            return $text;
        }
    }

    if ( ! function_exists( 'register_rest_route' ) ) {
        function register_rest_route( $namespace, $route, $args = array(), $override = false ) {
            return true;
        }
    }
}
