/**
 * On the Scheduled Entry Export feed settings screen, the merge tag
 * drop-down should only offer tags that can resolve in a batch export.
 * Entry-specific tags (individual fields, {entry_id}, {user:...}) have no
 * single entry to pull from, so they are removed here.
 *
 * This script is only enqueued on this add-on's feed settings tab.
 */
( function () {
	var RESOLVABLE_OTHER_TAGS = [
		'{admin_email}',
		'{form_title}',
		'{form_id}',
		'{date_mdy}',
		'{date_dmy}',
	];

	gform.addFilter( 'gform_merge_tags', function ( mergeTags ) {
		var entryGroups = [ 'ungrouped', 'required', 'optional', 'pricing' ];

		for ( var i = 0; i < entryGroups.length; i++ ) {
			if ( mergeTags[ entryGroups[ i ] ] ) {
				mergeTags[ entryGroups[ i ] ].tags = [];
			}
		}

		if ( mergeTags.other ) {
			mergeTags.other.tags = mergeTags.other.tags.filter( function ( tag ) {
				return RESOLVABLE_OTHER_TAGS.indexOf( tag.tag ) !== -1;
			} );
		}

		return mergeTags;
	} );
} )();
