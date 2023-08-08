<?php if(!defined('BASEPATH')) {
  exit('No direct script access allowed');
}

class Redis_demo extends CI_Controller {

  function __construct() {
    parent::__construct();

    try {
      $this->redis = new Predis\Client([
                                           'scheme' => $this->config->item('AWS_elasticache_scheme'),
                                           'host'   => $this->config->item('AWS_elasticache_endpoint'),
                                           'port'   => 6379,
                                       ]);
    } catch(Exception $ex) {
      echo $ex->getMessage();
    }
  }

  public function index() {
    echo "Testing ElastiCache...<br><br>";
  }

  public function select_credits() {
    throw new \Exception('General fail');
    /**
     * Let's assume select users data from 'Users' table
     * First, check if the data exists in Redis or not.
     * If it exists, get data from Redis. If not, get the data from DB and store it in Redis for the future.
     *
     * The table data stored in the Redis with the combination of one List and one Hashes.
     * List has the row id or primary key of the table.
     * Hashes has a row data and key is the combination of table name and either the row id or the primary key.
     *
     * Key description:
     *   [table name] - Key of List type stores the row id or primary key for specified table
     *   [table name]:[row id/primary key] - Hash key for one row data of specified table stored in Hashes type
     */

    $tbl_name = 'CreditsTest';

    $row_keys = [];
    $creditsStatic = [];
    $credits = [];

    // Get the list of row ids for 'Users' table in the Redis
    $count = 1000;
    $currCount = 1;
    while($currCount <= $count) {
      $row_keys[] = $currCount;
      $creditsStatic[$currCount] = [
          "id"                     => $currCount,
          "first_name"             => "Karl",
          "last_name"              => "Stundo",
          "aaaaaaaaaaaa"           => "test",
          "aaaaaaaaaaaa1"          => "test",
          "aaaaaaaaaaaa2"          => "test",
          "aaaaaaaaaaaa3"          => "test",
          "aaaaaaaaaaaa4"          => "test",
          "aaaaaaaaaaaa5"          => "test",
          "aaaaaaaaaaaa6"          => "test",
          "aaaaaaaaaaaa7"          => "test",
          "aaaaaaaaaaaa8"          => "test",
          "aaaaaaaaaaaa9"          => "test",
          "aaaaaaaaaaaa0"          => "test",
          "aaaaaaaaaaaa11"         => "test",
          "aaaaaaaaaaaa21"         => "test",
          "aaaaaaaaaaaa31"         => "test",
          "aaaaaaaaaaaa41"         => "test",
          "aaaaaaaaaaaa51"         => "test",
          "aaaaaaaaaaaa61"         => "test",
          "aaaaaaaaaaaa71"         => "test",
          "aaaaaaaaaaaa81"         => "test",
          "aaaaaaaaaaaa91"         => "test",
          "aaaaaaaaaaaa012"        => "test",
          "aaaaaaaaaaaa112"        => "test",
          "aaaaaaaaaaaa212"        => "test",
          "aaaaaaaaaaaa312"        => "test",
          "aaaaaaaaaaaa412"        => "test",
          "aaaaaaaaaaaa512"        => "test",
          "aaaaaaaaaaaa612"        => "test",
          "aaaaaaaaaaaa712"        => "test",
          "aaaaaaaaaaaa812"        => "test",
          "aaaaaaaaaaaa912"        => "test",
          "aaaaaaaaaaaa013"        => "test",
          "aaaaaaaaaaaa113"        => "test",
          "aaaaaaaaaaaa213"        => "test",
          "aaaaaaaaaaaa313"        => "test",
          "aaaaaaaaaaaa413"        => "test",
          "aaaaaaaaaaaa513"        => "test",
          "aaaaaaaaaaaa613"        => "test",
          "aaaaaaaaaaaa713"        => "test",
          "aaaaaaaaaaaa813"        => "test",
          "aaaaaaaaaaaa9331"       => "test",
          "aaaaaaaaaaaa013"        => "test",
          "aaaaaaaaaaaa114"        => "test",
          "aaaaaaaaaaaa214"        => "test",
          "aaaaaaaaaaaa314"        => "test",
          "aaaaaaaaaaaa414"        => "test",
          "aaaaaaaaaaaa514"        => "test",
          "aaaaaaaaaaaa641"        => "test",
          "aaaaaaaaaaaa741"        => "test",
          "aaaaaaaaaaaa814"        => "test",
          "aaaaaaaaaaaa941"        => "test",
          "aaaaaaaaaaaa041"        => "test",
          "aaaaaaaaaaaa114"        => "test",
          "aaaaaaaaaaaa414aa21"    => "test",
          "aaaaaaaaaaaa31"         => "test",
          "aaaaaaa4aaaaa41"        => "test",
          "aaaaaaaaaaaa514"        => "test",
          "aaaaaaaa4aaaa61"        => "test",
          "aaaaaaaaaaaa714"        => "test",
          "aaaaaaaaaaaa841"        => "test",
          "aaaaaaaaaaaa914"        => "test",
          "aaaaaa4aaaaaa01"        => "test",
          "aaaaaa6aaaaaa11"        => "test",
          "aaaaaaa6aaaa91aaaaaa21" => "test",
          "aaaaaa6aaaaaa31"        => "test",
          "aaaaaaa6aaaaa41"        => "test",
          "aaaaaaa6aaaaa51"        => "test",
          "aaaaaaa6aaaaa61"        => "test",
          "aaaaaaaa6aaaa71"        => "test",
          "aaaaaaaa6aaaa81"        => "test",
          "aaaaaaa6aaaa91"         => "test",
          "aaaaaaaa6aaaa01"        => "test",
          "aaaaaaa7aaaaa11"        => "test",
          "aaaaaaa7aaaaa21"        => "test",
          "aaaaaaa7aaaaa31"        => "test",
          "aaaaaaa7aaaaa41"        => "test",
          "aaaaaaa7aaaaa51"        => "test",
          "aaaaaaa77aaaaa61"       => "test",
          "aaaaaaa7aaaaa71"        => "test",
          "aaaaaaa7aaaaa81"        => "test",
          "aaaaaaa7aaaaa91"        => "test",
          "aaaaaaa7aaaaa01"        => "test",
          "aaaaaaa9aaaaa11"        => "test",
          "aaaaaa9aaaaaa21"        => "test",
          "aaaaaa9aaaaaa31"        => "test",
          "aaaaaa9aaaaaa41"        => "test",
          "aaaaaa9aaaaaa51"        => "test",
          "aaaaaa9aaaaaa61"        => "test",
          "aaaaaaa9aaaaa71"        => "test",
          "aaaaaa9aaaaaa81"        => "test",
          "aaaaaa9aaaaaa91"        => "test",
          "aaaaaaa9aaaaa01"        => "test",
          "aa2aaaaaaaaaa11"        => "test",
          "aa2aaaaaaaaaa21"        => "test",
          "a2aaaaaaaaaaa31"        => "test",
          "a2aaaaaaaaaaa41"        => "test",
          "aa2aaaaaaaaaa51"        => "test",
          "aa2aaaaaaaaaa61"        => "test",
          "aa2aaaaaaaaaa71"        => "test",
          "aa2aaaaaaaaaa81"        => "test",
          "aa2aaaaaaaaaa91"        => "test",
          "aa2aaaaaaaaaa01"        => "test",
          "birthday"               => "4/9/1970",
      ];
      //array_push($thisCredit,$creditsStatic);
      $currCount++;
    }

    if(sizeof($row_keys) > 0) {
      // Get the all row data from Redis by iterating row keys.
      foreach($row_keys as $row_key) {
        $hkey = $tbl_name . ':' . $row_key;
        $row = $this->redis->hgetall($hkey);
        if(!empty($row)) {
          array_push($credits, $row);
        } else {
          $this->redis->hmset($hkey, $creditsStatic[$row_key]);
          // Push the row id to the list
          $this->redis->rpush($tbl_name, $row_key);

        }

      }
    }

    echo sizeof($credits);

    foreach($credits as $c) {
      echo "<p style='margin:0;font-size-12px;'>" . $c['id'] . " - " . $c['first_name'] . " - " . $c['last_name'] . " - " . $c['aaaaaaaaaaaa0'] . "</p>";
    }
    //echo "<br><br><pre>";
    //var_dump($credits);

    /**
     * Please do whatever you want using user data here ...
     */

    return;
  }

