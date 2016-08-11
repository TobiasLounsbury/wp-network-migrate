<?php

require_once("merge.functions.php");

$host = (array_key_exists("db_host", $_REQUEST)) ? $_REQUEST['db_host'] : "localhost";
$user = (array_key_exists("db_user", $_REQUEST)) ? $_REQUEST['db_user'] : "";
$pass = (array_key_exists("db_pass", $_REQUEST)) ? $_REQUEST['db_pass'] : "";
$src = (array_key_exists("db_src", $_REQUEST)) ? $_REQUEST['db_src'] : "";
$target = (array_key_exists("db_target", $_REQUEST)) ? $_REQUEST['db_target'] : "";
$siteId = (array_key_exists("db_site_id", $_REQUEST)) ? $_REQUEST['db_site_id'] : "2";
$srcPrefix = (array_key_exists("db_src_prefix", $_REQUEST)) ? $_REQUEST['db_src_prefix'] : "wp_";
$targetPrefix = (array_key_exists("db_target_prefix", $_REQUEST)) ? $_REQUEST['db_target_prefix'] : "wp_";


$doUsers = (array_key_exists("users", $_REQUEST) && ($_REQUEST['users'] == "1")) ? true : false;
$doMeta = (array_key_exists("metadata", $_REQUEST) && ($_REQUEST['metadata'] == "1")) ? true : false;
$doPosts = (array_key_exists("posts", $_REQUEST) && ($_REQUEST['posts'] == "1")) ? true : false;
$doTables = (array_key_exists("tables", $_REQUEST) && ($_REQUEST['tables'] == "1")) ? true : false;
$doDropTables = (array_key_exists("dropTables", $_REQUEST) && ($_REQUEST['dropTables'] == "1")) ? true : false;

$live = (array_key_exists("live", $_REQUEST) && ($_REQUEST['live'] == "live")) ? true : false;



if(array_key_exists("action", $_REQUEST) && $_REQUEST['action'] == "run") {

  $db = validateCreds($host, $user, $pass, $target);
  if($db) {
    echo "<ul>";
    $db->query("START TRANSACTION");

    try {

      if($doUsers) {
        importUsers($db, $src, $target, $srcPrefix, $targetPrefix);
      }

      if ($doTables) {
        migrateTables($db, $live, $src, $target, $siteId, $doDropTables, $srcPrefix, $targetPrefix);
      }

      if ($doMeta || $doPosts) {

        $lookup = buildUserLookup($db, $src, $target, $srcPrefix, $targetPrefix);

        if ($doMeta) {
          importUserMeta($db, $src, $target, $lookup, $srcPrefix, $targetPrefix);
        }

        if ($doPosts) {
          updatePosts($db, $target, $siteId, $lookup, $targetPrefix);
        }
      }

      if ($live) {
        $db->query("COMMIT");
      } else {
        $db->query("ROLLBACK");
      }
    } catch (Exception $e) {
      $db->query("ROLLBACK");
    }

    echo "</ul>";
  } else {
    echo "DB Creds Failed:". $db->connect_error;
  }
  echo "<hr />";
}

include("form.php");
