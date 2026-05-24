# url-get-title

URLからtitle要素の文言を取得する

## 取得書式

`https://example.com`で取得した例

### タイトル文言＋URL（タイトル文言とURLの間に改行あり）

```txt
title
https://example.com
```

### リンク（Markdown形式）

```txt
[title](https://example.com)
```

### リンク（HTML形式）

```txt
<a href="https://example.com">title</a>
```

### タイトル文言のみ

```txt
title
```

### URLのみ

```txt
https://example.com
```

## 使用ライブラリ

以下のライブラリを利用しています。

- [Twig](https://twig.symfony.com) (BSD-3-Clause License)

## 本スクリプトのライセンス

MIT license
