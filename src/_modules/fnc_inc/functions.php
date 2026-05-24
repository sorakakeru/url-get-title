<?php
/**
 * url-get-title
 * https://github.com/sorakakeru/url-get-title
 * 
 * Copyright (c) 2025 Yamatsu
 * Released under the MIT license
 * https://github.com/sorakakeru/url-get-title/blob/main/LICENSE
 * 
 * This script uses these library.
 * Twig: https://twig.symfony.com/
 */

/**
 * CSRFトークン生成
 */
function generateToken() {
  return bin2hex(random_bytes(32));
}

/**
 * CSRFトークン検証
 * @param string $token トークン文字列
 */
function validateToken($token) {
  //送信されてきた$tokenが生成したハッシュと一致するか
  return isset($_SESSION['token']) && hash_equals($_SESSION['token'], $token);
}

/**
 * XSS対策
 * @param string $str 変換する文字列
 */
function h($str) {
  return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}

/**
 * クライアントIP取得
 * @return string
 */
function getClientIpAddress() {
  $candidates = [
    $_SERVER['HTTP_CF_CONNECTING_IP'] ?? '',
    $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '',
    $_SERVER['REMOTE_ADDR'] ?? '',
  ];

  foreach ($candidates as $candidate) {
    if ($candidate === '') continue;

    $ip = trim(explode(',', $candidate)[0]);
    if (filter_var($ip, FILTER_VALIDATE_IP)) return $ip;
  }

  return 'unknown';
}

/**
 * ブロック理由を監査ログに記録
 * @param string $reason 理由コード
 * @param string $targetUrl 入力URL
 * @param array $extra 追加情報
 */
function writeAccessBlockLog($reason, $targetUrl = '', $extra = []) {
  $logDir = __DIR__. '/../../_logs';
  if (!is_dir($logDir)) @mkdir($logDir, 0755, true);

  $logFile = $logDir. '/error.log';
  $entry = [
    'time' => date('c'),
    'reason' => (string)$reason,
    'target_url' => (string)$targetUrl,
    'method' => (string)($_SERVER['REQUEST_METHOD'] ?? ''),
    'user_agent' => (string)($_SERVER['HTTP_USER_AGENT'] ?? ''),
    'request_uri' => (string)($_SERVER['REQUEST_URI'] ?? ''),
    'extra' => is_array($extra) ? $extra : [],
  ];

  $line = json_encode($entry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  if ($line !== false) @file_put_contents($logFile, $line. PHP_EOL, FILE_APPEND | LOCK_EX);
}

/**
 * パスをRFC3986準拠で正規化
 * @param string $path パス
 */
function normalizeUrlPath($path) {
  if ($path === '') return '';

  $segments = explode('/', $path);
  $encodedSegments = array_map(function($segment) {
    return rawurlencode(rawurldecode($segment));
  }, $segments);

  return implode('/', $encodedSegments);
}

/**
 * クエリをRFC3986準拠で正規化
 * @param string $query クエリ
 */
function normalizeUrlQuery($query) {
  if ($query === '') return '';

  $pairs = explode('&', $query);
  $encodedPairs = [];

  foreach ($pairs as $pair) {
    if ($pair === '') {
      $encodedPairs[] = '';
      continue;
    }

    $parts = explode('=', $pair, 2);
    $key = rawurlencode(rawurldecode($parts[0]));
    if (count($parts) === 1) {
      $encodedPairs[] = $key;
      continue;
    }

    $value = rawurlencode(rawurldecode($parts[1]));
    $encodedPairs[] = $key . '=' . $value;
  }

  return implode('&', $encodedPairs);
}

/**
 * URLをRFC3986準拠で正規化
 * @param string $url URL
 * @return string|false
 */
function normalizeUrl($url) {
  if (!preg_match('~\A(https?://[^/?#]+)([^?#]*)?(\?[^#]*)?~u', $url, $matches)) {
    return false;
  }

  $base = $matches[1] ?? '';
  $pathRaw = $matches[2] ?? '';
  $queryRaw = isset($matches[3]) ? ltrim($matches[3], '?') : '';

  if ($base === '' || !preg_match('#\Ahttps?://#i', $base)) {
    return false;
  }

  $path = $pathRaw === '' ? '' : normalizeUrlPath($pathRaw);
  $query = $queryRaw === '' ? '' : normalizeUrlQuery($queryRaw);

  return $base . $path . ($query !== '' ? '?' . $query : '');
}

/**
 * http(s)検証
 * @param string $match_url URL
 */
function checkUrl($match_url) {
  $normalized = normalizeUrl($match_url);
  if ($normalized === false) return false;

  $parsed = parse_url($normalized);
  return is_array($parsed) && isset($parsed['scheme'], $parsed['host']);
}

/**
 * IPv6プレフィックス一致判定
 * @param string $ip IPv6アドレス
 * @param string $cidr 例: fe80::/10
 */
function ipv6InCidr($ip, $cidr) {
  [$subnet, $maskBits] = explode('/', $cidr);
  $ipBin = @inet_pton($ip);
  $subnetBin = @inet_pton($subnet);
  if ($ipBin === false || $subnetBin === false) return false;

  $maskBits = (int)$maskBits;
  $fullBytes = intdiv($maskBits, 8);
  $remainingBits = $maskBits % 8;

  if ($fullBytes > 0 && substr($ipBin, 0, $fullBytes) !== substr($subnetBin, 0, $fullBytes)) return false;
  if ($remainingBits === 0) return true;

  $mask = (0xFF << (8 - $remainingBits)) & 0xFF;
  return (
    (ord($ipBin[$fullBytes]) & $mask) ===
    (ord($subnetBin[$fullBytes]) & $mask)
  );
}

/**
 * 公開インターネット向けのIPか判定
 * @param string $ip IPアドレス
 */
function isPublicIp($ip) {
  if (!filter_var($ip, FILTER_VALIDATE_IP)) return false;

  if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
    return (bool)filter_var(
      $ip,
      FILTER_VALIDATE_IP,
      FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
    );
  }

  if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
    $denyCidrs = [
      '::/128',
      '::1/128',
      'fe80::/10',
      'fc00::/7',
      'ff00::/8',
      '2001:db8::/32',
      '::ffff:0:0/96',
    ];
    foreach ($denyCidrs as $cidr) {
      if (ipv6InCidr($ip, $cidr)) return false;
    }
    return true;
  }

  return false;
}

