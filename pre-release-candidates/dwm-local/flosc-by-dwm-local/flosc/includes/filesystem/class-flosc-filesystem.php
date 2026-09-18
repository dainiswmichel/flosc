<?php
/**
 * FLOSC filesystem operations (WP_Filesystem-backed, uploads-contained writes).
 *
 * Domain folder: includes/filesystem/ — on-disk I/O only (paths live in flosc-data-paths.php).
 *
 * @package FLOSC
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class FLOSC_Filesystem
 */
class FLOSC_Filesystem {

	/**
	 * @return WP_Filesystem_Base|null
	 */
	public function get_wp_filesystem() {
		global $wp_filesystem;

		if (!is_object($wp_filesystem)) {
			if (!function_exists('WP_Filesystem')) {
				require_once ABSPATH . 'wp-admin/includes/file.php';
			}
			WP_Filesystem();
		}

		return is_object($wp_filesystem) ? $wp_filesystem : null;
	}

	/**
	 * True when $path's parent directory is under wp-content/uploads.
	 * Pass 5: blocks writes/moves into the plugin directory.
	 *
	 * @param string $path Absolute filesystem path.
	 * @return bool
	 */
	private function path_is_under_uploads($path) {
		if (!is_string($path) || '' === $path) {
			return false;
		}

		$uploads = wp_upload_dir();
		if (!empty($uploads['error']) || empty($uploads['basedir'])) {
			return false;
		}

		$base_real = realpath($uploads['basedir']);
		if (false === $base_real) {
			return false;
		}

		$parent = dirname($path);
		if (!is_dir($parent)) {
			wp_mkdir_p($parent);
		}

		$dir_real = realpath($parent);
		if (false === $dir_real) {
			return false;
		}

		return 0 === strpos(trailingslashit($dir_real), trailingslashit($base_real));
	}

	/**
	 * Move a file without calling PHP rename().
	 * Destination must be under wp-content/uploads.
	 *
	 * @param string $source      Source path.
	 * @param string $destination Destination path.
	 * @return bool
	 */
	public function move_file_safely($source, $destination) {
		if (!$this->path_is_under_uploads($destination)) {
			return false;
		}

		$filesystem = $this->get_wp_filesystem();
		if ($filesystem && method_exists($filesystem, 'move')) {
			$moved = $filesystem->move($source, $destination, true);
			if ( $moved ) {
				return true;
			}
		}

		// Copy via get/put then delete source (global FS or Direct).
		$body = $this->read_contents( $source );
		if ( false === $body ) {
			return false;
		}
		if ( ! $this->write_file_safely( $destination, $body ) ) {
			return false;
		}
		return $this->delete_file_safely( $source );
	}

	/**
	 * Delete a file without calling PHP unlink().
	 *
	 * @param string $path File path.
	 * @return bool
	 */
	public function delete_file_safely($path) {
		if (!file_exists($path)) {
			return true;
		}

		$filesystem = $this->get_wp_filesystem();
		if ($filesystem && method_exists($filesystem, 'delete')) {
			return $filesystem->delete($path, false, 'f');
		}

		return wp_delete_file($path) !== false;
	}

	/**
	 * Delete a directory without calling PHP rmdir().
	 *
	 * @param string $path Directory path.
	 * @return bool
	 */
	public function delete_directory_safely($path) {
		$filesystem = $this->get_wp_filesystem();
		if ($filesystem && method_exists($filesystem, 'rmdir')) {
			return $filesystem->rmdir($path, true);
		}

		return true;
	}

