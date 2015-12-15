<?php

/**
 * Backdoor scanner for one of the most common backdoor injections
 *
 * Requires root access and can be used on a server with e.g WHM with several home directories.
 * Saves the original file if a problem arises.
 * @author Svante Hansson <svante.hansson@gmail.com>
 * @version 0.1
 */

/** Setup paths */
define( 'SCAN_DIR', "/home" );
define( 'BACKUP_DIR', "/tmp/backup" );
define( 'LOG_PATH', "/tmp/bd.log" );
define( 'SILENT_RUN', true );

/** Which extensions should be scanned? */
$extensions = array( 'php' );

/**
 * create file with content, and create folder structure if doesn't exist
 *
 * @param String $filepath
 * @param String $message
 */
function backup_file_contents( $filepath, $message ) {
	try {
		$isInFolder = preg_match( "/^(.*)\/([^\/]+)$/", $filepath, $filepathMatches );
		if ( $isInFolder ) {
			$folderName = $filepathMatches[1];
			$fileName   = $filepathMatches[2];
			if ( ! is_dir( $folderName ) ) {
				mkdir( $folderName, 0777, true );
			}
		}
		file_put_contents( $filepath, $message );
	} catch ( Exception $e ) {
		echo "ERR: error writing '$message' to '$filepath', " . $e->getMessage();
	}
}

function scan_dir( $silent = true ) {
	global $extensions;
	$r_di     = new RecursiveDirectoryIterator( SCAN_DIR );
	$f_count  = 0;
	$fs_count = 0;
	$fc_count = 0;

	foreach ( new RecursiveIteratorIterator( $r_di ) as $file ) {
		$pi = pathinfo( $file );
		$f_count ++;
		if ( $pi['extension'] == 'php' ) {
			$fs_count ++;
			$data = file( $file );

			// Look for one of the most used ways to add a backdoor and clean it.
			if ( strpos( $data[0], '?><?php' ) !== false ) {
				if ( strlen( $data[0] ) > 10000 ) {
					if ( stripos( $data[0], ' if(!isset($GLOBALS' ) !== false ) {
						$fc_count ++;
						$clean = explode( '<?php', $data[0] );
						$clean = array_pop( $clean );
						$clean = '<?php ' . $clean;
						if ( ! $silent ) {
							echo $file . ' cleaned to ' . $clean . "\n";
						}
						backup_file_contents( BACKUP_DIR . $file, implode( "\n", $data ) );
						$data[0] = $clean;

						// Clean file
						file_put_contents( $file, implode( "\n", $data ) );
						// Log it
						file_put_contents( LOG_PATH, $file . "\n", FILE_APPEND );
					}
				}
			}
		}
	}

	file_put_contents( LOG_PATH,
		'Scan results: ' . $f_count . ' files. ' . $fs_count . ' files scanned. ' . $fc_count . ' files cleaned.' . "\n",
		FILE_APPEND );
}

scan_dir(SILENT_RUN);
