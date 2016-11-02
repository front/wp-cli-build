<?php
namespace WP_CLI_Build;

class Build_Gitignore {

	public static function add_item( $item = NULL, $type = NULL ) {
		// If we have an item and type.
		if ( ( ! empty( $item ) ) && ( ! empty( $type ) ) ) {
			self::add_line( "wp-content/{$type}s/$item/" );
		}
	}

	public static function del_item( $item = NULL, $type = NULL ) {
		// If we have an item and type.
		if ( ( ! empty( $item ) ) && ( ! empty( $type ) ) ) {
			self::del_line( "wp-content/{$type}s/$item/" );
		}
	}

	public static function add_line( $line ) {
		// .gitignore path.
		$gitignore_path = ABSPATH . '.gitignore';
		if ( $gitignore_path == '/.gitignore' ) {
			$gitignore_path = realpath( '.' ) . '/.gitignore';
		}
		// Check if the file exists and load.
		if ( file_exists( $gitignore_path ) ) {
			$gitignore = @file( $gitignore_path );
		}
		// Check if the line is already in ignore file.
		if ( ! empty( $gitignore ) ) {
			// Check if the item is already in gitignore.
			$check_line = array_search( "$line\n", $gitignore );
			if ( empty( $check_line ) ) {
				$check_line = array_search( $line, $gitignore );
			}
			// If the line is present don't add.
			if ( $check_line !== FALSE ) {
				return TRUE;
			}
		}
		// Add to the .gitignore.
		file_put_contents( $gitignore_path, $line, FILE_APPEND );
	}

	public static function del_line( $line ) {
		// .gitignore path.
		$gitignore_path = ABSPATH . '.gitignore';
		if ( $gitignore_path == '/.gitignore' ) {
			$gitignore_path = realpath( '.' ) . '/.gitignore';
		}
		// Check if the file exists and load.
		if ( file_exists( $gitignore_path ) ) {
			$gitignore = @file( $gitignore_path );
			// Check if the path is already in ignore file.
			if ( ! empty( $gitignore ) ) {
				// Check if the item is already in gitignore.
				$check_line = array_search( "$line\n", $gitignore );
				if ( empty( $check_line ) ) {
					$check_line = array_search( $line, $gitignore );
				}
				// Remove item from gitignore.
				if ( ! empty( $check_line ) ) {
					unset( $gitignore[ $check_line ] );
					@file_put_contents( $line, $gitignore );
				}
			}
		}

		return FALSE;
	}

}