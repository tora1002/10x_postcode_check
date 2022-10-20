<?php

$test_array = makeArrayForCsv("okura_20220927.csv");

$result_address_information_for_navitaime_api = array();
foreach ($test_array as $post_code => $ary) {
    // 住所の配列だけを返す
    $result_address_information_for_navitaime_api[$post_code] = getAddressInformationForNavitaimeApi($post_code);
}

// 比較する
compareAddressInformation($test_array, $result_address_information_for_navitaime_api);

//***** 自作関数 *****//
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
            if (is_null($item[7])) {
                array_push($output_array[$item[3]], $item[4].$item[5].$item[6]);
            } else {
                array_push($output_array[$item[3]], $item[4].$item[5].$item[6].$item[7]);
            }
        } else {
            if (is_null($item[7])) {
                $output_array[$item[3]] = array($item[4].$item[5].$item[6]);
            } else {
                $output_array[$item[3]] = array($item[4].$item[5].$item[6].$item[7]);
            }
        }
    }
    
    return $output_array;
}


function getAddressInformationForNavitaimeApi($post_code)
{ 
    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_URL => "https://navitime-geocoding.p.rapidapi.com/address/postal_code?postal_code=". $post_code ."&datum=wgs84&offset=0&coord_unit=degree&limit=20",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_ENCODING => "",
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => "GET",
        CURLOPT_HTTPHEADER => [
        "X-RapidAPI-Host: navitime-geocoding.p.rapidapi.com",
        "X-RapidAPI-Key: 3fdbab3117msh19426a0d02cc7cdp1711a9jsnf707142a6934"
        ],
    ]);

    $response = curl_exec($curl);
    $err = curl_error($curl);

    curl_close($curl);

    if ($err) {
        echo "cURL Error #:" . $err;
    } else {
        $response_obj = json_decode($response);
        $items_obj = $response_obj->items;
        $result_array = array();
        // 返ってきたオブジェクトから、存在するであろう住所一覧を生成する
        foreach($items_obj as $obj) {
            array_push($result_array, $obj->name);
        }
        return $result_array;
    }
}

function compareAddressInformation($csv_array, $api_array)
{
    // APIの結果にあって、csvのリストにないので、抜け漏れの可能性あり
    $exist_api_array = array();
    foreach($api_array as $api_key => $api_values_array) {
        foreach ($api_values_array as $api) {
            foreach ($csv_array[$api_key] as $csv) {
                if ($api == $csv) {
                    if (empty($exist_api_array["$api_key"])) {
                        $exist_api_array["$api_key"] = [$api];
                    } else {
                        foreach ($exist_api_array as $key => $val) {
                            if ($key == $api_key) {
                                array_push($exist_api_array[$api_key], $api);
                            }
                        }
                    }
                    break;
                }
            }
        }
        $not_api_array = array_diff_recursive($api_array, $exist_api_array);
    }

    // csvリストにあって、APIの結果にないので、調査必要（あまり想定していない）
    $exist_csv_array = array();
    foreach($csv_array as $csv_key => $csv_values_array) {
        foreach ($csv_values_array as $csv) {
            foreach ($api_array[$csv_key] as $api) {
                if ($api == $csv) {
                    if (empty($exist_csv_array["$csv_key"])) {
                        $exist_csv_array["$csv_key"] = [$csv];
                    } else {
                        foreach ($exist_csv_array as $key => $val) {
                            if ($key == $csv_key) {
                                array_push($exist_csv_array[$csv_key], $csv);
                            }
                        }
                    }
                    break;
                }
            }
        }
        $not_csv_array = array_diff_recursive($csv_array, $exist_csv_array);
    }

    // 表示
    if (empty($not_api_array)) {
        echo "APIの結果にあって、csvのリストにない住所は存在しない！\n";
    } else {
        echo "APIの結果にあって、csvのリストにない住所は存在一覧：\n";
        foreach ($not_api_array as $not_api_key => $not_api_array_detials) {
            echo "郵便番号：". $not_api_key ."\n";
            foreach ($not_api_array_detials as $ary) {
                echo "$ary\n";
            }
        }
    }

    if (empty($not_csv_array)) {
        echo "CSVにあって、API結果にない住所は存在しない！\n";
    } else {
        echo "CSVにあって、APIの結果にない住所は存在一覧：\n";
        foreach ($not_csv_array as $not_csv_key => $not_csv_array_detials) {
            echo "郵便番号". $not_csv_key ."\n";
            foreach ($not_csv_array_detials as $ary) {
                echo "$ary\n";
            }
        }
    }
}

// 多次元配列の差分
// 参考： https://hotexamples.com/jp/examples/-/-/array_diff_recursive/php-array_diff_recursive-function-examples.html
// 上記を一部カスタマイズ
function array_diff_recursive($array1, $array2)
{
    //Compared two arrays recursively to find differences.
    $output = array();
    foreach ($array1 as $nKey => $nValue) {
        if (array_key_exists($nKey, $array2)) {
            if (is_array($nValue)) {
                $recursiveDiff = array_diff($nValue, $array2[$nKey]);
                if (count($recursiveDiff)) {
                    $output[$nKey] = $recursiveDiff;
                } else {
                    if ($nValue != $array2[$nKey]) {
                        $output[$nKey] = $nValue;
                    }
                }
            }
        } else {
            $output[$nKey] = $nValue;
        }
    }
    return $output;
}