/**
 * ホスト名からIP一覧を取得
 * @param string $host ホスト名またはIP
 * @return string[]
 */
function resolveHostIps($host) {
  if (filter_var($host, FILTER_VALIDATE_IP)) return [$host];

  $ips = [];
  if (function_exists('dns_get_record')) {
    $records = @dns_get_record($host, DNS_A + DNS_AAAA);
    if (is_array($records)) {
      foreach ($records as $record) {
        if (!empty($record['ip'])) $ips[] = $record['ip'];
        if (!empty($record['ipv6'])) $ips[] = $record['ipv6'];
      }
    }
  }

  if (empty($ips)) {
    $v4 = @gethostbynamel($host);
    if (is_array($v4)) $ips = array_merge($ips, $v4);
  }

  return array_values(array_unique($ips));
}

/**
 * 公開アクセス可能なURLか判定（SSRF対策）
 * @param string $url URL
 */
function isSafePublicUrl($url) {
  $parts = parse_url($url);
  if (!is_array($parts) || !isset($parts['scheme'], $parts['host'])) return false;

  $schemeRaw = $parts['scheme'];
  $hostRaw = $parts['host'];
  if (!is_string($schemeRaw) || !is_string($hostRaw) || $schemeRaw === '' || $hostRaw === '') return false;

  $scheme = strtolower($schemeRaw);
  if ($scheme !== 'http' && $scheme !== 'https') return false;

  $host = strtolower($hostRaw);
  if ($host === 'localhost' || str_ends_with($host, '.localhost')) return false;

  $ips = resolveHostIps($host);
  if (empty($ips)) return false;

  foreach ($ips as $ip) {
    if (!isPublicIp($ip)) return false;
  }

  return true;
}

