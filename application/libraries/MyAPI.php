<?php

/**
 * MyAPI
 * 
 * Small API handler class for custom API Server interaction
 * 
 * @version   1.0.0
 * @author    José Luis Quintana <http://git.io/joseluisq>
 */
class MyAPI {

  private static $_instance = NULL;
  private static $_supported_formats = array(
    'json' => 'application/json',
    'xml' => 'text/xml'
  );
  private static $_options = array(
    'base_url' => '',
    'api_key' => '',
    'data_type' => 'json',
    'header' => array()
  );
  private static $_CURL_OPTS = array(
    CURLOPT_CONNECTTIMEOUT => 10,
    CURLOPT_RETURNTRANSFER => TRUE,
    CURLOPT_TIMEOUT => 60,
    CURLOPT_USERAGENT => 'myapi-php-1.0',
  );

  /**
   * Create API handler
   * @param array $options
   * 
   * Supported values:
   * 
   *    array(
   *      'base_url' => '',
   *      'api_key' => '',
   *      'data_type' => 'json',
   *      'header' => array()
   *    )
   * 
   * @return MyAPI MyAPI instance
   */
  static function create($options = array()) {
    $opts = array_merge(self::$_options, $options);
    $data_type = $opts['data_type'];

    $header = $opts['header'];
    $header['content-type'] = static::$_supported_formats[$data_type];
    $header['api-key'] = $opts['api_key'];

    $headers = array();

    foreach ($header as $key => $value) {
      $headers[$key] = "$key: $value";
    }

    $opts['header'] = $headers;

    self::$_options = $opts;

    if (static::$_instance === NULL) {
      static::$_instance = new static();
    }

    return static::$_instance;
  }

  /**
   * Makes an HTTP request. This method can be overridden by subclasses if
   * developers want to do fancier things or use something other than curl to
   * make the request.
   *
   * @param string $url The URL to make the request to
   * @param array $params The parameters to use for the POST body
   * @param string $method Request method: GET, POST, PUT, DELETE, etc
   * @param CurlHandler $ch Initialized curl handle
   *
   * @return string The response text
   */
  static function create_request($url, $params, $method = 'GET', $ch = NULL, $file_upload = FALSE) {
    if (!$ch) {
      $ch = curl_init();
    }

    $opts = self::$_CURL_OPTS;
    $header = self::$_options['header'];

    $base_url = self::$_options['base_url'];
    $url = $base_url . $url;

    $opts[CURLOPT_CUSTOMREQUEST] = strtoupper($method);

    if ($file_upload) {
      $opts[CURLOPT_POSTFIELDS] = $params;
    } else {
      $opts[CURLOPT_POSTFIELDS] = http_build_query($params, null, '&');
    }

    $opts[CURLOPT_URL] = $url;

    $opts[CURLOPT_HTTPHEADER] = $header;

    curl_setopt_array($ch, $opts);

    $result = curl_exec($ch);

    if ($result === FALSE && empty($opts[CURLOPT_IPRESOLVE])) {
      $matches = array();
      $regex = '/Failed to connect to ([^:].*): Network is unreachable/';

      if (preg_match($regex, curl_error($ch), $matches)) {
        if (strlen(@inet_pton($matches[1])) === 16) {
          custom_error_log('Invalid IPv6 configuration on server, ' .
            'Please disable or get native IPv6 on your server.');
          self::$_CURL_OPTS[CURLOPT_IPRESOLVE] = CURL_IPRESOLVE_V4;
          curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
          $result = curl_exec($ch);
        }
      }
    }

    if ($result === FALSE) {
      $err = new Exception(array(
        'error_code' => curl_errno($ch),
        'error' => array(
          'message' => curl_error($ch),
          'type' => 'CurlException',
        ),
      ));

      curl_close($ch);

      throw $err;
    }

    curl_close($ch);

    return $result;
  }

  /**
   * Log data
   * @param mixed $msg
   */
  function custom_error_log($msg) {
    if (php_sapi_name() != 'cli') {
      error_log($msg);
    }
  }

  /**
   * GET request
   * @param string $url
   * @param array $params
   * @return mixed Response data
   */
  public static function get($url, $params = array(), $cn = NULL) {
    return self::create_request($url, $params, 'GET', $cn);
  }

  /**
   * POST request
   * @param string $url
   * @param array $params
   * @return mixed Response data
   */
  public static function post($url, $params = array(), $cn = NULL) {
    return self::create_request($url, $params, 'POST', $cn);
  }

  /**
   * PUT request
   * @param string $url
   * @param array $params
   * @return mixed Response data
   */
  public static function put($url, $params = array(), $cn = NULL) {
    return self::create_request($url, $params, 'PUT', $cn);
  }

  /**
   * DELETE request
   * @param string $url
   * @param array $params
   * @return mixed Response data
   */
  public static function delete($url, $params = array(), $cn = NULL) {
    return self::create_request($url, $params, 'DELETE', $cn);
  }

}