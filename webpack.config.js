/**
 * External Dependencies
 */
const { resolve } = require('path');
const defaultConfig = require('@wordpress/scripts/config/webpack.config');

const isProduction = process.env.NODE_ENV === 'production';

const newConfig = {
	...defaultConfig,
	...{
		entry: {
			admin: resolve(process.cwd(), 'src/admin', 'index.js'),
			editor: resolve(process.cwd(), 'src/editor', 'index.js'),
		},
	},

	// Display minimum info in terminal.
	stats: 'minimal',
};

// Development only.
if (!isProduction) {
	newConfig.devServer = {
		...newConfig.devServer,
		// Support for dev server on all domains.
		allowedHosts: 'all',
	};
}

module.exports = newConfig;
