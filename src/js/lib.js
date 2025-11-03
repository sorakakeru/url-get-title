/**
 * url-get-title
 * https://github.com/sorakakeru/url-get-title
 * 
 * Copyright (c) 2025 Yamatsu
 * Released under the MIT license
 * https://github.com/sorakakeru/url-get-title/blob/main/LICENSE
 */

const form = document.querySelector('.form_area form')
const checkInput = document.getElementById('url')
const generateArea = document.querySelectorAll('.generate_area')


/**
 * 入力フォームバリデーションチェック
 */

form.addEventListener('submit', (e) => {
  const eText = document.querySelector('.form_area .error')
  if (eText) eText.remove()

  generateArea.forEach(function(elm) {
    elm.closest('.result').remove()
    elm.closest('.result').previousElementSibling('hr').remove()
  })

  let isValidUrl = true
  try {
    const url = new URL(checkInput.value)
    if (url.protocol !== 'http:' && url.protocol !== 'https:') {
      isValidUrl = false
    }
  } catch {
    isValidUrl = false
  }

  if (checkInput.value.length === 0) { //URLの入力がない場合
    form.insertAdjacentHTML('afterend', '<p class="error">URLを入力してください</p>')
    e.preventDefault()
  } else if (!isValidUrl) { //URLが正しくない場合
    form.insertAdjacentHTML('afterend', '<p class="error">URLを正しく入力してください</p>')
    e.preventDefault()
  }
  
})


/**
 * コピーボタン
 */
generateArea.forEach(function(elm, i) {
  const btn = elm.querySelector('button')
  btn.addEventListener('click', copyTxt)

  function copyTxt() {
    if (navigator.clipboard && window.isSecureContext) {
      navigator.clipboard.writeText(elm.querySelector('p').textContent).then(success, faild)
    } else {
      alert('コピーはhttps接続環境でのみ有効です')
    }
  }

  function success() {
    alert('コピーしたよ！')
  }
  function faild() {
    alert('コピーできなかった、ごめん！')
  }
})