  public function select() {
    throw new \Exception('General fail');

    /**
     * Let's assume select users data from 'Users' table
     * First, check if the data exists in Redis or not.
     * If it exists, get data from Redis. If not, get the data from DB and store it in Redis for the future.
     *
     * The table data stored in the Redis with the combination of one List and one Hashes.
     * List has the row id or primary key of the table.
     * Hashes has a row data and key is the combination of table name and either the row id or the primary key.
     *
     * Key description:
     *   [table name] - Key of List type stores the row id or primary key for specified table
     *   [table name]:[row id/primary key] - Hash key for one row data of specified table stored in Hashes type
     */

    $tbl_name = 'Users';

    // Get the list of row ids for 'Users' table in the Redis
    $row_keys = $this->redis->lrange($tbl_name, 0, -1);

    if(!empty($row_keys)) {
      // Get the all row data from Redis by iterating row keys.
      foreach($row_keys as $row_key) {
        $hkey = $tbl_name . ':' . $row_key;
        $row = $this->redis->hgetall($hkey);
        print_r($row);
      }
    } else {
      // Get the data from DB.
      $users = [
          [
              "id"         => 1,
              "first_name" => "Eric",
              "last_name"  => "Armas",
              "birthday"   => "4/9/1970",
          ],
          [
              "id"         => 2,
              "first_name" => "Nick",
              "last_name"  => "Namikas",
              "birthday"   => "1/1/1970",
          ],
      ];

      /**
       * Store users data in the Redis in Hashes type one row by one.
       */
      foreach($users as $user) {
        $hkey = $tbl_name . ':' . $user['id'];
        $this->redis->hmset($hkey, $user);

        // Push the row id to the list
        $this->redis->rpush($tbl_name, $user['id']);
      }
    }

    /**
     * Please do whatever you want using user data here ...
     */

    return;
  }

