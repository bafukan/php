<?php
//運用番号to編成 予測関数nyosoku_unyo
//入力 : 運用id, 運用num, datekey
    //運用id : '日支', '京支', ...
    //運用num : 121, 122, ...
    //datekey : 当日...0, 1日後...1, 2日後...2
//出力 : TF, master, train, unknown, daiso
    //[0]TF : T...当日情報を使用, F...前日までの情報を使用
    //[1]master : 'ヒネ', 'キト', ...
    //[2]train : 'HE401', 'HE402', ...
    //[3]unknown : 不確定フラグ 1...有効, 0...無効
    //[4]daiso : 代走フラグ 1...有効, 0...無効
function nyosoku_unyo($id,$num,$key) {
    $pdo_data = 'DBkey';
    //初期化
    $minnum = null;
    $maxnum = null;
    //unyoindex/運用リストを参照,当該運用番号の最大最小値を取得
    $stmt = $pdo_data->prepare('SELECT * FROM unyo_index');
    $stmt->execute();
    while ( $db = $stmt->fetch(PDO::FETCH_ASSOC) ) {
        if ( $db['unyoid'] == $id && $db['min'] <= $num && $db['max'] >= $num ) {
            $minnum = $db['min'];
            $maxnum = $db['max'];
            break;
        }
    }
    //範囲外の運用番号を入力された場合,nullを返す
    if ( $minnum == null || $maxnum == null ) {
        return null;
    }
    //運用データを参照する際に基準となる運用番号を算出/key=+2なら(最新日/2つ前の運用番号)からデータを参照する
    $keynum = $num;
    if ( $key == 2 ) {
        $keynum = $keynum - 2;
        while ( $keynum < $minnum ) {
            $keynum = $keynum + ($maxnum-$minnum+1);
        }
    } elseif ( $key == 1 ) {
        $keynum = $keynum - 1;
        while ( $keynum < $minnum ) {
            $keynum = $keynum + ($maxnum-$minnum+1);
        }
    }
    //num1:1つ前の運用番号,後に補正
    $num1 = $keynum - 1;
    $num2 = $keynum - 2;
    while ( $num1 < $minnum ) {
        $num1 = $num1 + ($maxnum-$minnum+1);
    }
    while ( $num2 < $minnum ) {
        $num2 = $num2 + ($maxnum-$minnum+1);
    }
    $unyonum = array($num2,$num1,$keynum);
    //date[0]:2日前,date[2]:最新日
    date_default_timezone_set('Indian/Cocos');
    $date = array(date('Y-m-d',strtotime('-2 day')),date('Y-m-d',strtotime('-1 day')),date('Y-m-d'));
    //data参照
    $data = null;
    $sql = "SELECT * FROM data WHERE (`date`='".$date[0]."' AND `unyoid`='".$id."' AND `unyonum`='".$unyonum[0]."') OR (`date`='".$date[1]."' AND `unyoid`='".$id."' AND `unyonum`='".$unyonum[1]."') OR (`date`='".$date[2]."' AND `unyoid`='".$id."' AND `unyonum`='".$unyonum[2]."')";
    $stmt = $pdo_data->prepare($sql);
    $stmt->execute();
    while ( $db = $stmt->fetch(PDO::FETCH_ASSOC) ) {
        if ( ($data[$db['date']][$db['unyoid']][$db['unyonum']] == null) || ($data[$db['date']][$db['unyoid']][$db['unyonum']] && $data[$db['date']][$db['unyoid']][$db['unyonum']]['unyoop'] < $db['unyoop']) ) {
            $data[$db['date']][$db['unyoid']][$db['unyonum']] = $db;
        }
    }
    //最新日のデータから順に参照
    if ( $data[$date[2]][$id][$unyonum[2]] ) {
        $yosoku = array(
            'T',
            $data[$date[2]][$id][$unyonum[2]]['master'],
            $data[$date[2]][$id][$unyonum[2]]['train'],
            $data[$date[2]][$id][$unyonum[2]]['unk'],
            $data[$date[2]][$id][$unyonum[2]]['dai']
        );
    } elseif ( $data[$date[1]][$id][$unyonum[1]] ) {
        $yosoku = array(
            'F',
            $data[$date[1]][$id][$unyonum[1]]['master'],
            $data[$date[1]][$id][$unyonum[1]]['train'],
            $data[$date[1]][$id][$unyonum[1]]['unk'],
            $data[$date[1]][$id][$unyonum[1]]['dai']
        );
    } elseif ( $data[$date[0]][$id][$unyonum[0]] ) {
        $yosoku = array(
            'F',
            $data[$date[0]][$id][$unyonum[0]]['master'],
            $data[$date[0]][$id][$unyonum[0]]['train'],
            $data[$date[0]][$id][$unyonum[0]]['unk'],
            $data[$date[0]][$id][$unyonum[0]]['dai']
        );
    } else {
        $yosoku = null;
    }
    return $yosoku;
}

