<?php
// コマンドからcsv名を取得
$csv_file_name = $argv[1];

// 扱いやすいようにcsvから配列にデータ変換
$csv_array = makeArrayForCsv($csv_file_name);

// データチェック
checkData($csv_array);

//***** メイン自作関数 *****//
function makeArrayForCsv($file_name)
{
    $csv_file = file_get_contents($file_name);

    //変数を改行毎の配列に変換
    $aryHoge = explode("\n", $csv_file);

    $aryCsv = [];
    foreach($aryHoge as $key => $value){
        if($key == 0) continue; //1行目が見出しなど、取得したくない場合
        if(!$value) continue; //空白行が含まれていたら除外
        $aryCsv[] = explode(",", $value);
    }

    $output_array = array();
    foreach($aryCsv as $item) {
        if (array_key_exists($item[3], $output_array)) {
            if ($item[7] == "") {
                array_push($output_array[$item[3]], array($item[4], $item[5], $item[6]));
                sort($output_array[$item[3]]);
            } else {
                array_push($output_array[$item[3]], array($item[4], $item[5], $item[6], $item[7]));
                sort($output_array[$item[3]]);
            }
        } else {
            if ($item[7] == "") {
                $output_array[$item[3]] = array(array($item[4], $item[5], $item[6]));
            } else {
                $output_array[$item[3]] = array(array($item[4], $item[5], $item[6], $item[7]));
            }
        }
    }
    
    return $output_array;
}

function checkData($csv_array)
{
    print_r("===== チェック開始 =====\n");
    foreach ($csv_array as $post_code => $address_array) {
        print_r("==========\n");
        print_r($post_code ." チェック\n");
        if (count($address_array) == 1 && count($address_array[0]) == 3) {  // 郵便番号に1つしか住所が紐づいていない＆番地（x丁目）がない
            checkGoogleMapApi($post_code, $address_array[0]);
            continue;
        } elseif (count($address_array) == 1 && count($address_array[0]) == 4) { // 郵便番号に1つしか住所が紐づいていない＆番地（x丁目）がある
            // 歯抜けになっている可能性を指摘
            if ($address_array[0][3] != "1丁目") {
                // 1丁目ではないので、2以上の何かしらの数字が取れる
                // 丁目を削除し、数字のみに
                $j = getBanchi($address_array[0][3]);

                // -1して
                $j = (int)$j - 1;

                // 結合してプリント
                $address_string = $address_array[0][0].$address_array[0][1].$address_array[0][2];
                
                if ($j == 1) {
                    print_r($address_string."1丁目までのデータが含まれていませんが大丈夫すか？\n");
                } else {
                    print_r($address_string."1丁目 〜 ".$j."丁目までのデータが含まれていませんが大丈夫すか？\n");
                }
            }
            
            // 住所が正しいかチェック＆次の番地あるかチェック
            checkAddressAndNextAddressForGoogleMapApi($post_code, $address_array[0]);
            continue;
        } elseif (count($address_array) > 1 && count($address_array[0]) == 3) { // 郵便番号に複数の住所が紐づいている＆1番最初の住所に番地（x丁目）がない
            // 住所がいくつあるか（配列キーに合わせて -1）
            $count = count($address_array) - 1;

            // 正しい番地かどうかを観測する変数
            $x = 0;

            // 現在の配列データの番地
            $j = 0;
            foreach ($address_array as $i => $item_address_array) {
                if ($i == 0) { // 一番最初は、住所チェックのみ
                    checkGoogleMapApi($post_code, $item_address_array);
                } elseif ($i < $count) { // 2番目 〜 $count-1番目は歯抜けになってないか、と住所チェック
                    // 歯抜けチェック
                    $j = checkLostAddress($address_array, $item_address_array, $i, $x);
                    
                    // 住所チェック
                    checkGoogleMapApi($post_code, $item_address_array);
                } else { // 最後は住所チェックと、次の郵便番号がないかチェック
                    // 歯抜けチェック
                    $j = checkLostAddress($address_array, $item_address_array, $i, $x);

                    // 住所が正しいかチェック＆次の番地あるかチェック
                    checkAddressAndNextAddressForGoogleMapApi($post_code, $item_address_array);
                }
                $x = $j + 1;
                continue;
            } 
            continue;
        } else { // 郵便番号に複数の住所が紐づいている＆1番最初の住所に番地（x丁目）がある
            // 住所がいくつあるか（配列キーに合わせて -1）
            $count = count($address_array) - 1;

            // 正しい番地かどうかを観測する変数
            $x = 1;

            // 現在の配列データの番地
            $j = 1;
            foreach ($address_array as $i => $item_address_array) {
                if ($i < $count) { // 2番目 〜 $count-1番目は歯抜けになってないか、と住所チェック
                    // 歯抜けチェック
                    $j = checkLostAddress($address_array, $item_address_array, $i, $x, $j);

                    // 住所チェック
                    checkGoogleMapApi($post_code, $item_address_array);
                } else { // 最後は住所チェックと、次の郵便番号がないかチェック
                    // 歯抜けチェック
                    $j = checkLostAddress($address_array, $item_address_array, $i, $x, $j);

                    // 住所が正しいかチェック＆次の番地あるかチェック
                    checkAddressAndNextAddressForGoogleMapApi($post_code, $item_address_array);
                }
                $x = $j + 1;
                continue;
            } 
            continue;
        }
    }
    print_r("==========\n");
    print_r("===== チェック完了 =====\n");
}

