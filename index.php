<?php
###
# @name			Generate Missing Medium Photos
# @author		Quentin Bramas
# @copyright	2016 by Quentin Bramas
# @description	This file creates the missing medium photos.

###
# Location
$lychee = __DIR__ . '/../../';
$startTime = microtime(true);
# Load requirements
require($lychee . 'php/define.php');
require($lychee . 'php/autoload.php');
require($lychee . 'php/modules/misc.php');

# Set content
header('content-type: text/plain');

# Load config
if (!file_exists(LYCHEE_CONFIG_FILE)) exit('Error 001: Configuration not found. Please install Lychee first.');
require(LYCHEE_CONFIG_FILE);

# Define the table prefix
if (!isset($dbTablePrefix)) $dbTablePrefix = '';
defineTablePrefix($dbTablePrefix);

# Declare
$result = '';

# Database
$database = new mysqli($dbHost, $dbUser, $dbPassword, $dbName);
if (mysqli_connect_errno()!=0) {
	echo 'Error 100: ' . mysqli_connect_errno() . ': ' . mysqli_connect_error() . '' . PHP_EOL;
	exit();
}

# Load settings
$settings = new Settings($database);
$settings = $settings->get();

# Ensure that user is logged in
session_start();
if ((isset($_SESSION['login'])&&$_SESSION['login']===true)&&
	(isset($_SESSION['identifier'])&&$_SESSION['identifier']===$settings['identifier'])) {
	
	function createMedium($url, $filename, $width, $height) {
		# Function creates a smaller version of a photo when its size is bigger than a preset size
		# Excepts the following:
		# (string) $url = Path to the photo-file
		# (string) $filename = Name of the photo-file
		# (int) $width = Width of the photo
		# (int) $height = Height of the photo
		# Returns the following
		# (boolean) true = Success
		# (boolean) false = Failure
		# Set to true when creation of medium-photo failed
		global $settings;
		
		$error = false;
		# Size of the medium-photo
		# When changing these values,
		# also change the size detection in the front-end
		$newWidth	= 1920;
		$newHeight	= 1080;
		# Check permissions
		if (hasPermissions(LYCHEE_UPLOADS_MEDIUM)===false) {
			# Permissions are missing
			$error = true;
		}
		# Is photo big enough?
		# Is medium activated?
		# Is Imagick installed and activated?
		if (($error===false)&&
			($width>$newWidth||$height>$newHeight)&&
			($settings['medium']==='1')&&
			(extension_loaded('imagick')&&$settings['imagick']==='1')) {
			$newUrl = LYCHEE_UPLOADS_MEDIUM . $filename;
			# Read image
			$medium = new Imagick();
			$medium->readImage(LYCHEE.$url);
			# Adjust image
			$medium->scaleImage($newWidth, $newHeight, true);
			# Save image
			try { $medium->writeImage($newUrl); }
			catch (ImagickException $err) {
				Log::notice($database, __METHOD__, __LINE__, 'Could not save medium-photo: ' . $err->getMessage());
				$error = true;
			}
			$medium->clear();
			$medium->destroy();
		} else {
			# Photo too small or
			# Medium is deactivated or
			# Imagick not installed
			$error = true;
		}
		if($error === true) {
			return false;
		}
		return true;
	}

	function getAllPhotos() {
		# Functions returns data of a photo
		# Excepts the following:
		# (string) $albumID = Album which is currently visible to the user
		# Returns the following:
		# (array) $photo
		
		global $database;	
	
		# Get photo
		$query	= Database::prepare($database, "SELECT id, width, height, url, medium FROM ? WHERE medium=0", array(LYCHEE_TABLE_PHOTOS));
		$photos	= $database->query($query);

		$data = array();
		
		while ($photo = $photos->fetch_assoc()) {	# Parse photo
			
			$photo['filename'] =   $photo['url'];
			$photo['url']      = LYCHEE_URL_UPLOADS_BIG . $photo['url'];
			$data[] = $photo;

		}
		return $data;
	}

	$photos = getAllPhotos();
	if(empty($photos)) {
		exit('done :)');
	}
	$maxPhoto = 4;
	foreach($photos as $photo) {
		if(createMedium($photo['url'], $photo['filename'], $photo['width'], $photo['height'])) { 
			$query  = Database::prepare($database, "UPDATE ? SET medium=1 WHERE id=?", array(LYCHEE_TABLE_PHOTOS, $photo['id']));
			$result	= $database->query($query);
			echo 'success: '.$photo['id']. ' '.$photo['filename'] . "\n";
		}
		else {
			echo 'error:   '.$photo['id'] . ' '.$photo['filename'] . "\n";
		}
		$maxPhoto -= 1;
		if($maxPhoto == 0 || count($photos) - (4 - $maxPhoto) == 0) {
			header("Refresh:0");
			exit((count($photos) - (4 - $maxPhoto)) . ' photos remaining');
		}
	}

	
} else {
	# Don't go further if the user is not logged in
	echo('You have to be logged in to see the log.');
	exit();
}
?>
