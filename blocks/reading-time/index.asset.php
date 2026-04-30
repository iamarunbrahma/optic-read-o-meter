<?php
/**
 * Editor-script asset metadata. Read by WordPress when registering the block
 * via block.json (so we don't need a build step).
 */
return array(
	'dependencies' => array(
		'wp-blocks',
		'wp-element',
		'wp-block-editor',
		'wp-components',
		'wp-server-side-render',
		'wp-i18n',
	),
	'version'      => '1.0.0',
);
