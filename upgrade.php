<?php

/**
 * This script takes an upgrade URL, e.g. https://download.moodle.org/download.php/direct/stable310/moodle-latest-310.zip, performs a backup of the existing moodle site, downloads the new moodle files and replaces them.
 * 
 * Note that the config.php file of your Moodle instance needs to have the following replaced:
 * 
 * In some cases, such as making backups, we might want the variables in config.php but not execute the setup script.  Add SKIP_SETUP for this reason.
 * if (getenv('SKIP_SETUP') === false) {
 *   require_once(__DIR__ . '/lib/setup.php');
 * }
 *
 * This is to allow the reading of the config.php variables without initializing Moodle
 * 
 */


putenv('SKIP_SETUP=true');
include('moodle/config.php');

$config = [
    'docker' => [
        'service' => 'moodle',
        'moodle_directory' => '/bitnami/moodle',
    ],
    'directories' => [
        'moodle' => 'moodle',
        'moodledata' => 'moodledata',
        'backup' => getcwd() . '/backup',
    ],
    'commands' => [
        'docker-exec' => 'docker-compose exec moodle'
    ],
    'backupfile' => date('d-M-Y-H-i') . '.tar.gz',
    'sourcefile' => readline('Paste in your new moodle source file [https://download.moodle.org/download.php/direct/stable310/moodle-latest-310.zip]'),
];

try {
    echo ('Upgrading Moodle' . PHP_EOL);

    // set a default if empty
    if (strlen($config['sourcefile']) <= 0) {
        $config['sourcefile'] = 'https://download.moodle.org/download.php/direct/stable310/moodle-latest-310.zip';
        echo ('Auto set sourcefile to ' . $config['sourcefile'] . PHP_EOL);
    }

    // check that the pasted source is valid
    $parsed = parse_url($config['sourcefile']);
    if (!isset($parsed['host']) || $parsed['host'] != 'download.moodle.org') {
        throw new Exception("Invalid Moodle source URL", 1);
    }


    echo ('Putting Moodle in Maintenance Mode' . PHP_EOL);
    $cmd = $config['commands']['docker-exec'] . ' php ' . $config['docker']['moodle_directory'] . '/admin/cli/maintenance.php --enable';
    exec($cmd);

    echo ('Clear all caches to reduce size' . PHP_EOL);
    $cmd = $config['commands']['docker-exec'] . ' php ' . $config['docker']['moodle_directory'] . '/admin/cli/purge_caches.php';
    exec($cmd);



    // backup
    if (!is_dir($config['directories']['backup'])) {
        mkdir($config['directories']['backup']);
    }

    if (!is_dir($config['directories']['backup'] . '/tmp')) {
        mkdir($config['directories']['backup'] . '/tmp');
    }


    exec('tar -zcvf ' . $config['directories']['backup'] . '/tmp/moodle.tar.gz -C ' . $config['directories']['moodle'] . ' .');
    exec('tar -zcvf ' . $config['directories']['backup'] . '/tmp/moodledata.tar.gz -C ' . $config['directories']['moodledata'] . ' .');

    $cmd = $config['commands']['docker-exec'] . ' mysqldump -h ' . $CFG->dbhost . ' -u ' . $CFG->dbuser . ' -p' . $CFG->dbpass . ' ' . $CFG->dbname . ' > ' . $config['directories']['backup'] . '/tmp/db.sql';
    exec($cmd);

    $cmd = 'gzip ' . $config['directories']['backup'] . '/tmp/db.sql';
    exec($cmd);

    $cmd = 'tar -zcvf ' . $config['directories']['backup'] . '/' . $config['backupfile'] . ' -C ' . $config['directories']['backup'] . '/tmp .';
    echo $cmd;
    exec($cmd);


    if (!is_dir($config['directories']['backup'] . '/tmp/directoriestocopy')) {
        mkdir($config['directories']['backup'] . '/tmp/directoriestocopy');
    }

    // We are going to need to copy some items back, so make a backup of them for easy copying
    $dirsToSync = ['theme', 'mod', 'blocks', 'local', 'filter'];
    foreach ($dirsToSync as $key => $value) {
        $cmd = 'cp -Rf ' . $config['directories']['moodle'] . '/' . $value . ' ' . $config['directories']['backup'] . '/tmp/directoriestocopy';
        echo $cmd;
        exec($cmd);
    }



    // new source files
    $cmd = 'cp ' . $config['directories']['moodle'] . '/config.php ' . $config['directories']['backup'] . '/tmp';
    exec($cmd);

    $cmd = 'rm -Rf ' . $config['directories']['moodle'] . '/*';
    exec($cmd);


    $cmd = 'wget -q -O tmp.zip ' . $config['sourcefile'] . ' && unzip tmp.zip && rm tmp.zip';
    exec($cmd);

    $cmd = 'cp ' . $config['directories']['backup'] . '/tmp/config.php ' . $config['directories']['moodle'];
    exec($cmd);

    $dirs = array_slice(scandir($config['directories']['backup'] . '/tmp/directoriestocopy'), 2);


    foreach ($dirs as $key => $value) {
        $dirs2 = array_slice(scandir($config['directories']['backup'] . '/tmp/directoriestocopy/' . $value), 2);
        foreach ($dirs2 as $key2 => $value2) {
            // check if it exists on the destination and copy over if not

            if (!is_dir($config['directories']['moodle'] . '/' . $value . '/' . $value2)) {
                $cmd = 'cp -nRf ' . $config['directories']['backup'] . '/tmp/directoriestocopy/' . $value . '/' . $value2 . ' ' . $config['directories']['moodle'] . '/' . $value;
                exec($cmd);
            }
        }
    }

    echo ('Purge all caches' . PHP_EOL);
    $cmd = $config['commands']['docker-exec'] . ' php ' . $config['docker']['moodle_directory'] . '/admin/cli/purge_caches.php';
    exec($cmd);

    $cmd = 'make secure';
    exec($cmd);

    echo ('To complete, login with an admin account and upgrade the database' . PHP_EOL);
} catch (\Throwable $th) {

    // Continue - write rollback routine in the case of something going wrong

    if (is_dir($config['directories']['backup'] . '/tmp')) {
        $cmd = 'rm -Rf ' . $config['directories']['backup'] . '/tmp';
        exec($cmd);
    }
} finally {
    echo ('Putting Moodle out of Maintenance Mode' . PHP_EOL);

    $cmd = 'docker-compose exec ' . $config['docker']['service'] . ' php ' . $config['docker']['moodle_directory'] . '/admin/cli/maintenance.php --disable';
    exec($cmd);

    $cmd = 'rm -Rf ' . $config['directories']['backup'] . '/tmp';
    exec($cmd);
}
