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

use Jajo\JSONDB;

/**
 * Class DBClass
 *
 * @package AkakazeBot
 */
class DBClass 
{
  const DB_PATH = __DIR__.DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."db";
  const DB_FILE = "notify.json";

  private static $_db = null;
  private $db;
  private function __construct() {
    $this->db = new JSONDB(self::DB_PATH);
  }

  /**
   * DB singleton
   *
   * @return DBClass
   */
  public static function getDB() : DBClass
  {
    if (is_null(self::$_db)) {
      self::$_db = new self();
    }
    return self::$_db;
  }

  /**
   * Insert date
   *
   * @param array $data
   *
   * @return bool
   */
  public function insertAccessToken(array $data) : bool
  {
    try {
      $this->db->insert(self::DB_FILE, $data);
    } catch (\Throwable $th) {
      return false;
    }

    return true;
  }

  /**
   * Get Access Tokens
   *
   * @param array $where
   *
   * @return array
   */
  public function getAccessTokenRows(array $where) : array
  {
    $rows = $this->db->select("access_token")
    ->from(self::DB_FILE)
    ->where($where)
    ->get();

    return $rows;
  }

  public function deleteAccessToken(array $where)
  {
    try {
      $this->db->delete()
      ->from("notify.json")
      ->where([
        "name" => $_SESSION[NOTIFY_NAME],
        "class" => $_SESSION[NOTIFY_CLASS],
      ])
      ->trigger();
    } catch (\Throwable $th) {
      return false;
    }
    
    return true;
  }
}
