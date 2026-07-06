<?php
/**
 * Feed add-on built on the official Gravity Forms Feed Add-On framework.
 * Each feed is one scheduled export: pick a frequency (hourly, weekly,
 * monthly), a delivery day/time, and who receives the CSV of new entries.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GF_Scheduled_Export extends GFFeedAddOn {

	protected $_version                  = GFSE_VERSION;
	protected $_min_gravityforms_version = '2.5';
	protected $_slug                     = 'gf-scheduled-export';
	protected $_path                     = 'gf-scheduled-export/gf-scheduled-export.php';
	protected $_full_path                = __FILE__;
	protected $_title                    = 'Gravity Forms Scheduled Entry Exports';
	protected $_short_title              = 'Schedule Entry Exports';

	protected $_capabilities_form_settings = 'gravityforms_edit_forms';

	/**
	 * @var GF_Scheduled_Export|null
	 */
	private static $_instance = null;

	/**
	 * @return GF_Scheduled_Export
	 */
	public static function get_instance() {
		if ( null === self::$_instance ) {
			self::$_instance = new self();
		}

		return self::$_instance;
	}

	public function init() {
		parent::init();

		add_action( GFSE_CRON_HOOK, array( $this, 'process_scheduled_feeds' ) );
		add_action( 'admin_post_gfse_send_test', array( $this, 'handle_send_test' ) );
		add_action( 'admin_notices', array( $this, 'maybe_render_test_notice' ) );
		add_filter( 'gform_custom_merge_tags', array( $this, 'add_custom_merge_tags' ), 10, 4 );

		// Self-heal if the cron event was lost (e.g. a cron cleanup plugin).
		if ( ! wp_next_scheduled( GFSE_CRON_HOOK ) ) {
			gfse_activate();
		}
	}

	/**
	 * Feeds are processed on a schedule, never on form submission.
	 *
	 * @param array $entry
	 * @param array $form
	 * @return array
	 */
	public function maybe_process_feed( $entry, $form ) {
		return $entry;
	}

	/**
	 * Limit the merge tag drop-down on this add-on's feed settings tab to
	 * tags that can resolve in a batch export (no single-entry tags).
	 */
	public function scripts() {
		$scripts = array(
			array(
				'handle'    => 'gfse_merge_tags',
				'src'       => plugins_url( 'js/merge-tags.js', GFSE_PLUGIN_FILE ),
				'version'   => $this->_version,
				'deps'      => array( 'gform_form_admin' ),
				'in_footer' => true,
				'enqueue'   => array(
					array(
						'admin_page' => array( 'form_settings' ),
						'tab'        => $this->_slug,
					),
				),
			),
		);

		return array_merge( parent::scripts(), $scripts );
	}

	// ---------------------------------------------------------------------
	// Feed settings screen
	// ---------------------------------------------------------------------

	/**
	 * List this add-on's placeholders in the merge tag drop-down (the {..}
	 * button) on its own feed settings screen. They show under "Custom".
	 *
	 * @param array  $merge_tags
	 * @param int    $form_id
	 * @param array  $fields
	 * @param string $element_id
	 * @return array
	 */
	public function add_custom_merge_tags( $merge_tags, $form_id, $fields, $element_id ) {
		if ( rgget( 'page' ) !== 'gf_edit_forms' || rgget( 'view' ) !== 'settings' || rgget( 'subview' ) !== $this->_slug ) {
			return $merge_tags;
		}

		$merge_tags[] = array(
			'tag'   => '{admin_email}',
			'label' => esc_html__( 'Admin Email', 'gf-scheduled-export' ),
		);
		$merge_tags[] = array(
			'tag'   => '{entry_count}',
			'label' => esc_html__( 'Number of Entries in Export', 'gf-scheduled-export' ),
		);
		$merge_tags[] = array(
			'tag'   => '{period_start}',
			'label' => esc_html__( 'Export Period Start Date', 'gf-scheduled-export' ),
		);
		$merge_tags[] = array(
			'tag'   => '{period_end}',
			'label' => esc_html__( 'Export Period End Date', 'gf-scheduled-export' ),
		);

		return $merge_tags;
	}

	public function feed_settings_title() {
		return esc_html__( 'Scheduled Entry Export', 'gf-scheduled-export' );
	}

	public function feed_settings_fields() {
		$merge_tag_class = 'merge-tag-support mt-position-right mt-hide_all_fields';

		return array(
			array(
				'description' => '<p>' . esc_html__( 'The settings below will automatically export new entries and send them to the emails below based on the set time frame.', 'gf-scheduled-export' ) . '</p>',
				'fields'      => array(
					array(
						'name'     => 'feedName',
						'label'    => esc_html__( 'Name', 'gf-scheduled-export' ),
						'type'     => 'text',
						'required' => true,
						'class'    => 'medium',
					),
					array(
						'name'          => 'frequency',
						'label'         => esc_html__( 'Frequency', 'gf-scheduled-export' ),
						'type'          => 'select',
						'required'      => true,
						'default_value' => 'weekly',
						'tooltip'       => sprintf(
							'<h6>%s</h6>%s',
							esc_html__( 'Frequency', 'gf-scheduled-export' ),
							esc_html__( 'How often the export email is sent. Hourly sends any new entries every hour. Weekly and Monthly send on the day and time you pick below.', 'gf-scheduled-export' )
						),
						'choices'       => array(
							array(
								'label' => esc_html__( 'Hourly', 'gf-scheduled-export' ),
								'value' => 'hourly',
							),
							array(
								'label' => esc_html__( 'Weekly', 'gf-scheduled-export' ),
								'value' => 'weekly',
							),
							array(
								'label' => esc_html__( 'Monthly', 'gf-scheduled-export' ),
								'value' => 'monthly',
							),
						),
					),
					array(
						'name'          => 'delivery_day_week',
						'label'         => esc_html__( 'Delivery Day of the Week', 'gf-scheduled-export' ),
						'type'          => 'select',
						'default_value' => 'monday',
						'tooltip'       => sprintf(
							'<h6>%s</h6>%s',
							esc_html__( 'Delivery Day of the Week', 'gf-scheduled-export' ),
							esc_html__( 'The day of the week the weekly export email is sent.', 'gf-scheduled-export' )
						),
						'choices'       => $this->get_day_of_week_choices(),
						'dependency'    => array(
							'live'   => true,
							'fields' => array(
								array(
									'field'  => 'frequency',
									'values' => array( 'weekly' ),
								),
							),
						),
					),
					array(
						'name'          => 'delivery_day_month',
						'label'         => esc_html__( 'Delivery Day of the Month', 'gf-scheduled-export' ),
						'type'          => 'select',
						'default_value' => '1',
						'tooltip'       => sprintf(
							'<h6>%s</h6>%s',
							esc_html__( 'Delivery Day of the Month', 'gf-scheduled-export' ),
							esc_html__( 'The day of the month the monthly export email is sent. Days 1 to 28 are available so the email goes out every month, including February.', 'gf-scheduled-export' )
						),
						'choices'       => $this->get_day_of_month_choices(),
						'dependency'    => array(
							'live'   => true,
							'fields' => array(
								array(
									'field'  => 'frequency',
									'values' => array( 'monthly' ),
								),
							),
						),
					),
					array(
						'name'          => 'delivery_time',
						'label'         => esc_html__( 'Delivery Time', 'gf-scheduled-export' ),
						'type'          => 'text',
						'input_type'    => 'time',
						'default_value' => '09:00',
						'after_input'   => '&nbsp;&nbsp;' . sprintf(
							/* translators: %s: the site timezone, e.g. America/New_York */
							esc_html__( 'Site Timezone: %s', 'gf-scheduled-export' ),
							'<strong>' . esc_html( wp_timezone_string() ) . '</strong>'
						),
						'tooltip'       => sprintf(
							'<h6>%s</h6>%s',
							esc_html__( 'Delivery Time', 'gf-scheduled-export' ),
							esc_html__( 'The email is sent at (or shortly after) this time. The exact minute depends on when the site next runs its scheduled tasks.', 'gf-scheduled-export' )
						),
						'dependency'    => array(
							'live'   => true,
							'fields' => array(
								array(
									'field'  => 'frequency',
									'values' => array( 'weekly', 'monthly' ),
								),
							),
						),
					),
					array(
						'name'          => 'send_to',
						'label'         => esc_html__( 'Send To Email', 'gf-scheduled-export' ),
						'type'          => 'text',
						'required'      => true,
						'default_value' => '{admin_email}',
						'class'         => $merge_tag_class,
					),
					array(
						'name'    => 'from_name',
						'label'   => esc_html__( 'From Name', 'gf-scheduled-export' ),
						'type'    => 'text',
						'class'   => $merge_tag_class,
						'tooltip' => sprintf(
							'<h6>%s</h6>%s',
							esc_html__( 'From Name', 'gf-scheduled-export' ),
							esc_html__( 'The name the export email appears to come from. Leave blank to use the site default.', 'gf-scheduled-export' )
						),
					),
					array(
						'name'          => 'from_email',
						'label'         => esc_html__( 'From Email', 'gf-scheduled-export' ),
						'type'          => 'text',
						'default_value' => '{admin_email}',
						'class'         => $merge_tag_class,
						'tooltip'       => sprintf(
							'<h6>%s</h6>%s',
							esc_html__( 'From Email', 'gf-scheduled-export' ),
							esc_html__( 'The address the export email is sent from. Use an address that matches this website so the email is less likely to land in spam.', 'gf-scheduled-export' )
						),
					),
					array(
						'name'    => 'reply_to',
						'label'   => esc_html__( 'Reply To', 'gf-scheduled-export' ),
						'type'    => 'text',
						'class'   => $merge_tag_class,
						'tooltip' => sprintf(
							'<h6>%s</h6>%s',
							esc_html__( 'Reply To', 'gf-scheduled-export' ),
							esc_html__( 'Replies to the export email go to this address.', 'gf-scheduled-export' )
						),
					),
					array(
						'name'    => 'bcc',
						'label'   => esc_html__( 'BCC', 'gf-scheduled-export' ),
						'type'    => 'text',
						'class'   => $merge_tag_class,
						'tooltip' => sprintf(
							'<h6>%s</h6>%s',
							esc_html__( 'BCC', 'gf-scheduled-export' ),
							esc_html__( 'Sends a hidden copy of the export email to these addresses.', 'gf-scheduled-export' )
						),
					),
					array(
						'name'          => 'subject',
						'label'         => esc_html__( 'Subject', 'gf-scheduled-export' ),
						'type'          => 'text',
						'required'      => true,
						'default_value' => esc_html__( 'Scheduled Export', 'gf-scheduled-export' ),
						'class'         => $merge_tag_class,
					),
					array(
						'name'        => 'message',
						'label'       => esc_html__( 'Message', 'gf-scheduled-export' ),
						'type'        => 'textarea',
						'class'       => $merge_tag_class . ' medium',
						'placeholder' => $this->get_default_message(),
						'tooltip'     => sprintf(
							'<h6>%s</h6>%s',
							esc_html__( 'Message', 'gf-scheduled-export' ),
							esc_html__( 'The body of the export email. Leave blank to use the ready-made message shown in the box.', 'gf-scheduled-export' )
						),
					),
					array(
						'name'       => 'send_empty_group',
						'label'      => esc_html__( 'Quiet Periods', 'gf-scheduled-export' ),
						'type'       => 'checkbox',
						'tooltip'    => sprintf(
							'<h6>%s</h6>%s',
							esc_html__( 'Quiet Periods', 'gf-scheduled-export' ),
							esc_html__( 'Normally nothing is sent when there are no new entries. Turn this on to send a short email anyway, so recipients know the export is still running.', 'gf-scheduled-export' )
						),
						'choices'    => array(
							array(
								'label' => esc_html__( 'Send an email even when there are no new entries', 'gf-scheduled-export' ),
								'name'  => 'send_empty',
							),
						),
						'dependency' => array(
							'live'   => true,
							'fields' => array(
								array(
									'field'  => 'frequency',
									'values' => array( 'weekly', 'monthly' ),
								),
							),
						),
					),
					array(
						'name'        => 'empty_message',
						'label'       => esc_html__( 'No-Entries Message', 'gf-scheduled-export' ),
						'type'        => 'textarea',
						'class'       => $merge_tag_class . ' medium',
						'placeholder' => $this->get_default_empty_message(),
						'tooltip'     => sprintf(
							'<h6>%s</h6>%s',
							esc_html__( 'No-Entries Message', 'gf-scheduled-export' ),
							esc_html__( 'Sent instead of the message above when there are no new entries. Leave blank to use the ready-made message shown in the box.', 'gf-scheduled-export' )
						),
						'dependency'  => array(
							'live'   => true,
							'fields' => array(
								array(
									'field'  => 'frequency',
									'values' => array( 'weekly', 'monthly' ),
								),
								array(
									'field'  => 'send_empty_group',
									'values' => array( '1' ),
								),
							),
						),
					),
					array(
						'name'           => 'feed_condition',
						'label'          => esc_html__( 'Conditional Logic', 'gf-scheduled-export' ),
						'type'           => 'feed_condition',
						'checkbox_label' => esc_html__( 'Enable Condition', 'gf-scheduled-export' ),
						'instructions'   => esc_html__( 'Export entries if', 'gf-scheduled-export' ),
						'tooltip'        => sprintf(
							'<h6>%s</h6>%s',
							esc_html__( 'Conditional Logic', 'gf-scheduled-export' ),
							esc_html__( 'When enabled, only entries that match these rules are included in the export.', 'gf-scheduled-export' )
						),
					),
				),
			),
		);
	}

	private function get_day_of_week_choices() {
		global $wp_locale;

		$choices = array();

		// Monday first, matching how people think about work weeks.
		foreach ( array( 1, 2, 3, 4, 5, 6, 0 ) as $day_index ) {
			$choices[] = array(
				'label' => $wp_locale->get_weekday( $day_index ),
				'value' => strtolower( gmdate( 'l', strtotime( "Sunday +{$day_index} days" ) ) ),
			);
		}

		return $choices;
	}

	private function get_day_of_month_choices() {
		$choices = array();

		for ( $day = 1; $day <= 28; $day++ ) {
			$choices[] = array(
				'label' => sprintf(
					/* translators: %s: ordinal day of the month, e.g. 1st */
					esc_html__( '%s of the month', 'gf-scheduled-export' ),
					self::ordinal( $day )
				),
				'value' => (string) $day,
			);
		}

		return $choices;
	}

	// ---------------------------------------------------------------------
	// Feed list screen
	// ---------------------------------------------------------------------

	/**
	 * Add a "Send Test" row action next to Edit / Duplicate / Delete. The
	 * feed list table replaces the _id_ placeholder with each feed's ID.
	 */
	public function get_action_links() {
		$links = parent::get_action_links();

		$test_url = wp_nonce_url(
			admin_url( 'admin-post.php?action=gfse_send_test&feed_id=_id_' ),
			'gfse_send_test'
		);

		$links['send_test'] = '<a href="' . esc_url( $test_url ) . '" title="' . esc_attr__( 'Send this export right now, using its saved settings, so you can check the result immediately.', 'gf-scheduled-export' ) . '">' . esc_html__( 'Send Test', 'gf-scheduled-export' ) . '</a>';

		return $links;
	}

	/**
	 * "Send Test" row action: run the feed immediately against its saved
	 * settings without touching the real schedule.
	 */
	public function handle_send_test() {
		if ( ! GFCommon::current_user_can_any( $this->_capabilities_form_settings ) ) {
			wp_die( esc_html__( 'You are not allowed to do that.', 'gf-scheduled-export' ) );
		}

		check_admin_referer( 'gfse_send_test' );

		$feed_id = isset( $_GET['feed_id'] ) ? absint( $_GET['feed_id'] ) : 0;
		$feed    = $this->get_feed( $feed_id );

		if ( ! $feed || rgar( $feed, 'addon_slug' ) !== $this->_slug ) {
			wp_die( esc_html__( 'That export could not be found.', 'gf-scheduled-export' ) );
		}

		// Test period: one full interval back from right now.
		$tz       = wp_timezone();
		$now      = new DateTimeImmutable( 'now', $tz );
		$last_run = $this->subtract_one_interval( $now, rgars( $feed, 'meta/frequency' ) )->getTimestamp() - 1;

		$result = $this->execute_feed( $feed, $last_run, true );

		if ( is_wp_error( $result ) ) {
			$notice = array(
				'type'    => 'error',
				'message' => sprintf(
					/* translators: 1: feed name, 2: error details */
					__( 'The test for "%1$s" could not be sent: %2$s', 'gf-scheduled-export' ),
					rgars( $feed, 'meta/feedName' ),
					$result->get_error_message()
				),
			);
		} else {
			$notice = array(
				'type'    => 'success',
				'message' => sprintf(
					/* translators: %s: feed name */
					__( 'Test sent for "%s" - check the inbox (and spam folder) of the addresses it sends to.', 'gf-scheduled-export' ),
					rgars( $feed, 'meta/feedName' )
				),
			);
		}

		set_transient( 'gfse_test_notice_' . get_current_user_id(), $notice, MINUTE_IN_SECONDS );

		$back = wp_get_referer();
		if ( ! $back ) {
			$back = admin_url( 'admin.php?page=gf_edit_forms&view=settings&subview=' . $this->_slug . '&id=' . rgar( $feed, 'form_id' ) );
		}

		wp_safe_redirect( $back );
		exit;
	}

	/**
	 * Show the result of a "Send Test" after the redirect back to the list.
	 */
	public function maybe_render_test_notice() {
		if ( rgget( 'page' ) !== 'gf_edit_forms' ) {
			return;
		}

		$notice = get_transient( 'gfse_test_notice_' . get_current_user_id() );

		if ( empty( $notice ) || ! is_array( $notice ) ) {
			return;
		}

		delete_transient( 'gfse_test_notice_' . get_current_user_id() );

		printf(
			'<div class="notice notice-%s is-dismissible"><p>%s</p></div>',
			'error' === $notice['type'] ? 'error' : 'success',
			esc_html( $notice['message'] )
		);
	}

	public function feed_list_columns() {
		return array(
			'feedName'         => esc_html__( 'Name', 'gf-scheduled-export' ),
			'frequency'        => esc_html__( 'Frequency', 'gf-scheduled-export' ),
			'schedule_details' => esc_html__( 'Schedule Details', 'gf-scheduled-export' ),
			'send_to'          => esc_html__( 'Email Recipient', 'gf-scheduled-export' ),
			'last_run'         => esc_html__( 'Last Run', 'gf-scheduled-export' ),
		);
	}

	public function get_column_value_frequency( $feed ) {
		$labels = array(
			'hourly'  => esc_html__( 'Hourly', 'gf-scheduled-export' ),
			'weekly'  => esc_html__( 'Weekly', 'gf-scheduled-export' ),
			'monthly' => esc_html__( 'Monthly', 'gf-scheduled-export' ),
		);

		$frequency = rgars( $feed, 'meta/frequency' );

		return isset( $labels[ $frequency ] ) ? $labels[ $frequency ] : esc_html( $frequency );
	}

	public function get_column_value_schedule_details( $feed ) {
		$frequency = rgars( $feed, 'meta/frequency' );
		$time      = $this->format_delivery_time( rgars( $feed, 'meta/delivery_time' ) );

		if ( 'weekly' === $frequency ) {
			global $wp_locale;
			$day_value = rgars( $feed, 'meta/delivery_day_week' );
			$day_index = array_search( strtolower( (string) $day_value ), array( 'sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday' ), true );
			$day_label = false === $day_index ? ucfirst( (string) $day_value ) : $wp_locale->get_weekday( $day_index );

			return esc_html(
				sprintf(
					/* translators: 1: plural weekday, e.g. Mondays, 2: time, e.g. 9:00 am */
					__( '%1$ss at %2$s', 'gf-scheduled-export' ),
					$day_label,
					$time
				)
			);
		}

		if ( 'monthly' === $frequency ) {
			return esc_html(
				sprintf(
					/* translators: 1: ordinal day, e.g. 1st, 2: time, e.g. 9:00 am */
					__( '%1$s of the month at %2$s', 'gf-scheduled-export' ),
					self::ordinal( (int) rgars( $feed, 'meta/delivery_day_month' ) ),
					$time
				)
			);
		}

		return esc_html__( 'N/A', 'gf-scheduled-export' );
	}

	public function get_column_value_send_to( $feed ) {
		return esc_html( rgars( $feed, 'meta/send_to' ) );
	}

	public function get_column_value_last_run( $feed ) {
		$status = self::get_feed_status( rgar( $feed, 'id' ) );

		if ( empty( $status['last_run'] ) || 'pending' === rgar( $status, 'result' ) ) {
			return esc_html__( 'Not yet run', 'gf-scheduled-export' );
		}

		$when = wp_date(
			sprintf(
				/* translators: 1: date format, 2: time format */
				__( '%1$s \a\t %2$s', 'gf-scheduled-export' ),
				get_option( 'date_format', 'F j, Y' ),
				get_option( 'time_format', 'g:i a' )
			),
			(int) $status['last_run']
		);

		if ( ! empty( $status['error'] ) ) {
			return esc_html( $when ) . ' <span style="color:#d63638;" title="' . esc_attr( $status['error'] ) . '">' . esc_html__( '(failed)', 'gf-scheduled-export' ) . '</span>';
		}

		if ( 'skipped' === rgar( $status, 'result' ) ) {
			return esc_html( $when ) . ' <span style="color:#646970;">' . esc_html__( '(no new entries)', 'gf-scheduled-export' ) . '</span>';
		}

		return esc_html( $when );
	}

	// ---------------------------------------------------------------------
	// Scheduling
	// ---------------------------------------------------------------------

	/**
	 * Hourly cron callback: run every active feed that is due.
	 */
	public function process_scheduled_feeds() {
		foreach ( $this->get_feeds() as $feed ) {
			if ( empty( $feed['is_active'] ) ) {
				continue;
			}

			$due_since = $this->get_due_time( $feed );

			if ( null === $due_since ) {
				continue;
			}

			$status = self::get_feed_status( rgar( $feed, 'id' ) );

			if ( empty( $status['last_run'] ) ) {
				// New feed: start counting "new entries" from now and wait
				// for its first scheduled slot, rather than firing instantly.
				self::update_feed_status(
					rgar( $feed, 'id' ),
					array(
						'last_run' => time(),
						'result'   => 'pending',
						'error'    => '',
					)
				);
				continue;
			}

			if ( (int) $status['last_run'] >= $due_since->getTimestamp() ) {
				continue; // Already ran for this slot.
			}

			$this->process_feed_now( $feed, (int) $status['last_run'] );
		}
	}

	/**
	 * The most recent moment this feed was scheduled to go out, or null if
	 * the feed is misconfigured.
	 *
	 * @param array $feed
	 * @return DateTimeImmutable|null
	 */
	private function get_due_time( $feed ) {
		$tz        = wp_timezone();
		$now       = new DateTimeImmutable( 'now', $tz );
		$frequency = rgars( $feed, 'meta/frequency' );
		$time      = $this->parse_delivery_time( rgars( $feed, 'meta/delivery_time' ) );

		if ( 'hourly' === $frequency ) {
			return $now->setTime( (int) $now->format( 'G' ), 0, 0 );
		}

		if ( 'weekly' === $frequency ) {
			$day = strtolower( (string) rgars( $feed, 'meta/delivery_day_week' ) );

			if ( ! in_array( $day, array( 'sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday' ), true ) ) {
				return null;
			}

			$due = $now->modify( $day . ' this week' )->setTime( $time['hour'], $time['minute'], 0 );

			if ( $due > $now ) {
				$due = $due->modify( '-7 days' );
			}

			return $due;
		}

		if ( 'monthly' === $frequency ) {
			$day = min( 28, max( 1, (int) rgars( $feed, 'meta/delivery_day_month' ) ) );
			$due = $now->modify( 'first day of this month' )
				->modify( '+' . ( $day - 1 ) . ' days' )
				->setTime( $time['hour'], $time['minute'], 0 );

			if ( $due > $now ) {
				$due = $due->modify( 'first day of last month' )
					->modify( '+' . ( $day - 1 ) . ' days' )
					->setTime( $time['hour'], $time['minute'], 0 );
			}

			return $due;
		}

		return null;
	}

	/**
	 * @param string|null $raw e.g. "09:00" from the time input.
	 * @return array { hour: int, minute: int }
	 */
	private function parse_delivery_time( $raw ) {
		if ( preg_match( '/^(\d{1,2}):(\d{2})/', (string) $raw, $matches ) ) {
			return array(
				'hour'   => min( 23, (int) $matches[1] ),
				'minute' => min( 59, (int) $matches[2] ),
			);
		}

		return array(
			'hour'   => 9,
			'minute' => 0,
		);
	}

	private function format_delivery_time( $raw ) {
		$time = $this->parse_delivery_time( $raw );

		return wp_date(
			get_option( 'time_format', 'g:i a' ),
			gmmktime( $time['hour'], $time['minute'], 0 ),
			new DateTimeZone( 'UTC' )
		);
	}

	// ---------------------------------------------------------------------
	// Export + email pipeline
	// ---------------------------------------------------------------------

	/**
	 * Export this feed's new entries and email the CSV.
	 *
	 * @param array    $feed
	 * @param int|null $last_run Unix timestamp of the previous run, if any.
	 * @return true|WP_Error
	 */
	public function process_feed_now( $feed, $last_run = null ) {
		$result = $this->execute_feed( $feed, $last_run );

		self::update_feed_status(
			rgar( $feed, 'id' ),
			array(
				'last_run' => time(),
				'result'   => is_wp_error( $result ) ? 'error' : $result,
				'error'    => is_wp_error( $result ) ? $result->get_error_message() : '',
			)
		);

		if ( is_wp_error( $result ) ) {
			$this->log_error( __METHOD__ . '(): Feed #' . rgar( $feed, 'id' ) . ' failed: ' . $result->get_error_message() );
		} else {
			$this->log_debug( __METHOD__ . '(): Feed #' . rgar( $feed, 'id' ) . ' finished: ' . $result );
		}

		return is_wp_error( $result ) ? $result : true;
	}

	/**
	 * @param array    $feed
	 * @param int|null $last_run
	 * @param bool     $is_test  Test sends go out even with zero entries and
	 *                           are labeled as tests.
	 * @return string|WP_Error 'sent' or 'skipped' on success.
	 */
	private function execute_feed( $feed, $last_run, $is_test = false ) {
		$form = GFAPI::get_form( rgar( $feed, 'form_id' ) );

		if ( ! $form ) {
			return new WP_Error( 'gfse_form_missing', __( 'The form for this export no longer exists.', 'gf-scheduled-export' ) );
		}

		$tz  = wp_timezone();
		$now = new DateTimeImmutable( 'now', $tz );

		if ( $last_run ) {
			// New entries since the previous run: no gaps, no repeats.
			$start = ( new DateTimeImmutable( '@' . ( $last_run + 1 ) ) )->setTimezone( $tz );
		} else {
			$start = $this->subtract_one_interval( $now, rgars( $feed, 'meta/frequency' ) );
		}

		$entry_filter = function ( $entry ) use ( $feed, $form ) {
			return $this->is_feed_condition_met( $feed, $form, $entry );
		};

		$export = GFSE_Exporter::export_form( $form, $start, $now, $entry_filter );

		if ( is_wp_error( $export ) ) {
			return $export;
		}

		if ( 0 === $export['count'] && ! $is_test && ! $this->should_send_when_empty( $feed ) ) {
			// Nothing new; don't email an empty spreadsheet.
			wp_delete_file( $export['file'] );
			return 'skipped';
		}

		$sent = $this->send_feed_email( $feed, $form, $export, $start, $now, $is_test );

		wp_delete_file( $export['file'] );

		if ( is_wp_error( $sent ) ) {
			return $sent;
		}

		return 'sent';
	}

	/**
	 * The quiet-period email only applies to weekly and monthly feeds; an
	 * hourly feed would otherwise email an empty spreadsheet all day long.
	 *
	 * @param array $feed
	 * @return bool
	 */
	private function should_send_when_empty( $feed ) {
		return in_array( rgars( $feed, 'meta/frequency' ), array( 'weekly', 'monthly' ), true )
			&& rgars( $feed, 'meta/send_empty' );
	}

	/**
	 * Used both as the Message placeholder and as the fallback when that
	 * setting is left blank.
	 *
	 * @return string
	 */
	private function get_default_message() {
		return __( "Attached is a spreadsheet of the new {form_title} submissions received between {period_start} and {period_end}.\n\nTotal new submissions: {entry_count}", 'gf-scheduled-export' );
	}

	/**
	 * Used both as the No-Entries Message placeholder and as the fallback
	 * when that setting is left blank.
	 *
	 * @return string
	 */
	private function get_default_empty_message() {
		return __( 'No new submissions were received between {period_start} and {period_end}. This email confirms your scheduled export is still running.', 'gf-scheduled-export' );
	}

	/**
	 * @param DateTimeImmutable $now
	 * @param string            $frequency
	 * @return DateTimeImmutable
	 */
	private function subtract_one_interval( $now, $frequency ) {
		switch ( $frequency ) {
			case 'hourly':
				return $now->modify( '-1 hour' );
			case 'monthly':
				return $now->modify( '-1 month' );
			default:
				return $now->modify( '-7 days' );
		}
	}

	/**
	 * @param array             $feed
	 * @param array             $form
	 * @param array             $export { file, count, form_title }
	 * @param DateTimeImmutable $start
	 * @param DateTimeImmutable $end
	 * @param bool              $is_test
	 * @return true|WP_Error
	 */
	private function send_feed_email( $feed, $form, $export, $start, $end, $is_test = false ) {
		$to = $this->parse_email_list( rgars( $feed, 'meta/send_to' ), $form );

		if ( empty( $to ) ) {
			return new WP_Error( 'gfse_no_recipients', __( 'The "Send To Email" address is missing or not valid.', 'gf-scheduled-export' ) );
		}

		$date_format = get_option( 'date_format', 'F j, Y' ) . ' ' . get_option( 'time_format', 'g:i a' );

		$replacements = array(
			'{form_title}'   => $form['title'],
			'{entry_count}'  => (string) $export['count'],
			'{period_start}' => wp_date( $date_format, $start->getTimestamp() ),
			'{period_end}'   => wp_date( $date_format, $end->getTimestamp() ),
		);

		$subject = strtr( $this->replace_merge_tags( rgars( $feed, 'meta/subject' ), $form ), $replacements );

		if ( '' === trim( $subject ) ) {
			$subject = __( 'Scheduled Export', 'gf-scheduled-export' );
		}

		if ( $is_test ) {
			$subject = sprintf(
				/* translators: %s: the email subject */
				__( '[Test] %s', 'gf-scheduled-export' ),
				$subject
			);
		}

		if ( 0 === $export['count'] && ! $is_test ) {
			// Quiet-period email: use the No-Entries Message, falling back
			// to the ready-made text the setting shows as its placeholder.
			$template = rgars( $feed, 'meta/empty_message' );

			if ( '' === trim( (string) $template ) ) {
				$template = $this->get_default_empty_message();
			}

			$message = strtr( $this->replace_merge_tags( $template, $form ), $replacements );
		} else {
			$template = rgars( $feed, 'meta/message' );

			if ( '' === trim( (string) $template ) ) {
				$template = $this->get_default_message();
			}

			$message = strtr( $this->replace_merge_tags( $template, $form ), $replacements );

			if ( $is_test ) {
				$message .= "\n\n" . __( 'This is a test send. The real export will go out on its normal schedule and will only include entries received since the previous export.', 'gf-scheduled-export' );
			}
		}

		$headers = array();

		$from_email = sanitize_email( $this->replace_merge_tags( rgars( $feed, 'meta/from_email' ), $form ) );
		$from_name  = sanitize_text_field( $this->replace_merge_tags( rgars( $feed, 'meta/from_name' ), $form ) );

		if ( $from_email && is_email( $from_email ) ) {
			$headers[] = $from_name
				? sprintf( 'From: %s <%s>', $from_name, $from_email )
				: sprintf( 'From: %s', $from_email );
		}

		$reply_to = sanitize_email( $this->replace_merge_tags( rgars( $feed, 'meta/reply_to' ), $form ) );
		if ( $reply_to && is_email( $reply_to ) ) {
			$headers[] = 'Reply-To: ' . $reply_to;
		}

		foreach ( $this->parse_email_list( rgars( $feed, 'meta/bcc' ), $form ) as $bcc ) {
			$headers[] = 'Bcc: ' . $bcc;
		}

		$sent = wp_mail( $to, $subject, $message, $headers, array( $export['file'] ) );

		if ( ! $sent ) {
			return new WP_Error( 'gfse_mail_failed', __( 'The export email could not be sent. Check the site\'s email setup (an SMTP plugin usually fixes this).', 'gf-scheduled-export' ) );
		}

		return true;
	}

	/**
	 * Resolve merge tags like {admin_email} using the official GF helper.
	 * There is no single entry in a batch export, so entry-specific tags
	 * resolve to empty strings.
	 *
	 * @param string|null $text
	 * @param array       $form
	 * @return string
	 */
	private function replace_merge_tags( $text, $form ) {
		return GFCommon::replace_variables( (string) $text, $form, false, false, false, false, 'text' );
	}

	/**
	 * @param string|null $raw  Comma-separated addresses, may contain merge tags.
	 * @param array       $form
	 * @return string[]
	 */
	private function parse_email_list( $raw, $form ) {
		$emails = array();

		foreach ( explode( ',', $this->replace_merge_tags( $raw, $form ) ) as $email ) {
			$email = sanitize_email( trim( $email ) );

			if ( $email && is_email( $email ) ) {
				$emails[] = $email;
			}
		}

		return array_unique( $emails );
	}

	// ---------------------------------------------------------------------
	// Per-feed run status (stored outside feed meta so saving a feed in the
	// admin never wipes it)
	// ---------------------------------------------------------------------

	/**
	 * @param int $feed_id
	 * @return array { last_run: int, error: string }
	 */
	public static function get_feed_status( $feed_id ) {
		$all = get_option( GFSE_OPTION_FEED_STATUS, array() );

		return isset( $all[ $feed_id ] ) && is_array( $all[ $feed_id ] )
			? $all[ $feed_id ]
			: array(
				'last_run' => 0,
				'error'    => '',
			);
	}

	/**
	 * @param int   $feed_id
	 * @param array $status
	 */
	public static function update_feed_status( $feed_id, $status ) {
		$all             = get_option( GFSE_OPTION_FEED_STATUS, array() );
		$all             = is_array( $all ) ? $all : array();
		$all[ $feed_id ] = $status;

		update_option( GFSE_OPTION_FEED_STATUS, $all, false );
	}

	/**
	 * @param int $number
	 * @return string 1 -> 1st, 2 -> 2nd ...
	 */
	private static function ordinal( $number ) {
		$number = (int) $number;

		if ( in_array( $number % 100, array( 11, 12, 13 ), true ) ) {
			return $number . 'th';
		}

		switch ( $number % 10 ) {
			case 1:
				return $number . 'st';
			case 2:
				return $number . 'nd';
			case 3:
				return $number . 'rd';
			default:
				return $number . 'th';
		}
	}
}
