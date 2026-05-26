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

require_once __DIR__. '/_modules/vendor/autoload.php';

//Twig
$loader = new \Twig\Loader\FilesystemLoader(__DIR__. '/_modules/tmpl');
$twig = new \Twig\Environment($loader, []);
$template = $twig->load('index.html.twig');

//include
require_once __DIR__. '/_modules/fnc_inc/functions.php';

//セッションCookie属性を強化
$isHttps = (
  (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
  || (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443)
);
session_set_cookie_params([
  'lifetime' => 0,
  'path' => '/',
  'domain' => '',
  'secure' => $isHttps,
  'httponly' => true,
  'samesite' => 'Lax',
]);
session_start();

//def init
$token = '';
$sendSuccess = false;
$get_title = '';
$get_url = '';
$get_html = '';
$fetchCharset = '';
$error = [];


//token生成
if (empty($_SESSION['token'])) $_SESSION['token'] = generateToken();
$token = $_SESSION['token'];


//フォーム送信処理
if (isset($_POST['send'])) {

  //token確認
  $token = isset($_POST['token']) ? $_POST['token'] : '';
  $validateToken = validateToken($token);

  if (!$validateToken) {
    $error[] = '不正な操作を検出したため送信できませんでした';
    writeAccessBlockLog('csrf_invalid');
  } else {

    $get_url = trim((string)($_POST['url'] ?? ''));
    $normalizedUrl = normalizeUrl($get_url);

    //バリデーションチェック
    if (empty($get_url)) $error[] = 'URLを入力してください';
    if ($normalizedUrl === false) $error[] = 'URLを正しく入力してください';

    if ($normalizedUrl !== false) {
      $get_url = $normalizedUrl;
    }

    if (empty($error) && !isSafePublicUrl($get_url)) {
      $error[] = 'このURLにはアクセスできません';
      writeAccessBlockLog('blocked_url', $get_url);
    }

    if (empty($error)) {
      $fetchErrorCode = '';
      $fetchCharset = '';
      $get_html = curl_get_contents($get_url, $fetchErrorCode, $fetchCharset);
      if ($get_html === '') {
        $reasonCode = $fetchErrorCode !== '' ? $fetchErrorCode : 'fetch_failed';
        writeAccessBlockLog($reasonCode, $get_url);
        if ($fetchErrorCode === 'bot_protection') {
          $error[] = '取得先の環境により、タイトル文言を取得できませんでした';
        } elseif ($fetchErrorCode === 'title_not_found_in_head') {
          $error[] = '取得先の環境により、タイトル文言を取得できませんでした';
        } elseif ($fetchErrorCode === 'unsupported_content_type') {
          $error[] = 'このURL先のページはHTMLファイルではないため取得できませんでした';
        } else {
          $error[] = 'URL先のページ情報を取得できませんでした';
        }
      }
    }

    //エラーがなければタイトル取得処理
    if (empty($error)) {
      //文字コード検出・UTF-8変換
      $detectedEncoding = $fetchCharset;
      if ($detectedEncoding === '') {
        //Content-Typeヘッダーになければmetaタグから検出
        if (preg_match('/<meta[^>]+charset\s*=\s*["\']?\s*([a-zA-Z0-9\-_]+)/i', $get_html, $encm)) {
          $detectedEncoding = $encm[1];
        } elseif (preg_match('/<meta[^>]+content\s*=\s*["\'][^"\'>]*charset=([a-zA-Z0-9\-_]+)/i', $get_html, $encm)) {
          $detectedEncoding = $encm[1];
        }
      }
      //UTF-8以外なら変換
      if ($detectedEncoding !== '' && strtolower(str_replace(['-', '_'], '', $detectedEncoding)) !== 'utf8') {
        try {
          $converted = mb_convert_encoding($get_html, 'UTF-8', $detectedEncoding);
          if ($converted !== false && $converted !== '') {
            $get_html = $converted;
            //変換後はcharset宣言を除去（DOMDocumentが元のエンコーディングで再解釈するのを防ぐ）
            $get_html = preg_replace('/<meta\b[^>]*charset[^>]*>/i', '', $get_html) ?? $get_html;
          }
        } catch (\Throwable $e) {
          //不明なエンコーディング名など変換失敗時は元のHTMLのまま処理（文字化けするが取得は継続）
        }
      }

      //DOMDocumentでタイトル取得
      $doc = new DOMDocument();
      libxml_use_internal_errors(true); //HTMLパースの警告を抑制
      $doc->loadHTML('<?xml encoding="UTF-8">' . $get_html);
      libxml_clear_errors();

      //優先度）og:title > title要素
      $xpath = new DOMXPath($doc);
      $ogTitleNode = $xpath->query('//meta[translate(@property, "ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz")="og:title" or translate(@name, "ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz")="og:title"]/@content');
      if ($ogTitleNode !== false && $ogTitleNode->length > 0) $get_title = trim((string)$ogTitleNode->item(0)->nodeValue);

      if ($get_title === '') {
        $titleTags = $doc->getElementsByTagName('title');
        if ($titleTags->length > 0) $get_title = trim($titleTags->item(0)->textContent);
      }

      if ($get_title === '') {
        $error[] = '取得先の環境により、タイトル文言を取得できませんでした';
        writeAccessBlockLog('title_not_found', $get_url);
      }

      if (empty($error)) {
        $sendSuccess = true;

        //token再生成（削除の代わり）
        $_SESSION['token'] = generateToken();
        $token = $_SESSION['token'];
      }

    }

  }

}


//Twigに渡してレンダリング
echo $template->render([
  'token' => $token,
  'sendSuccess' => $sendSuccess,
  'setTitle' => $get_title,
  'setURL' => $get_url,
  'error' => $error
]);
