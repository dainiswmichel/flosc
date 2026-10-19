<?php
/**
 * Filesystem writes and moves stay inside uploads, including through symlinks.
 *
 * @package FLOSC
 */

if ( PHP_SAPI !== 'cli' ) {
	exit;
}

define( 'ABSPATH', __DIR__ . '/' );
define( 'FS_CHMOD_FILE', 0644 );

$flosc_test_root    = sys_get_temp_dir() . '/flosc-filesystem-' . bin2hex( random_bytes( 6 ) );
$flosc_test_uploads = $flosc_test_root . '/uploads';
$flosc_test_outside = $flosc_test_root . '/outside';
mkdir( $flosc_test_uploads, 0777, true );
mkdir( $flosc_test_outside, 0777, true );

function wp_upload_dir() {
	global $flosc_test_uploads;
	return array(
		'basedir' => $flosc_test_uploads,
		'error'   => false,
	);
}

function wp_mkdir_p( $path ) {
	return is_dir( $path ) || mkdir( $path, 0777, true );
}

function trailingslashit( $path ) {
	return rtrim( (string) $path, '/\\' ) . '/';
}

function wp_delete_file( $path ) {
	return unlink( $path );
}

class FLOSC_Test_Filesystem_Direct {
	public function put_contents( $path, $content, $mode = false ) {
		return false !== file_put_contents( $path, $content );
	}

	public function get_contents( $path ) {
		return file_get_contents( $path );
	}

	public function exists( $path ) {
		return file_exists( $path );
	}

	public function move( $source, $destination, $overwrite = false ) {
		if ( $overwrite && file_exists( $destination ) ) {
			unlink( $destination );
		}
		return rename( $source, $destination );
	}

	public function delete( $path, $recursive = false, $type = false ) {
		return unlink( $path );
	}
}

$wp_filesystem = new FLOSC_Test_Filesystem_Direct();

require dirname( __DIR__ ) . '/includes/filesystem/class-flosc-filesystem.php';

$flosc_test_fail = 0;
function flosc_test_ok( $label, $actual, $expected ) {
	global $flosc_test_fail;
	$pass = $actual === $expected;
	if ( ! $pass ) {
		$flosc_test_fail++;
	}
	printf(
		"%s %-58s %s%s\n",
		$pass ? 'ok  ' : 'FAIL',
		$label,
		var_export( $actual, true ),
		$pass ? '' : ' (want ' . var_export( $expected, true ) . ')'
	);
}

$filesystem = new FLOSC_Filesystem();
$ordinary   = $flosc_test_uploads . '/data/ordinary.txt';

echo "Ordinary uploads writes still work\n";
flosc_test_ok( 'a new uploads file is written', $filesystem->write_file_safely( $ordinary, 'first' ), true );
flosc_test_ok( 'an existing uploads file is overwritten', $filesystem->write_file_safely( $ordinary, 'second' ), true );
flosc_test_ok( 'the expected bytes were written', file_get_contents( $ordinary ), 'second' );
flosc_test_ok( 'a direct outside path is rejected', $filesystem->write_file_safely( $flosc_test_outside . '/direct.txt', 'changed' ), false );

echo "A destination symlink cannot escape uploads\n";
$outside_target = $flosc_test_outside . '/target.txt';
$write_link     = $flosc_test_uploads . '/data/write-link.txt';
$move_link      = $flosc_test_uploads . '/data/move-link.txt';
$move_source    = $flosc_test_uploads . '/data/move-source.txt';
file_put_contents( $outside_target, 'unchanged' );
file_put_contents( $move_source, 'move-me' );
symlink( $outside_target, $write_link );
symlink( $outside_target, $move_link );

flosc_test_ok( 'write through a symlink is rejected', $filesystem->write_file_safely( $write_link, 'changed' ), false );
flosc_test_ok( 'move onto a symlink is rejected', $filesystem->move_file_safely( $move_source, $move_link ), false );
flosc_test_ok( 'the outside target remains unchanged', file_get_contents( $outside_target ), 'unchanged' );
flosc_test_ok( 'a rejected move keeps its source', file_exists( $move_source ), true );

unlink( $write_link );
unlink( $move_link );
unlink( $move_source );
unlink( $ordinary );
unlink( $outside_target );
rmdir( dirname( $ordinary ) );
rmdir( $flosc_test_uploads );
rmdir( $flosc_test_outside );
rmdir( $flosc_test_root );

echo $flosc_test_fail ? "\n{$flosc_test_fail} FAILURES\n" : "\nFilesystem containment: all checks passed\n";
exit( $flosc_test_fail ? 1 : 0 );
