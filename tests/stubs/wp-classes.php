<?php

class WP_Error {
    public $error;
    protected $errors = array();
    protected $error_data = array();

    public function __construct( $code = '', $message = '', $data = '' ) {
        $this->error   = $code;
        $this->errors  = array( $code => array( $message ) );
        $this->error_data = array( $code => $data );
    }

    public function get_error_code() {
        return $this->error;
    }

    public function get_error_message( $code = '' ) {
        if ( empty( $code ) ) {
            $code = $this->get_error_code();
        }
        return $this->errors[ $code ][0] ?? '';
    }

    public function get_error_data( $code = '' ) {
        if ( empty( $code ) ) {
            $code = $this->get_error_code();
        }
        return $this->error_data[ $code ] ?? '';
    }

    public function is_wp_error() {
        return true;
    }
}

class WP_REST_Response {
    protected $data;
    protected $headers = array();
    protected $status = 200;

    public function __construct( $data = null, $status = 200 ) {
        $this->data   = $data;
        $this->status = $status;
    }

    public function get_data() {
        return $this->data;
    }

    public function set_data( $data ) {
        $this->data = $data;
    }

    public function get_status() {
        return $this->status;
    }

    public function set_status( $status ) {
        $this->status = $status;
    }

    public function get_headers() {
        return $this->headers;
    }

    public function set_headers( $headers ) {
        $this->headers = $headers;
    }

    public function to_array() {
        return $this->data;
    }
}

class WP_REST_Request {
    protected $params = array();
    protected $headers = array();
    protected $body = '';

    public function __construct( $method = 'GET' ) {
        $this->params['POST']  = array();
        $this->params['GET']   = array();
        $this->params['URL']   = array();
        $this->params['default'] = array();
    }

    public function get_json_params() {
        return json_decode( $this->body, true );
    }

    public function get_param( $key ) {
        return $this->params['POST'][ $key ]
            ?? $this->params['GET'][ $key ]
            ?? $this->params['default'][ $key ]
            ?? null;
    }

    public function get_header( $key ) {
        return $this->headers[ strtolower( $key ) ] ?? null;
    }

    public function set_body( $body ) {
        $this->body = $body;
    }

    public function set_headers( $headers ) {
        $this->headers = array_combine(
            array_map( 'strtolower', array_keys( $headers ) ),
            array_values( $headers )
        );
    }

    public function set_param( $key, $value ) {
        $this->params['default'][ $key ] = $value;
    }

    public function get_method() {
        return 'POST';
    }

    public function to_array() {
        return array_merge(
            $this->params['POST'],
            $this->params['GET'],
            $this->params['default']
        );
    }
}
