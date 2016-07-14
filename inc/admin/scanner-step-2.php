<?php
defined( 'ABSPATH' ) or die( 'Cheatin&#8217; uh?' );
?>
<div class="secupress-step-content-header secupress-flex secupress-flex-spaced">

	<?php
	$page_title  = __( 'Checked items will be automatically fixed', 'secupress' );
	$main_button = //// geof c'est un button qui trigger les fix, au clic ça repli les items comme le toggle du step 1, et les progress bar arrivent. need JS here CELA NE MENE PAS AU STEP 3, c'est la fin des fix qui donne le step 3, les boutons disparaissent, le titre change et le "toggle check" disparait aussi
	'<a href="' . secupress_admin_url( 'scanners' ) . '&step=3" class="secupress-button secupress-button-tertiary secupress-button-autofix shadow">
		<span class="icon">
			<i class="icon-wrench" aria-hidden="true"></i>
		</span>
		<span class="text">' . esc_html__( 'Fix all checked issues', 'secupress') . '</span>
	</a>';
	?>

	<p class="secupress-step-title"><?php echo $page_title; ?></p>
	<p>
		<?php echo $main_button; ?>
	</p>
</div>
<?php
$modules = secupress_get_modules();

foreach ( $secupress_tests as $module_name => $class_name_parts ) {
	$i = 0;

	$module_title     = ! empty( $modules[ $module_name ]['title'] )              ? $modules[ $module_name ]['title']              : '';
	$module_summary   = ! empty( $modules[ $module_name ]['summaries']['small'] ) ? $modules[ $module_name ]['summaries']['small'] : '';
	$class_name_parts = array_combine( array_map( 'strtolower', $class_name_parts ), $class_name_parts );

	if ( ! $is_subsite ) {
		foreach ( $class_name_parts as $option_name => $class_name_part ) {
			if ( ! file_exists( secupress_class_path( 'scan', $class_name_part ) ) ) {
				unset( $class_name_parts[ $option_name ] );
				continue;
			}

			secupress_require_class( 'scan', $class_name_part );
		}

		// For this priority, order the scans by status: 'good', 'warning', 'good', 'new'.
		$this_prio_bad_scans     = array_intersect_key( $class_name_parts, $bad_scans );
		$class_name_parts        = $this_prio_bad_scans;
		unset( $this_prio_bad_scans, $this_prio_warning_scans, $this_prio_good_scans );
	} else {
		foreach ( $class_name_parts as $option_name => $class_name_part ) {
			// Display only scanners where we have a scan result or a fix to be done.
			if ( empty( $scanners[ $option_name ] ) && empty( $fixes[ $option_name ] ) || ! file_exists( secupress_class_path( 'scan', $option_name ) ) ) {
				unset( $class_name_parts[ $option_name ] );
				continue;
			}

			secupress_require_class( 'scan', $class_name_part );
		}
	}

	?>
	<div id="secupress-tests" class="secupress-tests">
	<div class="secupress-scans-group secupress-group-<?php echo $module_name; ?>">
	<?php
	if ( ! $is_subsite ) {
		?>
		<div class="secupress-sg-header secupress-flex secupress-flex-spaced">

			<div class="secupress-sgh-name">
				<i class="icon-user-login" aria-hidden="true"></i>
				<p class="secupress-sgh-title"><?php echo $module_title; ?></p>
				<p class="secupress-sgh-description"><?php echo $module_summary; ?></p>
			</div>

			<div class="secupress-sgh-actions secupress-flex">
				<label class="text hide-if-no-js" for="secupress-toggle-check-<?php echo $module_name; ?>">
					<span class="label-before-text"><?php _e( 'Toggle group check', 'secupress' ); ?></span>
					<input type="checkbox" id="secupress-toggle-check<?php echo $module_name; ?>" class="secupress-checkbox secupress-toggle-check" checked="checked"/>
					<span class="label-text"></span>
				</label>
			</div>

		</div><!-- .secupress-sg-header -->
		<?php
	}

	foreach ( $class_name_parts as $option_name => $class_name_part ) {
		$class_name   = 'SecuPress_Scan_' . $class_name_part;
		$current_test = $class_name::get_instance();
		$referer      = urlencode( esc_url_raw( self_admin_url( 'admin.php?page=' . SECUPRESS_PLUGIN_SLUG . '_scanners' . ( $is_subsite ? '' : '#' . $class_name_part ) ) ) );
		$is_fixable   = true === $current_test::$fixable || 'pro' === $current_test::$fixable && secupress_is_pro();

		if ( false === $current_test::$fixable || $current_test->need_manual_fix() ) { // Step 2 = only auto fixable items and pro items.
			continue;
		}

		// Scan.
		$scanner        = isset( $scanners[ $option_name ] ) ? $scanners[ $option_name ] : array();
		$scan_status    = ! empty( $scanner['status'] ) ? $scanner['status'] : 'notscannedyet';
		$scan_nonce_url = 'secupress_scanner_' . $class_name_part . ( $is_subsite ? '-' . $site_id : '' );
		$scan_nonce_url = wp_nonce_url( admin_url( 'admin-post.php?action=secupress_scanner&test=' . $class_name_part . '&_wp_http_referer=' . $referer . ( $is_subsite ? '&for-current-site=1&site=' . $site_id : '' ) ), $scan_nonce_url );
		$scan_message   = '&#175;';

		if ( ! empty( $scanner['msgs'] ) ) {
			$scan_message = secupress_format_message( $scanner['msgs'], $class_name_part );
		}

		// Fix.
		$fix             = ! empty( $fixes[ $option_name ] ) ? $fixes[ $option_name ] : array();
		$fix_status_text = ! empty( $fix['status'] ) && 'good' !== $fix['status'] ? secupress_status( $fix['status'] ) : '';
		$fix_nonce_url   = 'secupress_fixit_' . $class_name_part . ( $is_subsite ? '-' . $site_id : '' );
		$fix_nonce_url   = wp_nonce_url( admin_url( 'admin-post.php?action=secupress_fixit&test=' . $class_name_part . '&_wp_http_referer=' . $referer . ( $is_subsite ? '&for-current-site=1&site=' . $site_id : '' ) ), $fix_nonce_url );
		$fix_message     = '';

		if ( ! empty( $fix['msgs'] ) && 'good' !== $scan_status ) {
			$scan_message = secupress_format_message( $fix['msgs'], $class_name_part );
		}

		// Row css class.
		$row_css_class  = ' type-' . sanitize_key( $class_name::$type );
		$row_css_class .= ' status-' . sanitize_html_class( $scan_status );
		$row_css_class .= isset( $autoscans[ $class_name_part ] ) ? ' autoscan' : '';
		$row_css_class .= $is_fixable ? ' fixable' : ' not-fixable';
		$row_css_class .= ! empty( $fix['has_action'] ) ? ' status-hasaction' : '';
		$row_css_class .= ! empty( $fix['status'] ) && empty( $fix['has_action'] ) ? ' has-fix-status' : ' no-fix-status';
		$row_css_class .= ! secupress_is_pro() && 'pro' === $current_test::$fixable ? ' fixable-in-pro' : '';
		++$i;
		?>
		<div id="secupress-group-content-<?php echo $module_name; ?>" class="secupress-sg-content">

			<div class="secupress-item-all secupress-item-<?php echo $class_name_part; ?> type-all status-all <?php echo $row_css_class; ?>" id="<?php echo $class_name_part; ?>">
				<div class="secupress-flex">

					<p class="secupress-item-status">
						<span class="secupress-label">////PUCE ROUGE</span>
					</p>

					<p class="secupress-item-title"><?php echo wp_kses( $current_test::$more_fix, $allowed_tags ); ?></p>

					<p class="secupress-row-actions">
						<?php
						if ( $is_fixable ) {
							// It can be fixed.
							?>
							<input type="checkbox" id="secupress-item-<?php echo $class_name_part; ?>" class="secupress-checkbox secupress-group-check hide-if-no-js" checked="checked"/>
							<label for="secupress-item-<?php echo $class_name_part; ?>" class="label-text hide-if-no-js">
								<span class="screen-reader-text"><?php _e( 'Auto-fix this item', 'secupress' ); ?></span>
							</label>
							<a class="secupress-button-primary secupress-button-mini hide-if-js secupress-fixit<?php echo $current_test::$delayed_fix ? ' delayed-fix' : ''; ?>" href="<?php echo esc_url( $fix_nonce_url ); ?>">
								<span class="icon" aria-hidden="true">
									<i class="icon-shield"></i>
								</span>
								<span class="text">
									<?php _e( 'Fix it', 'secupress' ); ?>
								</span>
							</a>
							<?php
						} elseif ( 'pro' === $current_test::$fixable ) {
							// It is fixable with the pro version but the free version is used.
							?>
							<span class="secupress-get-pro-version">
								<?php printf( __( 'Available in <a href="%s">Pro Version</a>', 'secupress' ), esc_url( secupress_admin_url( 'get_pro' ) ) ); ?>
							</span>
							<?php
						}
						?>
					</p>
				</div>

			</div><!-- .secupress-item-all -->

		</div><!-- .secupress-sg-content -->
		<?php
	}
}
?>
	</div><!-- .secupress-scans-group -->

</div><!-- .secupress-tests -->

<div class="secupress-step-content-footer secupress-flex secupress-flex-top secupress-flex-spaced">
	<p>
		<?php echo $main_button; ?>
	</p>
</div>
