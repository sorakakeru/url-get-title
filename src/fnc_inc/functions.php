<?php
/**
 * url-get-title
 * https://github.com/sorakakeru/url-get-title
 * 
 * Copyright (c) 2025 Yamatsu
 * Released under the MIT license
 * https://github.com/sorakakeru/url-get-title/blob/main/LICENSE
 */

  //CSRFトークン生成
  function generate_token() {
    return bin2hex(random_bytes(32));
  }


  //CSRFトークン検証
  function validate_token($token) {
    //送信されてきた$tokenが生成したハッシュと一致するか
    return isset($_SESSION['token']) && hash_equals($_SESSION['token'], $token);
  }


  //XSS対策
  function h($str) {
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
  }


  //パス部分：非ASCII文字のみencode
  function encodePath($path) {
    return preg_replace_callback('/[^\x00-\x7F]+/u', function($m) {
      return rawurlencode($m[0]);
    }, $path);
  }


  //クエリ部分：RFC 3986に基づいたencode
  function encodeQuery($query) {
    parse_str($query, $queryArray);
    $encoded = [];
    foreach ($queryArray as $key => $value) {
      $encoded[] = rawurlencode($key) . '=' . rawurlencode($value);
    }
    return implode('&', $encoded);
  }

  //ドメインとパス部分を抽出
  function normalizeUrl($input_url) {
    if (!preg_match('~\A(https?://[^/?#]+)(/[^?#]*)?(\?[^#]*)?~u', $input_url, $m)) {
      return false;
    }
    $base = $m[1];
    $path = isset($m[2]) ? encodePath($m[2]) : '';
    $query = isset($m[3]) ? encodeQuery(ltrim($m[3], '?')) : '';
    return $base . $path . ($query ? '?' . $query : '');
  }


  //http(s) match
  function checkUrl($match_url) {
    $parsed = parse_url($match_url);
    return $parsed && isset($parsed['scheme'], $parsed['host']) && preg_match('#\Ahttps?://#', $match_url);
  }
  

  //url get
  function curl_get_contents($curl_url) {
    $headers = [
      "User-Agent:Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.5 Safari/605.1.15"
    ];
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $curl_url);
    curl_setopt($ch, CURLOPT_HEADER, false);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
    curl_setopt($ch, CURLOPT_AUTOREFERER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_setopt($ch, CURLOPT_FAILONERROR, true);

    // httpsの場合のみSSL検証
    if (stripos($curl_url, 'https://') === 0) {
      curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
      curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
    }

    $result = curl_exec($ch);
    curl_close($ch);
    return $result;
  }
  
?>