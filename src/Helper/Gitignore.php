<?php namespace WP_CLI_Build\Helper;

class Gitignore {

	// WP-CLI Build block for .gitignore.
	public static function build_block( $exclude_items = [] ) {
		// .gitignore path.
		$gitignore_path = ABSPATH . '.gitignore';
		if ( $gitignore_path == '/.gitignore' ) {
			$gitignore_path = realpath( '.' ) . '/.gitignore';
		}
		// Check if the file exists and load.
		$gitignore = [];
		if ( file_exists( $gitignore_path ) ) {
			$gitignore = @file( $gitignore_path );
			// Check if the path is already in ignore file.
			if ( ! empty( $gitignore ) ) {
				// Remove existing block from gitignore.
				$start = array_search( "# START WP-CLI BUILD BLOCK\n", $gitignore );
				$end   = array_search( "# END WP-CLI BUILD BLOCK\n", $gitignore );
				if ( ( is_int( $start ) ) && ( is_int( $end ) ) ) {
					for ( $i = $start; $i <= $end; $i ++ ) {
						unset( $gitignore[ $i ] );
					}
				}
			}
		}
		// WP-CLI Build block.
		$gitignore[] = "# START WP-CLI BUILD BLOCK\n";
		$gitignore[] = "# ------------------------------------------------------------\n";
		$gitignore[] = "# This block is auto generated every time you run 'wp build-generate'\n";
		$gitignore[] = "# Rules: Exclude everything from Git except for your custom plugins and themes (that is: those that are not on wordpress.org)\n";
		$gitignore[] = "# ------------------------------------------------------------\n";
		$gitignore[] = "/*\n";
		$gitignore[] = "!.gitignore\n";
		$gitignore[] = "!build.yml\n";
		$gitignore[] = "!wp-content\n";
		$gitignore[] = "wp-content/*\n";
		$gitignore[] = "!wp-content/plugins\n";
		$gitignore[] = "wp-content/plugins/*\n";
		$gitignore[] = "!wp-content/themes\n";
		$gitignore[] = "wp-content/themes/*\n";
		// Generates exclude items.
		if ( ! empty( $exclude_items ) ) {
			$gitignore[] = "# ------------------------------------------------------------\n";
			$gitignore[] = "# Your custom themes/plugins\n";
			$gitignore[] = "# Added dynamically by WP-Build generate command\n";
			$gitignore[] = "# ------------------------------------------------------------\n";
			foreach ( $exclude_items as $item ) {
				if ( ( ! empty( $item['slug'] ) ) && ( ! empty( $item['type'] ) ) ) {
					$gitignore[] = "!wp-content/{$item['type']}s/{$item['slug']}/\n";
				}
			}
		}
		$gitignore[] = "# ------------------------------------------------------------\n";
		$gitignore[] = "# END WP-CLI BUILD BLOCK\n";
		// Put content in .gitignore.
		if ( ! empty( $gitignore ) ) {
			@file_put_contents( $gitignore_path, $gitignore );

			return TRUE;
		}

		return FALSE;
	}

}