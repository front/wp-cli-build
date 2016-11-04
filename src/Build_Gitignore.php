<?php
namespace WP_CLI_Build;

class Build_Gitignore {

	public static function ignore_item( $item = NULL, $type = NULL ) {
		// If we have an item and type.
		if ( ( ! empty( $item ) ) && ( ! empty( $type ) ) ) {
			self::add_line( "wp-content/{$type}s/$item/\n" );
		}
	}

	public static function exclude_item( $item = NULL, $type = NULL ) {
		// If we have an item and type.
		if ( ( ! empty( $item ) ) && ( ! empty( $type ) ) ) {
			self::add_line( "!wp-content/{$type}s/$item/\n" );
		}
	}

	public static function remove_item( $item = NULL, $type = NULL ) {
		// If we have an item and type.
		if ( ( ! empty( $item ) ) && ( ! empty( $type ) ) ) {
			self::del_line( "wp-content/{$type}s/$item/\n" );
		}
	}

	public static function add_line( $line, $verify = TRUE ) {
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
			if ( ( $check_line !== FALSE ) && ( $verify ) ) {
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
					@file_put_contents( $gitignore_path, $gitignore );
				}
			}
		}

		return FALSE;
	}

	// Exclude block that contains plugins/themes.
	public static function exclude_block( $exclude_items = [ ] ) {
		// .gitignore path.
		$gitignore_path = ABSPATH . '.gitignore';
		if ( $gitignore_path == '/.gitignore' ) {
			$gitignore_path = realpath( '.' ) . '/.gitignore';
		}
		// Check if the file exists and load.
		$gitignore = [ ];
		if ( file_exists( $gitignore_path ) ) {
			$gitignore = @file( $gitignore_path );
			// Check if the path is already in ignore file.
			if ( ! empty( $gitignore ) ) {
				// Remove existing block from gitignore.
				$start = array_search( "# START WP-CLI Build\n", $gitignore );
				$end   = array_search( "# END WP-CLI Build\n", $gitignore );
				if ( ( is_int( $start ) ) && ( is_int( $end ) ) ) {
					for ( $i = $start; $i <= $end; $i ++ ) {
						unset( $gitignore[ $i ] );
					}
				}
			}
		}
		// Generates new block.
		if ( ! empty( $exclude_items ) ) {
			$gitignore[] = "# START WP-CLI Build\n";
			foreach ( $exclude_items as $item ) {
				if ( ( ! empty( $item['slug'] ) ) && ( ! empty( $item['type'] ) ) ) {
					$gitignore[] = "!wp-content/{$item['slug']}s/{$item['type']}/\n";
				}

			}
			$gitignore[] = "# END WP-CLI Build\n";
		}
		// Put content in .gitignore.
		if ( ! empty( $gitignore ) ) {
			@file_put_contents( $gitignore_path, $gitignore );

			return TRUE;
		}

		return FALSE;
	}

}