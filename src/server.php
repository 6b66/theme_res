<?php
include_once(      "makedata.php");//mask, unmask, Handshake
include_once("authentication.php");//ユーザ認証関数群

$host = 'localhost';
$port = '9000';
$null = NULL;


$socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
socket_set_option($socket, SOL_SOCKET, SO_REUSEADDR, 1);

socket_bind($socket, 0, $port);
socket_listen($socket);

$clients = array($socket);

$UserData = array();//ユーザのデータが入る連想配列(keyはユーザ名)
$NowUsers = array();//ログインしているユーザ管理の連想配列
$Stay     = array();//ゲーム待機配列
$NowGame  = array();//ゲーム中のユーザ

while(true) {

  $changed = $clients;

//  socket_select($changed, $null, $null, 0, 10);
  socket_select($changed, $null, $null, $null);

  if(in_array($socket, $changed)) {
    //echo "new menber\n";
    $socket_new = socket_accept($socket);
    $clients[] = $socket_new;

    $header = socket_read($socket_new, 1024);
    //print_r($header);
    $found_socket = array_search($socket, $changed);
		unset($changed[$found_socket]);
    make_handshaking($header, $host, $port, $socket_new);
  }

  foreach ($changed as $changed_socket) {
    //print_r(socket_recv($changed_socket, $buf, 1024, 0) . "\n");
    //$byte = @socket_recv($changed_socket, $buf, 1024, 0);
    while(socket_recv($changed_socket, $buf, 1024, 0) >= 1) {
      $Data = json_decode(unmask($buf), true);
      if($Data == $null) { break; }

      $UserFilePath = "./users/".$Data["name"].".json";

      if($Data["mode"] == "new") {//新規ログインの場合

        new_login($Data, $UserFilePath, $changed_socket);

      }else if($Data["mode"] == "login") {//通常ログインの場合

        set_menber($Data, $UserFilePath, $changed_socket);

      }else if($Data["mode"] == "logout") {//ログアウト処理

        unset_menber($Data["name"]);

      }else if($Data["mode"] == "match" && $NowUsers[$Data["name"]] != $null) {//マッチの場合

        matching($Data, $changed_socket);

      }

      send_message( mask(unmask($buf)) , $changed_socket );
      break 2;
    }

    $buf = @socket_read($changed_socket, 1024, PHP_NORMAL_READ);
		if ($buf === false) {

      if(isset($NowUsers)) {
        foreach($NowUsers as $user) {
          if($user["socket"] == $changed_socket) {
            unset_menber($user["name"]);
          }
        }
      }

			$found_socket = array_search($changed_socket, $clients);
			//socket_getpeername($changed_socket, $ip);
			unset($clients[$found_socket]);
		}
  }

}

function matching($data, $changed_socket) {

  global $NowGame;
  global $Stay;

  print_log("match :".$data["name"]);

  $flag = true;//マッチングするとfalse
  foreach ($Stay as $key => $stay) {
    if($stay["room"] == $data["room"]) {

      $NowGame[] = [
        "player1" => [ "name" => $stay["name"],  "socket" => $stay["socket"] ],
        "player2" => [ "name" => $data["name"],  "socket" => $changed_socket ]
      ];

      print_log("matched:".$stay["name"]." & ".$data["name"]);

      unset($Stay[$key]);
      //echo "\nマッチング\n";
      //print_r($NowGame);
      $flag = false;
      break;
    }
  }
  if($flag) {//マッチングしなければ待機列に入れる
    $Stay[] = [ "name"   => $data["name"], "room"   => $data["room"], "socket" => $changed_socket ];
    send_info("matchInfo", "マッチング中です...", $changed_socket);
  }
  //echo "\n待機列\n";
  //print_r($Stay);
}
function print_log($msg) {
  echo "[".date("m").":".date("d").":".date("h").":".date("i").":".date("s")."]".$msg."\n";
}
function set_menber($data, $UserFilePath, $changed_socket) {

  global $NowUsers;
  global $UserData;

  $user = login($data, $UserFilePath, $NowUsers, $changed_socket);
  if($user !== false) {

    $UserData += [ $user["name"] => $user ];
    $NowUsers += [ $user["name"] => [ "name" => $user["name"], "socket" => $changed_socket] ];

    print_log("login :".$data["name"]);
  }
}
function unset_menber($username) {

  global $NowUsers;
  global $UserData;

  logout($UserData[$username], "./users/".$username.".json");
  unset( $NowUsers[$username]);
  unset( $UserData[$username]);
  delete_Stay($username);
  print_log("logout :".$username);
}
function send_message($msg, $to_socket) {

	@socket_write($to_socket,$msg,strlen($msg));

	return true;
}
function delete_Stay($name) {
  global $Stay;
  foreach($Stay as $key => $stay) {
    if($stay["name"] == $name) {
      //echo "待機列から削除\n";
      unset($Stay[$key]);
    }
  }
  //print_r($Stay);
}
function send_info($mode, $msg, $socket) {
  $msgjson = array(
    "mode" => $mode,
    "msg"  => $msg
  );
  $msgjson = json_encode($msgjson);
  send_message(mask($msgjson), $socket);
}

?>