//編成to運用番号 予測関数nyosoku_train
//入力 : master, train, datekey
    //master : 'ヒネ', 'キト', ...
    //train : 'HE401', 'HE402', ...
    //datekey : 当日...0, 1日後...1, 2日後...2
//出力 : TF, 運用id, 運用num, unknown, daiso
    //[0]TF : T...当日情報を使用, F...前日までの情報を使用
    //[1]運用id : '日支', '京支', ...
    //[2]運用num : 121, 122, ...
    //[3]unknown : 不確定フラグ 1...有効, 0...無効
    //[4]daiso : 代走フラグ 1...有効, 0...無効
function nyosoku_train($master,$train,$key) {
    $pdo_data = 'DBkey';
    //date[0]:2日前,date[2]:最新日
    date_default_timezone_set('Indian/Cocos');
    $date = array(date('Y-m-d',strtotime('-2 day')),date('Y-m-d',strtotime('-1 day')),date('Y-m-d'));
    //data参照
    $data = null;
    $sql = "SELECT * FROM data WHERE `master`='".$master."' AND `train`='".$train."' AND `date`>'".date('Y-m-d',strtotime('-3 day'))."'";
    $stmt = $pdo_data->prepare($sql);
    $stmt->execute();
    while ( $db = $stmt->fetch(PDO::FETCH_ASSOC) ) {
        if ( ($data[$db['date']] == null) || ($data[$db['date']] && $data[$db['date']]['unyoop'] < $db['unyoop']) ) {
            $data[$db['date']] = $db;
        }
    }
    //最新日のデータから順に参照
    if ( $data[$date[2]] ) {
        $yosoku = array(
            'T',
            $data[$date[2]]['unyoid'],
            $data[$date[2]]['unyonum'],
            $data[$date[2]]['unk'],
            $data[$date[2]]['dai']
        );
        $num = $data[$date[2]]['unyonum'];
    } elseif ( $data[$date[1]] ) {
        $yosoku = array(
            'F',
            $data[$date[1]]['unyoid'],
            $data[$date[1]]['unyonum']+1,
            $data[$date[1]]['unk'],
            $data[$date[1]]['dai']
        );
        $num = $data[$date[1]]['unyonum'];
    } elseif ( $data[$date[0]] ) {
        $yosoku = array(
            'F',
            $data[$date[0]]['unyoid'],
            $data[$date[0]]['unyonum']+2,
            $data[$date[0]]['unk'],
            $data[$date[0]]['dai']
        );
        $num = $data[$date[0]]['unyonum'];
    }
    //unyoindex/運用リストを参照,当該運用番号の最大最小値を取得
    $stmt = $pdo_data->prepare('SELECT * FROM unyo_index');
    $stmt->execute();
    while ( $db = $stmt->fetch(PDO::FETCH_ASSOC) ) {
        if ( $db['unyoid'] == $yosoku[1] && $db['min'] <= $num && $db['max'] >= $num ) {
            $minnum = $db['min'];
            $maxnum = $db['max'];
        }
    }
    //keyだけ運用番号を進める,後に補正
    if ( $key == 2 ) {
        $yosoku[2] += 2;
    } elseif ( $key == 1 ) {
        $yosoku[2] += 1;
    }
    while ( $yosoku[2] > $maxnum ) {
        $yosoku[2] = $yosoku[2] - ($maxnum-$minnum+1);
    }
    //運用番号から最新編成を検索,入力された編成と異なる場合はnullを返す
    $yn = nyosoku_unyo($yosoku[1],$yosoku[2],$key);
    if ( $yn[1] == $master && $yn[2] == $train ) {
        return $yosoku;
    } else {
        return null;
    }
}

//運用番号to編成 予測関数nnyosoku_unyo
//入力 : 運用id, 運用num, datekey, unyodata, unyoindex
    //運用id : '日支', '京支', ...
    //運用num : 121, 122, ...
    //datekey : 当日...0, 1日後...1, 2日後...2
    //unyodata : unyodata()をコール
    //unyoindex : unyoindex()をコール
//出力 : TF, master, train, unknown, daiso
    //[0]TF : T...当日情報を使用, F...前日までの情報を使用
    //[1]master : 'ヒネ', 'キト', ...
    //[2]train : 'HE401', 'HE402', ...
    //[3]unknown : 不確定フラグ 1...有効, 0...無効
    //[4]daiso : 代走フラグ 1...有効, 0...無効