	/**
	 * Write file contents through WP_Filesystem when available.
	 * Writes only under wp-content/uploads.
	 *
	 * @param string $path    Absolute path.
	 * @param string $content File body.
	 * @return bool
	 */
	public function write_file_safely($path, $content) {
		if (!$this->path_is_under_uploads($path)) {
			return false;
		}

		$filesystem = $this->get_wp_filesystem();
		if ($filesystem && method_exists($filesystem, 'put_contents')) {
			$ok = $filesystem->put_contents($path, $content, FS_CHMOD_FILE);
			if ( false !== $ok && null !== $ok ) {
				return (bool) $ok;
			}
		}

		// Direct local write (WP_Filesystem_Direct) so uploads still work if global FS is not ready.
		if ( ! function_exists( 'WP_Filesystem' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}
		if ( ! class_exists( 'WP_Filesystem_Direct', false ) ) {
			require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-base.php';
			require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-direct.php';
		}
		if ( class_exists( 'WP_Filesystem_Direct' ) ) {
			$direct = new WP_Filesystem_Direct( null );
			return (bool) $direct->put_contents( $path, $content, FS_CHMOD_FILE );
		}

		return false;
	}

	/**
	 * True when $path resolves under wp-content/uploads.
	 *
	 * @param string $path Absolute path.
	 * @return bool
	 */
	private function path_resolves_under_uploads( $path ) {
		$uploads = wp_upload_dir();
		if ( ! empty( $uploads['error'] ) || empty( $uploads['basedir'] ) ) {
			return false;
		}
		$base = realpath( $uploads['basedir'] );
		$real = realpath( $path );
		if ( false === $base || false === $real ) {
			return false;
		}
		return 0 === strpos( $real, $base );
	}

	/**
	 * Read file contents under wp-content/uploads via WP_Filesystem.
	 *
	 * @param string $path Absolute path.
	 * @return string|false File body or false on failure.
	 */
	public function read_file_safely( $path ) {
		if ( ! is_string( $path ) || $path === '' || ! $this->path_resolves_under_uploads( $path ) ) {
			return false;
		}
		return $this->read_contents( $path );
	}

	/**
	 * Read file body via WP_Filesystem. Caller enforces path policy
	 * (uploads catalog, verified upload temp, or plugin sample-data).
	 *
	 * @param string $path Absolute path.
	 * @return string|false
	 */
	public function read_contents( $path ) {
		if ( ! is_string( $path ) || $path === '' ) {
			return false;
		}

		$filesystem = $this->get_wp_filesystem();
		if ( $filesystem && method_exists( $filesystem, 'get_contents' ) ) {
			$exists = file_exists( $path );
			if ( method_exists( $filesystem, 'exists' ) ) {
				$exists = $exists || (bool) $filesystem->exists( $path );
			}
			if ( $exists ) {
				$contents = $filesystem->get_contents( $path );
				if ( false !== $contents ) {
					return (string) $contents;
				}
			}
		}

		// Direct local read when global FS is unavailable or failed.
		if ( ! function_exists( 'WP_Filesystem' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}
		if ( ! class_exists( 'WP_Filesystem_Direct', false ) ) {
			require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-base.php';
			require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-direct.php';
		}
		if ( class_exists( 'WP_Filesystem_Direct' ) && file_exists( $path ) ) {
			$direct   = new WP_Filesystem_Direct( null );
			$contents = $direct->get_contents( $path );
			return ( false === $contents ) ? false : (string) $contents;
		}

		return false;
	}

	/**
	 * Atomic text write under uploads: .tmp then move.
	 *
	 * @param string $path    Final absolute path under uploads.
	 * @param string $content File body.
	 * @return bool
	 */
	public function write_text_atomic( $path, $content ) {
		if ( ! $this->path_is_under_uploads( $path ) ) {
			return false;
		}
		$tmp = $path . '.tmp';
		if ( ! $this->write_file_safely( $tmp, (string) $content ) ) {
			return false;
		}
		if ( ! $this->move_file_safely( $tmp, $path ) ) {
			$this->delete_file_safely( $tmp );
			return false;
		}
		return true;
	}

	/**
	 * Write Deny-from-all .htaccess into an uploads-bound directory.
	 *
	 * @param string $dir Absolute directory path under uploads.
	 * @return bool
	 */
	public function protect_uploads_dir_with_htaccess( $dir ) {
		if ( ! is_string( $dir ) || $dir === '' ) {
			return false;
		}
		if ( ! is_dir( $dir ) ) {
			wp_mkdir_p( $dir );
		}
		return $this->write_file_safely( trailingslashit( $dir ) . '.htaccess', "Deny from all\n" );
	}

	/**
	 * Emit raw bytes already framed by the caller (headers set) and exit.
	 * Prefer WP_Filesystem put_contents(php://output) over bare echo.
	 *
	 * @param string $body Binary or plain-text body.
	 * @return void
	 */
	public function emit_raw_bytes_and_exit( $body ) {
		$body = is_string( $body ) ? $body : '';
		$filesystem = $this->get_wp_filesystem();
		if ( $filesystem && method_exists( $filesystem, 'put_contents' ) ) {
			$filesystem->put_contents( 'php://output', $body );
			exit;
		}
		status_header( 500 );
		exit;
	}

	/**
	 * Stream a plain-text (non-HTML) file download and exit.
	 * Uses WP_Filesystem → php://output so we do not echo unescaped HTML context.
	 *
	 * @param string $body         Raw file body (TSV, markdown, etc.).
	 * @param string $content_type MIME type (e.g. text/markdown; charset=UTF-8).
	 * @param string $filename     Download filename (sanitized by caller).
	 * @return void
	 */
	public function stream_plain_download_and_exit( $body, $content_type, $filename ) {
		$body         = is_string( $body ) ? $body : '';
		$content_type = is_string( $content_type ) && $content_type !== '' ? $content_type : 'application/octet-stream';
		$filename     = sanitize_file_name( (string) $filename );
		if ( $filename === '' ) {
			$filename = 'download.txt';
		}

		nocache_headers();
		header( 'Content-Type: ' . $content_type );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		header( 'Content-Length: ' . (string) strlen( $body ) );
		header( 'X-Content-Type-Options: nosniff' );

		$this->emit_raw_bytes_and_exit( $body );
	}

	/**
	 * Stream an uploads-bound binary file (optional HTTP Range) and exit.
	 * Loads via WP_Filesystem then slices in memory — intended for small media
	 * (e.g. phrase audio clips), not multi‑gigabyte assets.
	 *
	 * @param string      $path         Absolute path under uploads.
	 * @param string      $mime         Content-Type.
	 * @param string      $filename     Download/inline filename.
	 * @param bool        $is_download  Attachment vs inline.
	 * @param string|null $range_header Raw HTTP Range header value, or null.
	 * @return void
	 */
	public function stream_uploads_binary_range_and_exit( $path, $mime, $filename, $is_download = false, $range_header = null ) {
		$body = $this->read_file_safely( $path );
		if ( false === $body ) {
			status_header( 404 );
			exit;
		}

		$size  = strlen( $body );
		$start = 0;
		$end   = $size > 0 ? $size - 1 : 0;
		$partial = false;

		if ( is_string( $range_header ) && $range_header !== '' && $size > 0 ) {
			if ( preg_match( '/bytes=(\d*)-(\d*)/', $range_header, $m ) ) {
				$rs = ( isset( $m[1] ) && $m[1] !== '' ) ? (int) $m[1] : null;
				$re = ( isset( $m[2] ) && $m[2] !== '' ) ? (int) $m[2] : null;
				if ( null !== $rs ) {
					$start   = $rs;
					$end     = null !== $re ? min( $re, $size - 1 ) : $size - 1;
					$partial = true;
				} elseif ( null !== $re ) {
					// Suffix: last N bytes.
					$start   = max( 0, $size - $re );
					$end     = $size - 1;
					$partial = true;
				}
			}
		}

		if ( $size <= 0 || $start > $end || $start >= $size ) {
			status_header( 416 );
			header( 'Content-Range: bytes */' . $size );
			exit;
		}

		$slice    = substr( $body, $start, $end - $start + 1 );
		$filename = sanitize_file_name( (string) $filename );
		if ( $filename === '' ) {
			$filename = 'download.bin';
		}
		$mime = is_string( $mime ) && $mime !== '' ? $mime : 'application/octet-stream';

		header( 'Accept-Ranges: bytes' );
		header( 'Content-Type: ' . $mime );
		header( 'Content-Disposition: ' . ( $is_download ? 'attachment' : 'inline' ) . '; filename="' . $filename . '"' );
		header( 'Cache-Control: private, max-age=3600' );
		header( 'X-Content-Type-Options: nosniff' );

		if ( $partial ) {
			status_header( 206 );
			header( 'Content-Range: bytes ' . $start . '-' . $end . '/' . $size );
		}
		header( 'Content-Length: ' . (string) strlen( $slice ) );

		$this->emit_raw_bytes_and_exit( $slice );
	}

	/**
	 * Atomic JSON write: write temp file then move into place.
	 *
	 * @param string $path Absolute path.
	 * @param mixed  $data Data to encode.
	 * @return bool
	 */
	public function write_json_atomic($path, $data) {
		$json = wp_json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
		if (!is_string($json) || $json === '') {
			return false;
		}

		$tmp_path = $path . '.tmp';
		if (!$this->write_file_safely($tmp_path, $json)) {
			return false;
		}

		return $this->move_file_safely($tmp_path, $path);
	}
}
