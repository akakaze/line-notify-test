<?php session_start();

use AkakazeBot\DBClass;
use AkakazeBot\LINENotify;
use Dotenv\Dotenv;
use Slim\App;
use Slim\Http\Request;
use Slim\Http\Response;

require "vendor/autoload.php";

const NOTIFY_STATE = "LINE Notify state";
const NOTIFY_NAME = "LINE Notify name";
const NOTIFY_CLASS = "LINE Notify class";

$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();
$lineNotifyInfo = [
  "client_id" => $_ENV["LINE_NOTIFY_CLIENT_ID"],
  "client_secret" => $_ENV["LINE_NOTIFY_CLIENT_SECRET"],
  "redirect_uri" => $_ENV["LINE_NOTIFY_CLIENT_REDIRECT_URI"],
];

$app = new App();

//模擬登錄
$app->post("/simulation-login", function (Request $req,  Response $res, $args = []) {
  $n = $req->getParsedBodyParam("name");
  $c = $req->getParsedBodyParam("class");
  if ($n !== null && $c !== null) {
    $_SESSION[NOTIFY_NAME] = $n;
    $_SESSION[NOTIFY_CLASS] = $c;
    return $res->withHeader("Location", "../front-end/sub-and-notify");
  }
});

//模擬登出
$app->get("/simulation-logout", function (Request $req,  Response $res, $args = []) {
  unset($_SESSION[NOTIFY_NAME]);
  unset($_SESSION[NOTIFY_CLASS]);

  return $res->withHeader("Location", "../front-end/");
});

//GET https://notify-bot.line.me/oauth/authorize
$app->get("/authorize", function (Request $req,  Response $res, $args = []) {
  if (isset($_SESSION[NOTIFY_NAME]) && isset($_SESSION[NOTIFY_CLASS])) {
    if (!isset($_SESSION[NOTIFY_STATE])) {
      $_SESSION[NOTIFY_STATE] = hash("sha256", session_id());
    }
    global $lineNotifyInfo;
    $ln = new LINENotify($lineNotifyInfo);
    $url = $ln->getOuthAuthorize($_SESSION[NOTIFY_STATE]);
    
    return $res->write($url);
  }
  return $res->withHeader("Location", "../front-end/");
});

//POST https://notify-bot.line.me/oauth/token
$app->post("/oauth2-callback", function (Request $req,  Response $res, $args = []) {
  $state = $req->getParsedBodyParam("state");
  //驗證 state 防止 Cross-site request forgery
  if ($state !== $_SESSION[NOTIFY_STATE]) {
    //state mismatch 
    return $res->write("WRONG STATE {$state} VS {$_SESSION[NOTIFY_STATE]}");
  }
  $error = $req->getParsedBodyParam("error");
  if ($error !== null) {
    /*
    * When there is a failure, redirects to the assigned reirect_uri with the parameters below attached.
    *
    * error               string  Assigns error codes defined by OAuth2 https://tools.ietf.org/html/rfc6749#section-4.1.2
    * state               string  Directly send the assigned state parameter
    * error_description   string	An optional huma-readable text providing additional information, used to assist the client developer in understanding the error that occurred.
    */
    return $res->write("ERROR: {$error}");
  }

  //POST 取得 Access token
  global $lineNotifyInfo;
  $ln = new LINENotify($lineNotifyInfo);
  $code = $req->getParsedBodyParam("code");
  try {
    $result = $ln->postOuthToken($code);
  } catch (\Throwable $th) {
    return $res->write($th->getMessage());
  }
  
  //將 Access token 寫入 DB
  $db = DBClass::getDB();
  $insert = $db->insertAccessToken([
    "name" => $_SESSION[NOTIFY_NAME],
    "class" => $_SESSION[NOTIFY_CLASS],
    "access_token" => $result->access_token,
  ]);
  if ($insert) {
    unset($_SESSION[NOTIFY_STATE]);
  }
  
  return $res->withHeader("Location", "../front-end/sub-and-notify");
});

//GET https://notify-api.line.me/api/status
$app->get("/is-sub", function (Request $req,  Response $res, $args = []) {
  $sub = [];

  //DB 確認有沒有 Access token
  $db = DBClass::getDB();
  $rows = $db->getAccessTokenRows([
    "name" => $_SESSION[NOTIFY_NAME],
    "class" => $_SESSION[NOTIFY_CLASS]
  ]);
  if (empty($rows)) {
    $sub["is-sub"] = false;
  }
  else {
    $token = $rows[0]["access_token"];

    //GET 取得狀態，200為正常訂閱狀態
    global $lineNotifyInfo;
    $ln = new LINENotify($lineNotifyInfo);
    try {
      $result = $ln->getApiStatus($token);
    } catch (\Throwable $th) {
      $sub["is-sub"] = false;
    }
    if ($result->status === 200) {
      $sub["is-sub"] = true;
    }
  }

  return $res->write(json_encode($sub));
});

//POST https://notify-api.line.me/api/notify
$app->post("/notify", function (Request $req,  Response $res, $args = []) {
  $class = $req->getParsedBodyParam("class");
  $message = $req->getParsedBodyParam("message");
  
  //DB 找到要發的 Access token
  $db = DBClass::getDB();
  $rows = $db->getAccessTokenRows([
    "class" => $class
  ]);
  $ret = [];
  foreach ($rows as $row) {
    $token = $row['access_token'];
    $parameters = [
      "message" => $message,
      // "imageThumbnail" => "",
      // "imageFullsize" => "",
      // "imageFile" => "",
      // "stickerPackageId" => "",
      // "stickerId" => "",
      // "notificationDisabled" => "",
    ];

    //POST 進行通知
    global $lineNotifyInfo;
    $ln = new LINENotify($lineNotifyInfo);
    try {
      $result = $ln->postApiNotify($token, $parameters);
    } catch (\Throwable $th) {
      return $res->write($th->getMessage()."/ token: {$token}");
    }

    $ret[] = json_decode($result);
  }
  
  return $res->write(json_encode($ret));
});

//POST https://notify-api.line.me/api/revoke
$app->get("/revoke", function (Request $req,  Response $res, $args = []) {
  //DB 找到 Access token
  $db = DBClass::getDB();
  $rows = $db->getAccessTokenRows([
    "name" => $_SESSION[NOTIFY_NAME],
    "class" => $_SESSION[NOTIFY_CLASS],
  ]);
  
  //POST 解除訂閱
  global $lineNotifyInfo;
  $ln = new LINENotify($lineNotifyInfo);
  $token = $rows[0]["access_token"];
  try {
    $ln->postApiRevoke($token);
  } catch (\Throwable $th) {
    return $res->write($th->getMessage());
  }
  
  //DB 刪除 Access token
  $delete = $db->deleteAccessToken([
    "name" => $_SESSION[NOTIFY_NAME],
    "class" => $_SESSION[NOTIFY_CLASS],
  ]);

  return $res->withHeader("Location", "../front-end/sub-and-notify");
});

$app->run();
?>