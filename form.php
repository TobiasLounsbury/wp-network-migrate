<?php
$formUsers = ($doUsers) ? "checked" : "";
$formMeta = ($doMeta) ? "checked" : "";
$formPosts = ($doPosts) ? "checked" : "";
$formTables = ($doTables) ? "checked" : "";
$formDropTables = ($doDropTables) ? "checked" : "";

$formLive = ($live) ? "checked" : "";
$formDry = (!$live) ? "checked" : "";

?>
<form action="" method="post">

  <div><label for="db_host">Database Host</label><br /><input name="db_host" id="db_host" value="<?php echo $host; ?>" /></div>
  <div><label for="db_user">Database User</label><br /><input name="db_user" id="db_user" value="<?php echo $user; ?>"/></div>
  <div><label for="db_pass">Database Password</label><br /><input name="db_pass" id="db_pass" type="password" value="<?php echo $pass; ?>" /></div>
  <br />
  <div><label for="db_src">Source Database</label><br /><input name="db_src" id="db_src" value="<?php echo $src; ?>" /></div>
  <div><label for="db_src_prefix">Source Table Prefix</label><br /><input name="db_src_prefix" id="db_src_prefix" value="<?php echo $srcPrefix; ?>" /></div>
  <br />
  <div><label for="db_target">Target Database</label><br /><input name="db_target" id="db_target" value="<?php echo $target; ?>" /></div>
  <div><label for="db_target_prefix">Target Table Prefix</label><br /><input name="db_target_prefix" id="db_target_prefix" value="<?php echo $targetPrefix; ?>" /></div>
  <div><label for="db_site_id">Multi-site ID (for prefix)</label><br /><input size="1" name="db_site_id" id="db_site_id" value="<?php echo $siteId; ?>" /></div>
  <br />
  <div><input type="checkbox" value="1" name="users" id="users" <?php echo $formUsers; ?> /> - <label for="users">Import Users</label></div>
  <div><input type="checkbox" value="1" name="metadata" id="metadata" <?php echo $formMeta; ?> /> - <label for="metadata">Import User MetaData</label></div>
  <div><input type="checkbox" value="1" name="posts" id="posts" <?php echo $formPosts; ?> /> - <label for="posts">Update Post Data</label></div>
  <br />
  <div><input type="checkbox" value="1" name="tables" id="tables" <?php echo $formTables; ?> /> - <label for="tables">Migrate Tables</label></div>
  <div><input type="checkbox" value="1" name="dropTables" id="dropTables" <?php echo $formDropTables; ?> /> - <label for="dropTables">Drop Tables Before Migration</label></div>
  <br />
  <div><input type="radio" name="live" id="live" value="live" <?php echo $formLive; ?> /> - <label for="live">Live Run</label></div>
  <div><input type="radio" name="live" id="dryrun" value="dryrun" <?php echo $formDry; ?> /> - <label for="dryrun">Dry Run</label></div>

  <hr />
  <input type="hidden" name="action" value="run" />
  <input type="submit" value="run" />
</form>
