<?php

function buildUserLookup($db, $src, $target, $srcPrefix, $targetPrefix) {
  $lookup = array();

  $sql = "SELECT a.ID as old, b.ID AS `new` FROM `{$src}`.`{$srcPrefix}users` a LEFT JOIN `{$target}`.`{$targetPrefix}users` b ON (b.user_login = a.user_login OR b.user_email = a.user_email) WHERE b.ID IS NOT NULL";
  $res = $db->query($sql);

  while ($row = $res->fetch_assoc()) {
    $lookup[$row['old']] = $row['new'];
  }

  return $lookup;
}

function importUsers($db, $src, $target, $srcPrefix, $targetPrefix) {

  //Get all the existing usernames and email addresses to avoid dupes
  $sql = "SELECT user_login, user_email FROM `{$target}`.`{$targetPrefix}users`";
  $userNames = $emails = array();

  $res = $db->query($sql);
  while ($row = $res->fetch_assoc()) {
    $userNames[] = "'{$row['user_login']}'";
    $emails[] = "'{$row['user_email']}'";
  }

  $clauses = array();

  if(!empty($userNames)) {
    $clauses[] = "`user_login` NOT IN (". implode(", ", $userNames) .")";
  }

  if(!empty($emails)) {
    $clauses[] = "`user_email` NOT IN (". implode(", ", $emails) .")";
  }

  $clauses = (!empty($clauses)) ? "WHERE ". implode(" AND ", $clauses) : "";

  $colSQL = "SHOW columns FROM `{$src}`.`{$srcPrefix}users`";
  $res = $db->query($colSQL);

  $trgtFields = array();
  $srcFields = array();

  while ($row = $res->fetch_assoc()) {

    if ($row['Field'] == "ID") {
      $srcFields[] = "0 AS `ID`";
    } else {
      $srcFields[] = "`{$row['Field']}`";
    }
    $trgtFields[] = "`{$row['Field']}`";
  }

  $trgtFieldList = implode(",", $trgtFields);
  $srcFieldList = implode(",", $srcFields);

  $sql = "INSERT INTO `{$target}`.`{$targetPrefix}users` ({$trgtFieldList}) (SELECT {$srcFieldList} FROM `{$src}`.`{$srcPrefix}users` {$clauses} )";
  if($db->query($sql)) {
    echo "<li>Users Imported Successfully</li>";
  } else {
    echo "<li>Error Importing Users: ". $db->error. "</li>";
    $db->query("ROLLBACK");
    die();
  }
}

