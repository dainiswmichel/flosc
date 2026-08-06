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
			return $filesystem->move($source, $destination, true);
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_copy -- fallback when WP_Filesystem move is unavailable
		return copy($source, $destination) && $this->delete_file_safely($source); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_copy -- controlled file copy in FLOSC-managed path
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
			return (bool) $filesystem->put_contents($path, $content, FS_CHMOD_FILE);
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- fallback when WP_Filesystem is unavailable
		return false !== file_put_contents($path, $content); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- controlled write in FLOSC-managed path
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