//***** サブ自作関数 *****//
// GoogleMap APIに問い合わせ
function checkGoogleMapApi($post_code_string, $address_array)
{
    $post_code = substr($post_code_string, 0, 3). "-" .substr($post_code_string, -4);
    $address_string = makeAdressStringForArray($address_array);

    list($response_address, $response_post_code) = requestGoogleMapApi($address_string);
    
    if ($address_string =! $response_address) {
        print_r("住所が違う！\n");
        print_r("csvデータ： ". $address_string ."\n");
        print_r("GoogleMap： ". $response_address ."\n");
    } elseif ($post_code =! $response_post_code) {
        print_r("郵便番号が違う！\n");
        print_r("csvデータ： ". $post_code ."\n");
        print_r("GoogleMap： ". $response_post_code ."\n");
    }
}

function checkNextAddressGoogleMapApi($post_code_string, $address_array)
{
    $post_code = substr($post_code_string, 0, 3). "-" .substr($post_code_string, -4);
    $address_string = makeNextAdressStringForArray($address_array);

    list($response_address, $response_post_code) = requestGoogleMapApi($address_string);

    if ($address_string == $response_address && $post_code == $response_post_code) {
        print_r("他にも番地がありそうですけど、大丈夫ですか？\n");
        print_r($address_string. " 以降\n");
    }
}

// 住所チェックと次の住所があるかのチェック
function checkAddressAndNextAddressForGoogleMapApi($post_code_string, $address_array)
{
    // 郵便番号が正しいかチェック
    checkGoogleMapApi($post_code_string, $address_array);

    // 次の郵便番号ある？
    checkNextAddressGoogleMapApi($post_code_string, $address_array);
}

// 本当にリクエストだけ投げる場所
function requestGoogleMapApi($address_string)
{
    // リクエスト
    $api_key = "AIzaSyBi4E3Kdw63Jt_l-ZHMwyHKcK1pBP-rOJc";
    $request_url = "https://maps.googleapis.com/maps/api/geocode/json?address=". urlencode($address_string) ."&language=ja&components=country:JP&key=". $api_key;
    $response_array = json_decode(file_get_contents($request_url), true);
    
    // 整形
    $response_address = mb_convert_kana(preg_replace("/日本、〒\d{3}-\d{4} /", "", $response_array["results"][0]["formatted_address"]), "n");
    preg_match("/\d{3}-\d{4}/", $response_array["results"][0]["formatted_address"], $post_code_match_array);
    $response_post_code = $post_code_match_array[0];

    return array($response_address, $response_post_code);
}

function makeAdressStringForArray($address_array)
{
    $i = 0;
    $count = count($address_array);
    $address_string = "";
    while ($i < $count) {
        $address_string = $address_string . $address_array[$i];
        $i++;
    }

    return $address_string;
}

function makeNextAdressStringForArray($address_array)
{
    $i = 0;
    $count = count($address_array);
    $address_string = "";
    while ($i < $count) {
        if ($i == 3) {
            // 丁目を削除し、数字のみに
            $j = preg_replace("/丁目/", "", $address_array[3]);

            // +1して
            $j = (int)$j + 1;
            
            // 結合
            $address_string = $address_string . (string)$j . "丁目";
        } else {
            $address_string = $address_string . $address_array[$i];
        }
        $i++;
    }

    return $address_string;
}

function getBanchi($address)
{
    return preg_replace("/丁目/", "", $address);
}

function checkLostAddress($address_array, $item_address_array, $i, $x)
{
    // 丁目を削除
    $j = getBanchi($item_address_array[3]);

    // 歯抜けになっていないかチェック
    if ($x != $j) {
        // 結合してプリント
        $address_string = $item_address_array[0].$item_address_array[1].$item_address_array[2];
        if ($j == 2) {
            print_r($address_string."1丁目のデータが含まれていませんが大丈夫すか？\n");
        } else {
            // 確実に抜けている番地
            $k = $j - 1;

            // 1つ前のデータの番地を取得する
            $l = getBanchi($address_array[$i-1][3]);

            // 1つ前のデータは存在するので、1つ数字を足す
            $l++;

            if ($k == $l) {
                print_r($address_string.$k."丁目のデータが含まれていませんが大丈夫すか？\n");
            } else {
                print_r($address_string.$l."丁目 〜 ".$k."丁目までのデータが含まれていませんが大丈夫すか？\n");
            }
        }
    }

    return $j;
}

