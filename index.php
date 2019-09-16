<?php
###
# @name			Generate Missing Medium Photos
# @author		Quentin Bramas
# @copyright	2016 by Quentin Bramas
# @description	This file creates the missing medium photos.



# maximum number of photos processed (be careful to avoid timeout)
$maxPhoto = 4;

# FROM LYCHEE
# Size of the medium-photo
# When changing these values,
# also change the size detection in the front-end
$newWidth	= 1920;
$newHeight	= 1080;
$quality        = 98;


use Mysqli;
use Lychee\Modules\Database;
use Lychee\Modules\Settings;

$lychee = __DIR__ . '/../../';
$startTime = microtime(true);

require($lychee . 'php/define.php');
require($lychee . 'php/autoload.php');
require($lychee . 'php/helpers/hasPermissions.php');

# Set content
header('content-type: text/plain');

# Load config
if (!file_exists(LYCHEE_CONFIG_FILE)) exit('Error 001: Configuration not found. Please install Lychee first.');
require(LYCHEE_CONFIG_FILE);

# Declare
$result = '';

# Load settings
$settings = new Settings();
$settings = $settings->get();

# Ensure that user is logged in
session_start();
if ((isset($_SESSION['login'])&&$_SESSION['login']===true)&&
	(isset($_SESSION['identifier'])&&$_SESSION['identifier']===$settings['identifier'])) {
	
	# Function taken from Lychee Photo Module
	function createMedium($url, $filename, $type, $width, $height) {
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
		global $newWidth;
		global $newHeight;
		global $quality;
		# Check permissions
		if (hasPermissions(LYCHEE_UPLOADS_MEDIUM)===false) {
			# Permissions are missing
			$error = true;
			echo 'Not enough persmission on the medium folder'."\n";
		}
		# Is photo big enough?
		# Is Imagick installed and activated?
		if (($error===false)&&($width>$newWidth||$height>$newHeight)){
			if((extension_loaded('imagick')&&$settings['imagick']==='1')) {
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
					echo 'Imagick Exception:'."\n";
					var_dump($e);
				}
				$medium->clear();
				$medium->destroy();
                	} else {
				$newUrl = LYCHEE_UPLOADS_MEDIUM . $filename;

				# Read image
                                $newHeight = $newWidth/($width/$height);
		                $medium    = imagecreatetruecolor($newWidth, $newHeight);

				// Create new image
				switch($type) {
					case 'image/jpeg': $sourceImg = imagecreatefromjpeg(LYCHEE.$url); break;
					case 'image/png':  $sourceImg = imagecreatefrompng(LYCHEE.$url); break;
					case 'image/gif':  $sourceImg = imagecreatefromgif(LYCHEE.$url); break;
					default:           Log::notice($database, __METHOD__, __LINE__, 'Type of photo is not supported: ' . $filename . ' of type ' . $type);
                                                           return false;
                                                           break;
				}

				// Create retina thumb
				imagecopyresampled($medium, $sourceImg, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
				imagejpeg($medium, $newUrl, $quality);
				imagedestroy($medium);

				// Free memory
				imagedestroy($sourceImg);
                        }
		} else {
			# Photo too small or
			# Imagick not installed
			$error = true;
		}

		if($error === true) {
			return false;
		}
		return true;
	}

	function getAllPhotos() {
		# Functions returns the list of photos

		global $newWidth;
		global $newHeight;
		# Get photos that do not have a medium size photo
		$query	= Database::prepare(Database::get(), "SELECT id, width, height, url, medium, type FROM ? WHERE medium=0 AND (width > ? OR height > ?)", array(LYCHEE_TABLE_PHOTOS, $newWidth, $newHeight));
		$photos	= Database::get()->query($query);

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
	
	# for each photo we create the medium size photo
	# when reached the maximum number of photo, we reload the page

	foreach($photos as $photo) {
		if(createMedium($photo['url'], $photo['filename'], $photo['type'], $photo['width'], $photo['height'])) {
			$query  = Database::prepare(Database::get(), "UPDATE ? SET medium=1 WHERE id=?", array(LYCHEE_TABLE_PHOTOS, $photo['id']));
			$result	= Database::get()->query($query);
			echo 'success: '.$photo['id']. ' '.$photo['filename'] . "\n";
		}
		else {
			 exit('error:   '.$photo['id'] . ' '.$photo['filename']);
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
