# 住所チェックツール

## これはなんですか？
トウライから入稿される郵便番号や住所データが間違ってないか、最低限チェックするための簡易ツールです。
できることは限られていますが、やらないよりはマシかなと。

## 何がチェックできるの？
* GogleMapAPIを用いて、郵便番号をキーにして、正しい住所が記載されているか？
* GogleMapAPIを用いて、データの中にある◯丁目以降にも、住所が存在しているかどうか？
    * 存在している場合は、他の店舗の配送対象になっているか？そもそも配送対象外なのか確認が必要
* 番地（◯丁目）で、歯抜けのデータがないか？（ex. 入稿データに1丁目と4丁目は存在するが、2丁目・3丁目が存在しない）
    * このような場合は、他の店舗の配送対象になっているか？そもそも配送対象外なのか確認が必要
<br>
※ チェックロジックは下に記載。

## 動作環境
PHP 7.3.29 では動くことは確認済

## 動作方法（コマンド）
php check_google.php （ファイル名）

## 実行結果イメージ
```
===== チェック開始 =====
==========
2440842 チェック
==========
2440843 チェック
==========
2470006 チェック
==========
2470051 チェック
==========
2470055 チェック
==========
2470056 チェック
==========
2470061 チェック
他にも番地がありそうですけど、大丈夫ですか？
神奈川県鎌倉市台3丁目 以降
==========
2470071 チェック
==========
2470072 チェック
==========
2470073 チェック
==========
2470074 チェック
==========
2470075 チェック
==========
==========
===== チェック完了 =====
```

## 判定ロジック
### 郵便番号に1つの住所しか紐づいておらず、番地がnull
* 例. 神奈川県藤沢市高谷
* この場合は、1つの郵便番号で全番地を表し、全て配送するものとする
* なので、地名から郵便番号を引っ張り、それが正しいかどうかのみを検出する

### 郵便番号に1つの住所しか紐づいておらず、番地がnull「ではない」
* 例. 神奈川県鎌倉市岩瀬2丁目
* 1丁目でなければ、1〜◯丁目までないけど大丈夫か指摘する
* 地名から郵便番号を引っ張り、それが正しいかどうか検出する
* その後に番地がないか検索し、あれば追加しなくて良いかアラートを出す
    * 例えば、2丁目のデータはあるが、3丁目のデータがないか検索

### 郵便番号に複数の住所が紐づいている
* 例. 神奈川県横浜市栄区笠間1〜5丁目
* 番地に歯抜けがないか検証する
    * （例えば、1丁目と4丁目はデータにあるが、2丁目、3丁目がデータにない）
* 地名から郵便番号を引っ張り、それが正しいかどうか検出する
* データ上一番最後の番地の後にも、番地がないか検索し、あれば追加しなくて良いかアラートを出す
    * 例えば、4丁目までのデータはあるが、5丁目のデータがないか検索







