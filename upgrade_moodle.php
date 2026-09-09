<?php

function upgrade_moodle($zip_url) {
    // Define the paths for the Moodle root and Moodledata directories
    $moodle_root = '/var/www/moodle/moodle';
    $moodle_old = '/var/www/moodle/moodle_old';
    $moodledata_dir = '/var/www/moodle/moodledata';

    // Automatically determine the upgrading directory based on the current script path
    $current_path = dirname(__FILE__);
    $upgrade_dir = $current_path . '/upgrading';  // Central upgrading directory
    $temp_dir = $upgrade_dir . '/temp';           // Temp directory within the upgrading directory

    // Directories and files to preserve
    $dirs_to_backup = ['theme', 'config.php'];

    // Create the upgrading and temp directories if they do not exist
    exec("mkdir -p $temp_dir");

    // Step 1: Download the Moodle zip file from the URL
    echo "Downloading the Moodle zip file...\n";
    $zip_file_path = "$temp_dir/moodle.zip";
    if (!download_file($zip_url, $zip_file_path)) {
        die("Failed to download the zip file.\n");
    }

    // Verify the zip file was downloaded and is not corrupted
    if (!file_exists($zip_file_path) || filesize($zip_file_path) === 0) {
        die("Downloaded ZIP file is invalid or empty.\n");
    }

    echo "Starting the Moodle upgrade process...\n";

    // Step 2: Rename the current Moodle directory to "moodle_old"
    if (is_dir($moodle_root)) {
        echo "Renaming current Moodle directory to moodle_old...\n";
        exec("mv $moodle_root $moodle_old");
    }

    // Step 3: Create a new Moodle directory and extract the downloaded Moodle zip file
    echo "Creating new Moodle directory and extracting the new Moodle version...\n";
    exec("mkdir -p $moodle_root");
    exec("unzip $zip_file_path -d $temp_dir");

    // Move the extracted files to the new Moodle directory
    $extracted_moodle_dir = "$temp_dir/moodle";
    if (is_dir($extracted_moodle_dir)) {
        exec("cp -r $extracted_moodle_dir/* $moodle_root/");
    } else {
        die("Failed to find the extracted moodle directory in the temp location.\n");
    }

    // Step 4: Restore the config.php and custom plugins/themes
    echo "Restoring config.php and custom plugins/themes...\n";
    foreach ($dirs_to_backup as $dir) {
        if (file_exists("$moodle_old/$dir")) {
            exec("cp -r $moodle_old/$dir $moodle_root/");
        }
    }

    // Step 5: Clean up temporary files
    echo "Cleaning up temporary files...\n";
    exec("rm -rf $temp_dir");

    echo "Creating new Moodle directory and extracting the new Moodle version...\n";
    exec("mkdir -p $moodle_root");
    exec("unzip $zip_file_path -d $temp_dir");

    // Move the extracted files to the new Moodle directory
    $extracted_moodle_dir = "$temp_dir/moodle";
    if (is_dir($extracted_moodle_dir)) {
        exec("cp -r $extracted_moodle_dir/* $moodle_root/");
    } else {
        die("Failed to find the extracted moodle directory in the temp location.\n");
    }

    // Step 4: Restore the config.php and custom plugins/themes
    echo "Restoring config.php and custom plugins/themes...\n";
    foreach ($dirs_to_backup as $dir) {
        if (file_exists("$moodle_old/$dir")) {
            exec("cp -r $moodle_old/$dir $moodle_root/");
        }
    }

    // Step 5: Clean up temporary files
    echo "Cleaning up temporary files...\n";
    exec("rm -rf $temp_dir");

    echo "Upgrade completed. Plugins, themes, and config.php restored. You can now continue with the Moodle upgrade.\n";
}

// Function to download a file from a URL using file_get_contents
function download_file($url, $destination) {
	$ctx = stream_context_create([
    'http' => [
        'method'          => 'GET',
        'header'          => "User-Agent: Mozilla/5.0 (compatible; DOT-ODP-Updater/1.0)\r\n",
        'follow_location' => 1,
        'max_redirects'   => 5,
        'timeout'         => 300,
    ],
]);
$file_content = file_get_contents($url, false, $ctx);

	if ($file_content === FALSE) {
        return false;
    }

    $file = fopen($destination, 'w');
    if (!$file) {
        return false;
    }

    fwrite($file, $file_content);
    fclose($file);

    return true;
}

// Check if script is called with an argument
if ($argc != 2) {
    die("Usage: php upgrade_moodle.php https://example.com/moodle.zip\n");
}

$zip_url = $argv[1];
upgrade_moodle($zip_url);

?>

