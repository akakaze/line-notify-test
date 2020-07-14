<?php
/**
 * Akakaze Bot
 * PHP version 7
 * 
 * @package LINENotify
 * @author Akakaze <akakazebot@gmail.com>
 * @copyright 2018 Akakaze
 */
namespace AkakazeBot;

use ErrorException;
use Exception;

/**
 * Class LINENotify
 *
 * @package AkakazeBot
 */
class LINENotify 
{
  private $client_id;
  private $client_secret;
  private $redirect_uri;

  /**
   * build instance
   *
   * @param array $lineNotifyInfo LINE Notify client info. Include:
   * - client_id
   * - client_secret
   * - redirect_uri
   * 
   * @return $this
   */
  public function __construct(array $lineNotifyInfo) {
    $this->client_id = $lineNotifyInfo["client_id"];
    $this->client_secret = $lineNotifyInfo["client_secret"];
    $this->redirect_uri = $lineNotifyInfo["redirect_uri"];
  }

  /**
   * GET https://notify-bot.line.me/oauth/authorize
   * 
   * The following is the OAuth2 authorization endpoint URI.
   *
   * @param string $state Assigns a token that can be used for responding to CSRF attacks.
   *
   * @return string authorize url
   */
  public function getOuthAuthorize(string $state) : string
  {
    $data = [
      "response_type" => "code",
      "client_id" => $this->client_id,
      "redirect_uri" => $this->redirect_uri,
      "scope" => "notify",
      "state" => $state,
      "response_mode" => "form_post",
    ];
    $url = "https://notify-bot.line.me/oauth/authorize?" . http_build_query($data);

    return $url;
  }

  /**
   * POST https://notify-bot.line.me/oauth/token
   * 
   * The OAuth2 token endpoint.
   *
   * @param string $code Assigns a code parameter value generated during redirection.
   *
   * @return object The response body is a JSON object type.
   */
  public function postOuthToken(string $code) : object
  {
    $uri = "https://notify-bot.line.me/oauth/token";
    $header = [
      "Content-Type: application/x-www-form-urlencoded"
    ];
    $parameters = [
      "grant_type" => "authorization_code",
      "code" => $code,
      "redirect_uri" => $this->redirect_uri,
      "client_id" => $this->client_id,
      "client_secret" => $this->client_secret
    ];
    $opt = [
      CURLOPT_POST => true,
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_HTTPHEADER => $header,
      CURLOPT_POSTFIELDS => http_build_query($parameters)
    ];

    return $this->curlFormat($uri, $opt);
  }

  /**
   * POST https://notify-api.line.me/api/notify
   * 
   * Sends notifications to users or groups that are related to an access token.  
   * If this API receives a status code 401 when called, the access token will be deactivated on LINE Notify (disabled by the user in most cases). Connected services will also delete the connection information.  
   * Requests use POST method with application/x-www-form-urlencoded (Identical to the default HTML form transfer type).
   * 
   * @param string $token
   * @param array $parameters
   *
   * @return object The response body is a JSON object type.
   */
  public function postApiNotify(string $token, array $parameters) : object
  {
    $uri = "https://notify-api.line.me/api/notify";
    $header = [
      "Content-Type: application/x-www-form-urlencoded",
      "Authorization: Bearer {$token}"
    ];
    $opt = [
      CURLOPT_POST => true,
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_HTTPHEADER => $header,
      CURLOPT_POSTFIELDS => http_build_query($parameters)
    ];

    return $this->curlFormat($uri, $opt);
  }

  /**
   * GET https://notify-api.line.me/api/status
   * 
   * An API for checking connection status. You can use this API to check the validity of an access token. Acquires the names of related users or groups if acquiring them is possible.  
   * On the connected service side, it's used to see which groups are configured with a notification and which user the notifications will be sent to. There is no need to check the status with this API before calling /api/notify or /api/revoke.  
   * If this API receives a status code 401 when called, the access token will be deactivated on LINE Notify (disabled by the user in most cases). Connected services will also delete the connection information.
   *
   * @param string $token
   *
   * @return object The response body is a JSON object type.
   */
  public function getApiStatus(string $token) : object
  {
    $uri = "https://notify-api.line.me/api/status";
    $header = [
      "Content-Type: application/x-www-form-urlencoded",
      "Authorization: Bearer {$token}"
    ];
    $opt = [
      CURLOPT_HTTPGET => true,
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_HTTPHEADER => $header
    ];

    return $this->curlFormat($uri, $opt);
  }

  /**
   * POST https://notify-api.line.me/api/revoke
   * 
   * An API used on the connected service side to revoke notification configurations. Using this API will revoke all used access tokens, disabling the access tokens from accessing the API.
   * The revocation process on the connected service side is as follows
   * Call /api/revoke
   * 1. If step 1 returns status code 200, the request is accepted, revoking all access tokens and ending the process
   * 2. If step 1 returns status code 401, the access tokens have already been revoked and the connection will be d
   * 3. If step 1 returns any other status code, the process will end (you can try again at a later time)
   *
   * @param string $token
   *
   * @return object The response body is a JSON object type.
   */
  public function postApiRevoke(string $token) : object
  {
    $uri = "https://notify-api.line.me/api/revoke";
    $header = [
      "Content-Type: application/x-www-form-urlencoded",
      "Authorization: Bearer {$token}"
    ];
    $opt = [
      CURLOPT_POST => true,
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_HTTPHEADER => $header
    ];

    return $this->curlFormat($uri, $opt);
  }

  private function curlFormat(string $uri, array $opt) : object
  {
    $ch = curl_init($uri);
    curl_setopt_array($ch, $opt);
    $result = curl_exec($ch);
    
    $curl_errno = curl_errno($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close ($ch);

    if ($curl_errno !== 0) {
      throw new ErrorException("Error number for cURL: {$curl_errno}");
    }

    if ($this->isResponseStatus200($httpCode)) {
      // Content-Type: application/json
      return json_decode($result);
    }
  }

  private function isResponseStatus200(int $httpCode) : bool
  {
    switch ($httpCode) {
      case 200:
        return true;
      break;

      case 400:
        throw new Exception("Bad request.", $httpCode);
      break;

      case 401:
        throw new Exception("Invalid access token.", $httpCode);
      break;

      case 500:
        throw new Exception("Failure due to server error.", $httpCode);
      break;

      default:
        throw new Exception("Processed over time or stopped.", $httpCode);
      break;
    }
    return false;
  }
}
