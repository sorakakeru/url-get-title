<?php
/**
 * url-get-title
 * https://github.com/sorakakeru/url-get-title
 * 
 * Copyright (c) 2025 Yamatsu
 * Released under the MIT license
 * https://github.com/sorakakeru/url-get-title/blob/main/LICENSE
 */

  session_start();

  //include setting
  require_once __DIR__. '/functions.php';
    
  //token
  if (empty($_SESSION['token'])) {
    $_SESSION['token'] = generate_token();
  }
  $token = h($_SESSION['token']);


  //var
  $e_msg = '';
  $result_html = '';

  //リンクを生成ボタンが押された時の処理
  if (isset($_POST['send'])) {

    //token確認
    if (!validate_token($_POST['token'])) {
      $e_msg = '不正な操作を検出したため動作を停止しました';
    } else {

      $url = $_POST['url'];
      //フォーム入力チェック
      if(empty($url)) {
        $e_msg = 'URLを入力してください';
      } elseif (!checkUrl($url)) {
        $e_msg = 'URLを正しく入力してください';
      } else {

        //生成処理
        $get_html = curl_get_contents($url);

        if(!empty($get_html)) { //urlが取得できれば

          // DOMDocumentでタイトル取得
          $doc = new DOMDocument();
          libxml_use_internal_errors(true); // HTMLパースの警告を抑制
          $doc->loadHTML($get_html);
          libxml_clear_errors();

          $title_name = '';
          $titleTags = $doc->getElementsByTagName('title');
          if ($titleTags->length > 0) {
            $title_name = $titleTags->item(0)->textContent;
            $title_name = mb_convert_encoding(trim($title_name), 'UTF-8', 'HTML-ENTITIES, UTF-8, SJIS, EUC-JP, JIS, ASCII');
          }

          //出力
          $result_html .= '<hr>' ."\n";
          $result_html .= '<dl class="result">';
          $result_html .= '<dt>タイトル文言＋URL</dt>';
          $result_html .= '<dd><div class="generate_area"><p>' .h($title_name). '<br>' ."\n" .h(normalizeUrl($url)). '</p><button type="button">copy</button></div></dd>' ."\n";
          $result_html .= '<dt>リンク（Markdown形式）</dt>';
          $result_html .= '<dd><div class="generate_area"><p><code>[' .h($title_name). '](' .h(normalizeUrl($url)). ')</code></p><button type="button">copy</button></div></dd>' ."\n";
          $result_html .= '<dt>リンク（HTML形式）</dt>';
          $result_html .= '<dd><div class="generate_area"><p><code>&lt;a href="' .h(normalizeUrl($url)). '" target="_blank"&gt;' .h($title_name). '&lt;/a&gt;</code></p><button type="button">copy</button></div></dd>' ."\n";
          $result_html .= '<dt>タイトル文言のみ</dt>';
          $result_html .= '<dd><div class="generate_area"><p>' .h($title_name). '</p><button type="button">copy</button></div></dd>' ."\n";
          $result_html .= '</dl>' ."\n";

        } else { //取得できなければ
          $e_msg = 'URLが取得できませんでした';
        }
      }

    }

  }
?>

<div class="form_area">
  <form action="" method="post">
    <input type="hidden" name="token" value="<?php echo $token; ?>">
    <dl>
      <dt>URLを入力</dt>
      <dd>
        <input type="text" name="url" id="url" placeholder="https://">
        <button type="submit" name="send">取得</button>
      </dd>
    </dl>
  </form>
  <?php if (!empty($e_msg)): ?>
    <p class="error"><?php echo h($e_msg); ?></p>
  <?php endif; ?>
</div>

<?php
  if (!empty($result_html)) {
    echo $result_html;
  }
?>