//大量リクエストをする場合向け
//$unyoindex = unyoyosoku_index();, $unyodata = unyoyosoku_data();を事前にコール
function unyoyosoku_index() {
    $pdo_data = 'DBkey';
    $stmt = $pdo_data->prepare('SELECT * FROM unyo_index');
    $stmt->execute();
    while ( $db = $stmt->fetch(PDO::FETCH_ASSOC) ) {
        $unyoindex[] = $db;
    }
    return $unyoindex;
}

function unyoyosoku_data() {
    $pdo_data = 'DBkey';
    $unyodata = null;
    date_default_timezone_set('Indian/Cocos');
    $sql = "SELECT * FROM data WHERE `date`>'".date('Y-m-d',strtotime('-3 day'))."'";
    $stmt = $pdo_data->prepare($sql);
    $stmt->execute();
    while ( $db = $stmt->fetch(PDO::FETCH_ASSOC) ) {
        if ( ($unyodata[$db['date']][$db['unyoid']][$db['unyonum']] == null) || ($unyodata[$db['date']][$db['unyoid']][$db['unyonum']] && $unyodata[$db['date']][$db['unyoid']][$db['unyonum']]['unyoop'] < $db['unyoop']) ) {
            $unyodata[$db['date']][$db['unyoid']][$db['unyonum']] = $db;
        }
    }
    return $unyodata;
}

function nnyosoku_unyo($id,$num,$key,$unyodata,$unyoindex) {
    $minnum = null;
    $maxnum = null;
    //unyoindex/運用リストを参照,当該運用番号の最大最小値を取得
    for ( $i = 0; $unyoindex[$i]; $i++ ) {
        if ( $unyoindex[$i]['unyoid'] == $id && $unyoindex[$i]['min'] <= $num && $unyoindex[$i]['max'] >= $num ) {
            $minnum = $unyoindex[$i]['min'];
            $maxnum = $unyoindex[$i]['max'];
            break;
        }
    }
    //範囲外の運用番号を入力された場合,nullを返す
    if ( $minnum == null || $maxnum == null ) {
        return null;
    }
    //運用データを参照する際に基準となる運用番号を算出/key=+2なら(最新日/2つ前の運用番号)からデータを参照する
    $keynum = $num;
    if ( $key == 2 ) {
        $keynum = $keynum - 2;
        while ( $keynum < $minnum ) {
            $keynum = $keynum + ($maxnum-$minnum+1);
        }
    } elseif ( $key == 1 ) {
        $keynum = $keynum - 1;
        while ( $keynum < $minnum ) {
            $keynum = $keynum + ($maxnum-$minnum+1);
        }
    }
    //num1:1つ前の運用番号,後に補正
    $num1 = $keynum - 1;
    $num2 = $keynum - 2;
    while ( $num1 < $minnum ) {
        $num1 = $num1 + ($maxnum-$minnum+1);
    }
    while ( $num2 < $minnum ) {
        $num2 = $num2 + ($maxnum-$minnum+1);
    }
    $unyonum = array($num2,$num1,$keynum);
    //date[0]:2日前,date[2]:最新日
    date_default_timezone_set('Indian/Cocos');
    $date = array(date('Y-m-d',strtotime('-2 day')),date('Y-m-d',strtotime('-1 day')),date('Y-m-d'));
    //最新日のデータから順に参照
    if ( $unyodata[$date[2]][$id][$unyonum[2]] ) {
        $yosoku = array(
            'T',
            $unyodata[$date[2]][$id][$unyonum[2]]['master'],
            $unyodata[$date[2]][$id][$unyonum[2]]['train'],
            $unyodata[$date[2]][$id][$unyonum[2]]['unk'],
            $unyodata[$date[2]][$id][$unyonum[2]]['dai']
        );
    } elseif ( $unyodata[$date[1]][$id][$unyonum[1]] ) {
        $yosoku = array(
            'F',
            $unyodata[$date[1]][$id][$unyonum[1]]['master'],
            $unyodata[$date[1]][$id][$unyonum[1]]['train'],
            $unyodata[$date[1]][$id][$unyonum[1]]['unk'],
            $unyodata[$date[1]][$id][$unyonum[1]]['dai']
        );
    } elseif ( $unyodata[$date[0]][$id][$unyonum[0]] ) {
        $yosoku = array(
            'F',
            $unyodata[$date[0]][$id][$unyonum[0]]['master'],
            $unyodata[$date[0]][$id][$unyonum[0]]['train'],
            $unyodata[$date[0]][$id][$unyonum[0]]['unk'],
            $unyodata[$date[0]][$id][$unyonum[0]]['dai']
        );
    } else {
        $yosoku = null;
    }
    return $yosoku;
}
?>