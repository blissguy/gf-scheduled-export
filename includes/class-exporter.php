<?php
/**
 * Builds a CSV of Gravity Forms entries for a date range, streaming rows to a
 * temp file so large forms don't exhaust memory.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GFSE_Exporter {

	const PAGE_SIZE = 200;

	/**
	 * Export one form's entries to a temp CSV file.
	 *
	 * @param array             $form         Gravity Forms form object.
	 * @param DateTimeImmutable $start
	 * @param DateTimeImmutable $end
	 * @param callable|null     $entry_filter Optional. Returns false to leave
	 *                                        an entry out of the export (used
	 *                                        for feed conditional logic).
	 * @return array|WP_Error { file, count, form_title }
	 */
	public static function export_form( $form, $start, $end, $entry_filter = null ) {
		$form_id = (int) $form['id'];

		$file = self::create_temp_file( $form['title'] );

		if ( is_wp_error( $file ) ) {
			return $file;
		}

		$handle = fopen( $file, 'w' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen

		if ( ! $handle ) {
			wp_delete_file( $file );
			return new WP_Error( 'gfse_tmp_open', __( 'Could not create a temporary file for the report.', 'gf-scheduled-export' ) );
		}

		// UTF-8 BOM so the file opens cleanly in Excel.
		fwrite( $handle, "\xEF\xBB\xBF" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite

		$columns = self::get_columns( $form );

		// Empty escape = RFC 4180 behavior, and avoids the PHP 8.4 deprecation.
		fputcsv( $handle, wp_list_pluck( $columns, 'label' ), ',', '"', '' );

		// Gravity Forms treats these as site-local times.
		$search_criteria = array(
			'status'     => 'active',
			'start_date' => $start->format( 'Y-m-d H:i:s' ),
			'end_date'   => $end->format( 'Y-m-d H:i:s' ),
		);
		$sorting         = array(
			'key'       => 'date_created',
			'direction' => 'ASC',
		);

		$count  = 0;
		$offset = 0;

		do {
			$paging  = array(
				'offset'    => $offset,
				'page_size' => self::PAGE_SIZE,
			);
			$entries = GFAPI::get_entries( $form_id, $search_criteria, $sorting, $paging );

			if ( is_wp_error( $entries ) ) {
				fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
				wp_delete_file( $file );
				return $entries;
			}

			foreach ( $entries as $entry ) {
				if ( $entry_filter && ! call_user_func( $entry_filter, $entry ) ) {
					continue;
				}
				fputcsv( $handle, self::build_row( $entry, $columns ), ',', '"', '' );
				$count++;
			}

			$offset += self::PAGE_SIZE;
		} while ( count( $entries ) === self::PAGE_SIZE );

		fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose

		// Give the attachment a readable name; wp_mail uses the basename.
		$friendly = dirname( $file ) . '/' . self::friendly_filename( $form['title'], $start, $end );

		if ( file_exists( $friendly ) ) {
			// Another form with the same title, or a stale copy - keep names unique.
			$friendly = dirname( $file ) . '/' . self::friendly_filename( $form['title'] . '-' . $form_id . '-' . wp_generate_password( 6, false ), $start, $end );
		}

		if ( $friendly !== $file && rename( $file, $friendly ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.rename_rename
			$file = $friendly;
		}

		return array(
			'file'       => $file,
			'count'      => $count,
			'form_title' => $form['title'],
		);
	}

	/**
	 * @param string            $form_title
	 * @param DateTimeImmutable $start
	 * @param DateTimeImmutable $end
	 * @return string e.g. contact-form-2026-06-01-to-2026-06-30.csv
	 */
	private static function friendly_filename( $form_title, $start, $end ) {
		$slug = sanitize_title( $form_title );

		if ( '' === $slug ) {
			$slug = 'form-entries';
		}

		return sprintf( '%s-%s-to-%s.csv', $slug, $start->format( 'Y-m-d' ), $end->format( 'Y-m-d' ) );
	}

	/**
	 * Temp file outside any web-accessible uploads folder.
	 *
	 * @param string $form_title
	 * @return string|WP_Error
	 */
	private static function create_temp_file( $form_title ) {
		if ( ! function_exists( 'wp_tempnam' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		$file = wp_tempnam( sanitize_key( $form_title ) . '.csv' );

		if ( ! $file ) {
			return new WP_Error( 'gfse_tmp_create', __( 'Could not create a temporary file for the report.', 'gf-scheduled-export' ) );
		}

		return $file;
	}

	/**
	 * Build the column map from the form definition. Multi-part fields
	 * (name, address, checkboxes) become one column per visible input.
	 *
	 * @param array $form
	 * @return array[] { id, label, field|null }
	 */
	private static function get_columns( $form ) {
		$columns = array(
			array(
				'id'    => 'id',
				'label' => __( 'Entry ID', 'gf-scheduled-export' ),
				'field' => null,
			),
			array(
				'id'    => 'date_created',
				'label' => __( 'Submitted', 'gf-scheduled-export' ),
				'field' => null,
			),
		);

		foreach ( $form['fields'] as $field ) {
			if ( in_array( $field->type, array( 'html', 'section', 'page', 'captcha' ), true ) ) {
				continue;
			}

			$field_label = ! empty( $field->adminLabel ) ? $field->adminLabel : $field->label;
			$inputs      = $field->get_entry_inputs();

			if ( is_array( $inputs ) ) {
				foreach ( $inputs as $input ) {
					if ( ! empty( $input['isHidden'] ) ) {
						continue;
					}

					$input_label = ! empty( $input['customLabel'] ) ? $input['customLabel'] : $input['label'];

					$columns[] = array(
						'id'    => (string) $input['id'],
						'label' => $field_label . ' - ' . $input_label,
						'field' => $field,
					);
				}
			} else {
				$columns[] = array(
					'id'    => (string) $field->id,
					'label' => $field_label,
					'field' => $field,
				);
			}
		}

		return $columns;
	}

	/**
	 * @param array   $entry
	 * @param array[] $columns
	 * @return string[]
	 */
	private static function build_row( $entry, $columns ) {
		$row = array();

		foreach ( $columns as $column ) {
			if ( null === $column['field'] ) {
				if ( 'date_created' === $column['id'] ) {
					$value = get_date_from_gmt( rgar( $entry, 'date_created' ), 'Y-m-d H:i:s' );
				} else {
					$value = rgar( $entry, $column['id'] );
				}
			} else {
				// get_value_export handles multi-part fields, checkboxes,
				// lists, and file uploads (exported as URLs).
				$value = $column['field']->get_value_export( $entry, $column['id'], true, true );
			}

			$row[] = self::escape_csv_value( (string) $value );
		}

		return $row;
	}

	/**
	 * Neutralize spreadsheet formula injection from user-submitted values.
	 *
	 * @param string $value
	 * @return string
	 */
	private static function escape_csv_value( $value ) {
		if ( '' !== $value && preg_match( '/^[=+\-@]/', $value ) ) {
			$value = "'" . $value;
		}

		return $value;
	}
}