function importUserMeta($db, $src, $target, $lookup, $srcPrefix, $targetPrefix, $siteId) {

  //Get the current User ID's so we can determine which ones are dupes
  $sql = "SELECT DISTINCT(user_id) FROM `{$target}`.`{$targetPrefix}usermeta`";
  $oldMetadataIds = array();
  $oldUserIds = array();
  $res = $db->query($sql);
  while ($row = $res->fetch_assoc()) {
    $oldMetadataIds[] = $row['user_id'];
  }

  //Create a temp table so we can work on the data without modifying any perm-data on dry-run
  $createSQL = "CREATE TEMPORARY TABLE `{$target}`.`{$targetPrefix}_wp_migrate_usermeta_temp` LIKE `{$src}`.`{$srcPrefix}usermeta`";
  if (!$db->query($createSQL)) {
    echo "<li>Metadata Error: " . $db->error . "</li>";
  }

  //Import the Data into temp table
  $importSQL = "INSERT `{$target}`.`{$targetPrefix}_wp_migrate_usermeta_temp` SELECT * FROM `{$src}`.`{$srcPrefix}usermeta`";
  if (!$db->query($importSQL)) {
    echo "<li>Error Importing Metadata: " . $db->error . "</li>";
  }

  //Update the User IDs
  foreach($lookup as $old => $new) {
    $sql = "UPDATE `{$target}`.`{$targetPrefix}_wp_migrate_usermeta_temp` SET `user_id` = {$new} WHERE `user_id` = {$old}";
    if(!$db->query($sql)) {
      echo "<li>Error Updating Metadata: ". $db->error. "</li>";
    }
    if (in_array($new, $oldMetadataIds)) {
      $oldUserIds[] = $new;
    }
  }






  $keys = array("capabilities", "user_level");

  //Remove unneeded metadata for dupe users
  if (!empty($oldUserIds)) {
    $keyList = "'wp_". implode("', 'wp_", $keys). "'";
    $dupeUserList = implode(", ", $oldUserIds);
    $sql = "DELETE FROM `{$target}`.`{$targetPrefix}_wp_migrate_usermeta_temp` WHERE `user_id` IN ({$dupeUserList}) AND `meta_key` NOT IN ({$keyList})";

    if(!$db->query($sql)) {
      echo "<li>Error Updating Metadata: ". $db->error. "</li>";
    }
  }

  //Update Site membership/capability and roll keys
  $newUserList = implode(", ", $lookup);
  foreach($keys as $key) {
    $sql = "UPDATE `{$target}`.`{$targetPrefix}_wp_migrate_usermeta_temp` SET `meta_key` = 'wp_{$siteId}_{$key}' WHERE  `meta_key` = 'wp_{$key}' AND `user_id` IN ({$newUserList})";

    if(!$db->query($sql)) {
      echo "<li>Error Updating Metadata: ". $db->error. "</li>";
    }
  }


  //import modified data into target metadata table.
  $sql = "INSERT INTO `{$target}`.`{$targetPrefix}usermeta` (`user_id`, `meta_key`, `meta_value`) (SELECT `user_id`, `meta_key`, `meta_value` FROM `{$target}`.`{$targetPrefix}_wp_migrate_usermeta_temp`)";
  if(!$db->query($sql)) {
    echo "<li>Error Importing Metadata: ". $db->error. "</li>";
  } else {
    echo "<li>Finished Importing User Metadata</li>";
  }

}

function updatePosts($db, $target, $siteId, $lookup, $targetPrefix) {
  foreach($lookup as $old => $new) {
    $sql = "UPDATE `{$target}`.`{$targetPrefix}{$siteId}_posts` SET `post_author` = {$new} WHERE `post_author` = {$old}";
    if(!$db->query($sql)) {
      echo "<li>Error Updating Posts: ". $db->error. "</li>";
      return;
    }
  }
  echo "<li>Successfully Updated Post Authors</li>";
}

function migrateTables($db, $live, $src, $target, $siteId, $drop, $srcPrefix, $targetPrefix) {
  if (!$live) {
    echo "<li>Cannot Dry Run Table Migration: Aborting</li>";
    return;
  }
  $sql = "SELECT table_name FROM information_schema.tables WHERE table_schema = '{$src}'";
  $res = $db->query($sql);

  $x = 0;
  $skip = array("users", "usermeta");

  while ($table = $res->fetch_assoc()) {
    $tableBase = str_replace($srcPrefix, "", $table['table_name']);

    if (strpos($table['table_name'], $srcPrefix) > -1 && !in_array($tableBase, $skip)) {
      $tableName = $targetPrefix . $siteId . "_" . $tableBase;
      if ($drop) {
        $dropSQL = "DROP TABLE IF EXISTS `{$target}`.`{$tableName}`";
        if (!$db->query($dropSQL)) {
          echo "<li>Error Dropping Table `{$tableName}`: " . $db->error . "</li>";
        }
      }

      $createSQL = "CREATE TABLE `{$target}`.`{$tableName}` LIKE `{$src}`.`{$table['table_name']}`";
      if (!$db->query($createSQL)) {
        echo "<li>Error Creating Table `{$tableName}`: " . $db->error . "</li>";
      }

      $importSQL = "INSERT `{$target}`.`{$tableName}` SELECT * FROM `{$src}`.`{$table['table_name']}`";
      if (!$db->query($importSQL)) {
        echo "<li>Error Importing Table Data for `{$tableName}`: " . $db->error . "</li>";
        continue;
      }
      $x++;
    }
  }
  echo "<li>Migrated {$x} tables from {$src} to {$target}</li>";
}


function &validateCreds($host, $user, $pass, $src) {
  $db = new mysqli($host, $user, $pass, $src);
  return $db;
}



