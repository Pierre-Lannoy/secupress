<?php
if ( ! $is_subsite ) {
		$module_icon    = ! empty( $modules[ $module_name ]['icon'] )               ? $modules[ $module_name ]['icon']               : '';
		$module_title   = ! empty( $modules[ $module_name ]['title'] )              ? $modules[ $module_name ]['title']              : '';
		$module_summary = ! empty( $modules[ $module_name ]['summaries']['small'] ) ? $modules[ $module_name ]['summaries']['small'] : '';
?>

<div class="secupress-scans-group secupress-group-<?php echo $module_name; ?>">

		<div class="secupress-sg-header secupress-flex secupress-flex-spaced">

			<div class="secupress-sgh-name">
				<i class="icon-<?php echo $module_icon; ?>" aria-hidden="true"></i>
				<p class="secupress-sgh-title"><?php echo $module_title; ?></p>
				<p class="secupress-sgh-description"><?php echo $module_summary; ?></p>
			</div>

			<div class="secupress-sgh-actions secupress-flex">
				<a href="<?php echo secupress_admin_url( 'modules' ) . '&module=' . $module_name; ?>" target="_blank" class="secupress-link-icon secupress-vcenter">
					<span class="icon"><i class="icon-cog" aria-hidden="true"></i></span>
					<span class="text"><?php _e( 'Go to module settings', 'secupress' ); ?></span>
				</a>
				<button class="secupress-vnormal hide-if-no-js dont-trigger-hide trigger-hide-first" type="button" data-trigger="slidetoggle" data-target="secupress-group-content-<?php echo $module_name; ?>">
					<i class="icon-angle-up" aria-hidden="true"></i>
					<span class="screen-reader-text"><?php _e( 'Show/hide panel', 'secupress' ); ?></span>
				</button>
			</div>

		</div><!-- .secupress-sg-header -->
<?php
}
?>
	<div id="secupress-group-content-<?php echo $module_name; ?>" class="secupress-sg-content">
		<?php
		foreach ( $class_name_parts as $option_name => $class_name_part ) {
			$class_name   = 'SecuPress_Scan_' . $class_name_part;
			$current_test = $class_name::get_instance();
			$referer      = urlencode( esc_url_raw( self_admin_url( 'admin.php?page=' . SECUPRESS_PLUGIN_SLUG . '_scanners' . ( $is_subsite ? '' : '#' . $class_name_part ) ) ) );

			// Scan.
			$scanner        = isset( $scanners[ $option_name ] ) ? $scanners[ $option_name ] : array();
			$scan_status    = ! empty( $scanner['status'] ) ? $scanner['status'] : 'notscannedyet';
			$scan_nonce_url = 'secupress_scanner_' . $class_name_part . ( $is_subsite ? '-' . $site_id : '' );
			$scan_nonce_url = wp_nonce_url( admin_url( 'admin-post.php?action=secupress_scanner&test=' . $class_name_part . '&_wp_http_referer=' . $referer . ( $is_subsite ? '&for-current-site=1&site=' . $site_id : '' ) ), $scan_nonce_url );
			$scan_message   = $current_test->title;

			if ( ! empty( $scanner['msgs'] ) ) {
				$scan_message = secupress_format_message( $scanner['msgs'], $class_name_part );
			}

			// Row css class.
			$row_css_class  = ' status-' . sanitize_html_class( $scan_status );
			$row_css_class .= isset( $autoscans[ $class_name_part ] ) ? ' autoscan' : '';
			?>
			<div class="secupress-item-all secupress-item-<?php echo $class_name_part; ?> status-all <?php echo $row_css_class; ?>" id="<?php echo $class_name_part; ?>">

				<div class="secupress-flex">

					<p class="secupress-item-status">
						<span class="secupress-label"><?php echo secupress_status( $scan_status ); ?></span>
					</p>

					<p class="secupress-item-title"><?php echo $scan_message; ?></p>

					<p class="secupress-row-actions">
						<a class="secupress-button secupress-button-mini secupress-scanit hide-if-js" href="<?php echo esc_url( $scan_nonce_url ); ?>">
							<span class="icon" aria-hidden="true">
								<i class="icon-refresh"></i>
							</span>
							<span class="text">
								<?php echo 'notscannedyet' === $scan_status ? _x( 'Scan', 'verb', 'secupress' ) : _x( 'Re-Scan', 'verb', 'secupress' ); ?>
							</span>
						</a><br class="hide-if-js">

						<?php
						/**
						 * Things changed:
						 * data-trigger added
						 * data-target instead of data-test
						 * data-target === .secupress-item-details' ID
						 */
						?>
						<button data-trigger="slidetoggle" data-target="details-<?php echo $class_name_part; ?>" class="secupress-details link-like hide-if-no-js" type="button">
							<span class="secupress-toggle-button">
								<span aria-hidden="true" class="icon">
									<i class="icon-info-disk"></i>
								</span>
								<span class="text"><?php esc_html_e( 'Learn more', 'secupress'); ?></span>
							</span>
							<span class="secupress-toggle-button hidden" aria-hidden="true">
								<span aria-hidden="true" class="icon">
									<i class="icon-cross"></i>
								</span>
								<span class="text"><?php esc_html_e( 'Close' ); ?></span>
 							</span>
						</button>
					</p>
				</div><!-- .secupress-flex -->

				<div class="secupress-item-details hide-if-js" id="details-<?php echo $class_name_part; ?>">
					<div class="secupress-flex">
						<span class="secupress-details-icon">
							<i class="icon-i" aria-hidden="true"></i>
						</span>
						<p class="details-content"><?php echo wp_kses( $current_test->more, $allowed_tags ); ?></p>
						<span class="secupress-placeholder"></span>
					</div>
				</div><!-- .secupress-item-details -->
			</div><!-- .secupress-item-all -->
			<?php
		} // end
		?>
	</div><!-- .secupress-sg-content -->
</div><!-- .secupress-group-<?php echo $module_name; ?> -->