/**
 * Locationヘッダーから遷移先URLを解決
 * @param string $baseUrl 現在URL
 * @param string $location レスポンスのLocation値
 */
function resolveRedirectUrl($baseUrl, $location) {
  $location = trim($location);
  if ($location === '') return '';
  if (preg_match('#\Ahttps?://#i', $location)) return $location;

  $base = parse_url($baseUrl);
  if (!is_array($base) || !isset($base['scheme'], $base['host'])) return '';

  $schemeRaw = $base['scheme'];
  $hostRaw = $base['host'];
  if (!is_string($schemeRaw) || !is_string($hostRaw) || $schemeRaw === '' || $hostRaw === '') return '';

  $scheme = $schemeRaw;
  $host = $hostRaw;
  $port = (isset($base['port']) && is_int($base['port'])) ? ':' . $base['port'] : '';
  $path = $base['path'] ?? '/';

  if (str_starts_with($location, '//')) return $scheme . ':' . $location;
  if (str_starts_with($location, '/')) return $scheme . '://' . $host . $port . $location;

  $dir = preg_replace('#/[^/]*$#', '/', $path);
  return $scheme . '://' . $host . $port . $dir . $location;
}

/**
 * HTMLとして許可するContent-Typeか判定
 * @param string $contentType Content-Typeヘッダー値
 */
function isAllowedHtmlContentType($contentType) {
  $baseType = strtolower(trim(explode(';', $contentType, 2)[0] ?? ''));
  return $baseType === 'text/html' || $baseType === 'application/xhtml+xml';
}

/**
 * Bot対策/待機ページ系の遷移・本文か判定
 * @param array $responseHeaders レスポンスヘッダー
 * @param string $url 判定対象URL
 * @param string $responseBody レスポンス本文
 */
function isBotProtectionPage($responseHeaders, $url = '', $responseBody = '') {
  $connectorValues = $responseHeaders['x-queueit-connector'] ?? [];
  if (!empty($connectorValues)) return true;

  if ($url !== '') {
    $parts = parse_url($url);
    if (is_array($parts)) {
      $host = strtolower((string)($parts['host'] ?? ''));
      $query = strtolower((string)($parts['query'] ?? ''));
      if (str_contains($host, 'queue-it') || str_contains($host, '-wr.') || str_contains($query, 'enqueuetoken=')) return true;
    }
  }

  if ($responseBody !== '') {
    $bodyLower = strtolower($responseBody);
    if (str_contains($bodyLower, 'enqueuetoken=') || str_contains($bodyLower, 'x-queueit-connector') || str_contains($bodyLower, 'document.cookie') && str_contains($bodyLower, 'decodeuricomponent')) return true;
  }

  return false;
}

/**
 * URL取得
 * @param string $curl_url URL
 * @param string $errorCode 失敗理由コード
 */