  public function insert() {

    throw new \Exception('General fail');

    /**
     * Insert new user data into 'Users' table
     * After insert done successfully, store new user data in Redis.
     *
     * Key description:
     *   [table name] - Key of List type stores the row id or primary key for specified table
     *   [table name]:[row id/primary key] - Hash key for one row data of specified table stored in Hashes type
     */

    /**
     * Insert new user data into DB ...
     */

    $tbl_name = 'Users';

    /**
     * $row - Inserted row data
     * $row['id'] - Row id of new user row could be set using $this->db->insert_id()
     */
    $row = [
        "id"         => 3,
        "first_name" => "Test3",
        "last_name"  => "Armas",
        "birthday"   => "4/9/1970",
    ];

    // Store user data in the Redis in Hashes type.
    $hkey = $tbl_name . ':' . $row['id'];
    $this->redis->hmset($hkey, $row);

    // Push the row id to the list
    $this->redis->rpush($tbl_name, $row['id']);

    echo 'User data inserted successfully.<br>';

    /**
     * Please do whatever you want using user data here ...
     */

    return;
  }

  public function delete() {

    throw new \Exception('General fail');

    /**
     * Delete an user data from 'Users' table
     * After delete done successfully, delete an user data in Redis as well.
     *
     * Key description:
     *   [table name] - Key of List type stores the row id or primary key for specified table
     *   [table name]:[row id/primary key] - Hash key for one row data of specified table stored in Hashes type
     */

    /**
     * Delete an user data into DB ...
     */

    $tbl_name = 'Users';

    $deleted_id = 3;    // Row id of deleted one

    // Get all fields in hash stored at deleted key and remove them
    $hkey = $tbl_name . ':' . $deleted_id;

    $hfields = $this->redis->hkeys($hkey);
    $this->redis->hdel($hkey, $hfields);

    // Push the row id to the list
    $this->redis->lrem($tbl_name, 0, $deleted_id);

    echo 'User data deleted successfully.<br>';

    /**
     * Please do whatever you want using user data here ...
     */

    return;
  }

  //HERE FOR SAMPLE
  function clear_credits_cache($input) {

    throw new \Exception('General fail');

    $input = $this->credit_input($input);

    $creditIds = $this->get_my_credit_ids($input);

    $tbl_name = 'Credits';
    $credits = [];

    if(sizeof($creditIds) > 0) {
      // Get the all row data from Redis by iterating row keys.
      foreach($creditIds as $creditId) {
        $hkey = $tbl_name . ':' . $creditId['listingId'];
        $hfields = $this->redis->hkeys($hkey);
        $this->redis->hdel($hkey, $hfields);
        // Push the row id to the list
        $this->redis->lrem($tbl_name, 0, $creditId['listingId']);

      }
    }

  }

  public function update() {

    throw new \Exception('General fail');

    /**
     * Update an user data in 'Users' table
     * After update done successfully, store updated user data in Redis.
     *
     * Key description:
     *   [table name] - Key of List type stores the row id or primary key for specified table
     *   [table name]:[row id/primary key] - Hash key for one row data of specified table stored in Hashes type
     */

    /**
     * Update an user data into DB ...
     * Example:
     *   Users[1]['first_name'] = 'Eric' -> Users[1]['first_name'] = 'Test1'
     *   Users[1]['last_name'] = 'Armas' -> Users[1]['last_name'] = 'OIX'
     */

    $tbl_name = 'Users';

    /**
     * $row - Updated row data
     * $row['id'] - Row id of new user row could be set using $this->db->insert_id()
     */
    $row = [
        "id"         => 3,
        "first_name" => "Test1",
        "last_name"  => "OIX",
    ];

    // Store updated user data in the Redis in Hashes type.
    $hkey = $tbl_name . ':' . $row['id'];

    foreach($row as $field => $field_val) {
      $this->redis->hset($hkey, $field, $field_val);
    }

    echo 'User data updated successfully.<br>';

    /**
     * Please do whatever you want using user data here ...
     */

    return;
  }
}

/* End of file redis_demo.php */
/* Location: ./application/controllers/redis_demo.php */
