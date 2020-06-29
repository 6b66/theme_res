<?php
function new_login($Data, $UserFilePath, $changed_socket) {
  if(check_name($Data["name"])) {//使用できない文字が含まれていない
    if(!(file_exists($UserFilePath))) {//ファイルが存在していない

      //新規jsonの作成
      $ary = array(
        "name"     => $Data["name"],
        "id"       => $Data["id"],
        "count"    => 0,
        "wincount" => 0
      );
      $ary = json_encode($ary);
      file_put_contents($UserFilePath , $ary);
      send_info("loginInfo", "登録しました。ログインしてください。", $changed_socket);
      print_log("new_login :".$Data["name"]);
    }else {
      send_info("loginInfo", "すでに使用されているユーザー名です。", $changed_socket);
    }
  }else {
    send_info("loginInfo", "使用できない文字が含まれています。", $changed_socket);
  }
}

/*ログインに成功したらユーザのデータを返す。失敗したらfalseを返す*/
function login($Data, $UserFilePath, $NowUsers, $changed_socket) {
  if(!isset($NowUsers[$Data["name"]])) {
    if(file_exists($UserFilePath)) {
      $user = json_decode(file_get_contents($UserFilePath), true);
      if($user["id"] == $Data["id"]) {

        $ary = array(
          "mode" => "loginsuccess",
          "name" => $Data["name"]
        );

        send_message(mask(json_encode($ary)), $changed_socket);
        return $user;
      }else {
        send_info("loginInfo", "IDが間違っています。", $changed_socket);
      }
    }else {
      send_info("loginInfo", "ユーザー名が間違っています。", $changed_socket);
    }
  }else { send_info("loginInfo", "すでにログインしています。", $changed_socket); }
  return false;
}

function logout($user_data, $UserFilePath) {
  file_put_contents($UserFilePath, json_encode($user_data));
}

function check_name($name) {
  $flag = 1;
  //￥ ／ ： ＊ ？ ” ＜ ＞ ｜  <-使用不可文字
  $no = Array("\\", "/", ":", "*", "?", "\"", "<", ">", "|");
  for($i = 0;$i < 9;$i++) {
    if(strpos($name, $no[$i]) !== false) {
      $flag = 0;
      break;
    }
  }
  return $flag;
}
?>
