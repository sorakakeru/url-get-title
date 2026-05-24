/**
 * url-get-title
 * https://github.com/sorakakeru/url-get-title
 * 
 * Copyright (c) 2025 Yamatsu
 * Released under the MIT license
 * https://github.com/sorakakeru/url-get-title/blob/main/LICENSE
 */

/**
 * 入力フォームバリデーションチェック
 */

const form = document.querySelector('.form_area form');
const checkInput = document.querySelector('.form_area input[name="url"]');

form.addEventListener('submit', (e) => {
  const eText = document.querySelector('.form_area .error');
  if (eText) eText.remove();

  const currentResultArea = document.querySelector('.result_area');
  if (currentResultArea) currentResultArea.remove();

  let isValidUrl = true;
  try {
    const url = new URL(checkInput.value);
    if (url.protocol !== 'http:' && url.protocol !== 'https:') isValidUrl = false;
  } catch {
    isValidUrl = false;
  }

  if (checkInput.value.length === 0) { //URLの入力がない場合
    form.insertAdjacentHTML('afterend', '<p class="error">URLを入力してください</p>');
    e.preventDefault();
  } else if (!isValidUrl) { //URLが正しくない場合
    form.insertAdjacentHTML('afterend', '<p class="error">URLを正しく入力してください</p>');
    e.preventDefault();
  }
});


/**
 * コピーボタン
 */

const resultArea = document.querySelector('.result_area');

if (resultArea) {
  const generateArea = resultArea.querySelectorAll('dd');
  generateArea.forEach(function(elm) {
    const btn = elm.querySelector('button');
    const copyTarget = elm.querySelector('p');
    btn.addEventListener('click', copyTxt);

    function copyTxt() {
      const copyText = getTextWithBrNewline(copyTarget);
      (navigator.clipboard && window.isSecureContext)
        ? navigator.clipboard.writeText(copyText).then(success, faild)
        : alert('コピーはhttps接続環境でのみ有効です');
    }

    function getTextWithBrNewline(target) {
      if (!target) return '';

      const clone = target.cloneNode(true);
      clone.querySelectorAll('br').forEach(function(br) {
        br.replaceWith('\n');
      });
      return clone.textContent;
    }

    function success() {
      alert('コピーしたよ！');
    }
    function faild() {
      alert('コピーできなかった、ごめん！');
    }
  });
}
