<?php

require_once('src/Rezip.php');
$action = "home";

$opt = getopt("l::r::a:", ['help'], $idx);
if (isset($opt['a'])) {
    $action = $opt['a'];
}
if (isset($opt['help'])) {
	$exe = basename($argv[0]);
    print "{$exe}  [-a action] [-l ]\n";
    print "\thost 接続ホスト\n";
    print "\taction アクション指定(デフォルト：home)\n";
    print "\tparams パラメタ(デフォルト：[])\n";
    print "\n";
    print "ex) {$exe} -h dev7 -a quest_list -p {\\\"quest_type\\\":2}\n\n";
    exit;
}

$paths = array_slice($argv, $idx);
$rezip = new Rezip();
$rezip->exec($paths);