function curl_get_contents($curl_url, &$errorCode = '') {
  $errorCode = '';
  $headers = [
    "User-Agent:Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.5 Safari/605.1.15"
  ];

  $maxRedirects = 5;
  $maxBytes = 1024 * 1024; //1MB
  $headReadLimitBytes = 512 * 1024; //先頭512KBを走査対象にする
  $currentUrl = $curl_url;

  for ($redirectCount = 0; $redirectCount <= $maxRedirects; $redirectCount++) {
    if (!isSafePublicUrl($currentUrl)) {
      $errorCode = 'blocked_url';
      return '';
    }

    $ch = curl_init();
    $responseHeaders = [];
    $responseBody = '';
    $headerComplete = false;
    $rejectBody = false;
    $bodyTooLarge = false;
    $titleFoundInHead = false;
    $headScanLimitReached = false;

    curl_setopt($ch, CURLOPT_URL, $currentUrl);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, false);
    curl_setopt($ch, CURLOPT_HEADER, false);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
    curl_setopt($ch, CURLOPT_AUTOREFERER, false);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
    curl_setopt($ch, CURLOPT_TIMEOUT, 8);
    curl_setopt($ch, CURLOPT_FAILONERROR, false);
    curl_setopt($ch, CURLOPT_PROTOCOLS, CURLPROTO_HTTP | CURLPROTO_HTTPS);
    curl_setopt($ch, CURLOPT_REDIR_PROTOCOLS, CURLPROTO_HTTP | CURLPROTO_HTTPS);
    curl_setopt($ch, CURLOPT_HTTPGET, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);

    curl_setopt($ch, CURLOPT_HEADERFUNCTION, function($ch, $headerLine) use (&$responseHeaders) {
      $trimmed = trim($headerLine);
      if ($trimmed === '' || !str_contains($trimmed, ':')) return strlen($headerLine);

      [$name, $value] = explode(':', $trimmed, 2);
      $name = strtolower(trim($name));
      $value = trim($value);
      if (!isset($responseHeaders[$name])) $responseHeaders[$name] = [];

      $responseHeaders[$name][] = $value;
      return strlen($headerLine);
    });

    curl_setopt($ch, CURLOPT_WRITEFUNCTION, function($ch, $chunk) use (&$responseBody, &$responseHeaders, &$headerComplete, &$rejectBody, &$bodyTooLarge, &$titleFoundInHead, &$headScanLimitReached, $maxBytes, $headReadLimitBytes) {
      if (!$headerComplete) {
        $headerComplete = true;
        $contentTypeValues = $responseHeaders['content-type'] ?? [];
        $contentType = '';
        if (!empty($contentTypeValues)) {
          $last = end($contentTypeValues);
          if ($last !== false) $contentType = (string)$last;
        }
        if ($contentType === '' || !isAllowedHtmlContentType($contentType)) {
          $rejectBody = true;
          return 0;
        }
      }

      if ($rejectBody) return 0;

      $nextSize = strlen($responseBody) + strlen($chunk);
      if ($nextSize > $maxBytes) {
        $bodyTooLarge = true;
        return 0;
      }
      $responseBody .= $chunk;

      //先頭だけ走査し、title情報が見つかった時点でダウンロードを打ち切る
      if (preg_match('/<meta[^>]+(?:property|name)\s*=\s*["\']og:title["\'][^>]*>/i', $responseBody) || stripos($responseBody, '</title>') !== false) {
        $titleFoundInHead = true;
        return 0;
      }

      if (strlen($responseBody) >= $headReadLimitBytes) {
        $headScanLimitReached = true;
        return 0;
      }

      return strlen($chunk);
    });

    $ok = curl_exec($ch);
    $httpCode = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $curlErr = curl_errno($ch);

    if ($titleFoundInHead && $curlErr !== 0) {
      //WRITEFUNCTIONで意図的に停止した場合は成功扱い
      $curlErr = 0;
      $ok = true;
    }

    if ($headScanLimitReached && !$titleFoundInHead) {
      $errorCode = 'title_not_found_in_head';
      return '';
    }

    if ($rejectBody) {
      $errorCode = 'unsupported_content_type';
      return '';
    }

    if ($bodyTooLarge) {
      $errorCode = 'response_too_large';
      return '';
    }

    if ($ok === false || $curlErr !== 0) {
      $errorCode = 'fetch_failed';
      return '';
    }

    if ($httpCode >= 300 && $httpCode < 400) {
      $locationValues = $responseHeaders['location'] ?? [];
      $nextLocation = end($locationValues);
      if ($nextLocation === false || $nextLocation === '') {
        $errorCode = 'invalid_redirect';
        return '';
      }

      $nextUrl = resolveRedirectUrl($currentUrl, $nextLocation);
      if ($nextUrl === '') {
        $errorCode = 'invalid_redirect';
        return '';
      }

      //待機ページ系はリダイレクト時点で打ち切って早期返却
      if (isBotProtectionPage($responseHeaders, $nextUrl)) {
        $errorCode = 'bot_protection';
        return '';
      }

      $currentUrl = $nextUrl;
      continue;
    }

    if ($httpCode < 200 || $httpCode >= 300) {
      $errorCode = 'http_error';
      return '';
    }

    if (isBotProtectionPage($responseHeaders, $currentUrl, $responseBody)) {
      $errorCode = 'bot_protection';
      return '';
    }

    return $responseBody;
  }

  $errorCode = 'too_many_redirects';
  return '';
}
