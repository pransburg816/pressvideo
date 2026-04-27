/**
 * PressVideo — Targeted deploy (UI/dashboard files only).
 * Edit FILES below to choose which files to push.
 *
 * Usage (from plugin root):
 *   node deploy-ui.js
 */

const path     = require('path');
const fs       = require('fs');
const { Client } = require('basic-ftp');

// .env: local copy takes priority, falls back to IAC theme folder.
const localEnv = path.resolve(__dirname, '.env');
const themeEnv = path.resolve(
	__dirname,
	'../../Development Sites/devsite.iac-intl.com/public_html/wp-content/themes/storefront-child/.env'
);
const envPath = fs.existsSync(localEnv) ? localEnv : themeEnv;
if (fs.existsSync(envPath)) {
	fs.readFileSync(envPath, 'utf8').split('\n').forEach(line => {
		const [k, ...v] = line.split('=');
		if (k && v.length) process.env[k.trim()] = v.join('=').trim();
	});
}

const PLUGIN_LOCAL  = __dirname;
const REMOTE_PLUGIN = '/devsite.iac-intl.com/public_html/wp-content/plugins/pv-youtube-importer';

const FILES = [
	'assets/dist/js/admin-customizer.min.js',
	'pv-youtube-importer.php',
	'includes/class-plugin.php',
	'includes/class-activator.php',
	'uninstall.php',
	// PHP — CPT
	'includes/cpt/class-videos-cpt.php',
	'includes/cpt/class-video-taxonomies.php',
	'includes/cpt/class-video-meta.php',
	// PHP — tier
	'includes/class-tier.php',
	// PHP — display
	'includes/display/class-shortcodes.php',
	'includes/display/class-offcanvas.php',
	'includes/display/class-template-loader.php',
	'includes/display/class-template-tags.php',
	'includes/display/class-renderer-factory.php',
	'includes/display/class-renderer-interface.php',
	'includes/display/renderers/class-renderer-offcanvas.php',
	'includes/display/renderers/class-renderer-modal.php',
	'includes/display/class-video-grid.php',
	'includes/display/class-modal.php',
	// Templates
	'templates/single/single-video.php',
	'templates/archive/archive-videos.php',
	'templates/archive/partials/card.php',
	'templates/archive/partials/list-item.php',
	'templates/offcanvas/video-offcanvas.php',
	'templates/modal/video-modal.php',
	'templates/single/layouts/hero-top.php',
	'templates/single/layouts/hero-split.php',
	'templates/single/layouts/theater.php',
	// PHP — admin
	'includes/admin/class-dashboard-page.php',
	'includes/admin/class-import-ui.php',
	'includes/admin/class-settings-page.php',
	'includes/admin/class-customizer-page.php',
	'includes/admin/class-analytics-page.php',
	// PHP — analytics
	'includes/analytics/class-analytics-tracker.php',
	// Admin template
	'templates/admin/customizer.php',
	// PHP — import
	'includes/import/class-youtube-api.php',
	'includes/import/class-channel-importer.php',
	// Display — notifications
	'includes/display/class-notifications.php',
	// CSS
	'assets/dist/css/pv-notifications.css',
	'assets/dist/css/admin.min.css',
	'assets/dist/css/offcanvas.min.css',
	'assets/dist/css/grid.min.css',
	'assets/dist/css/watch-page.min.css',
	'assets/dist/css/admin-customizer.min.css',
	'assets/dist/css/analytics.min.css',
	'assets/dist/css/modal.min.css',
	// JS
	'assets/dist/js/admin-color-picker.min.js',
	'assets/dist/js/offcanvas.min.js',
	'assets/dist/js/lazy-video.min.js',
	'assets/dist/js/archive-filter.min.js',
	'assets/dist/js/admin-customizer.min.js',
	'assets/dist/js/pv-notifications.js',
	'assets/dist/js/pv-tracker.min.js',
	'assets/dist/js/analytics-admin.min.js',
	'assets/dist/js/modal.min.js',
];

async function main() {
	const client = new Client();
	client.ftp.verbose = false;
	try {
		await client.access({
			host:     process.env.FTP_HOST,
			user:     process.env.FTP_USER,
			password: process.env.FTP_PASS,
			secure:   true,
			secureOptions: { rejectUnauthorized: false },
		});
		console.log(`✓ Connected`);
		for (const rel of FILES) {
			const local  = path.join(PLUGIN_LOCAL, rel);
			const remote = REMOTE_PLUGIN + '/' + rel.replace(/\\/g, '/');
			await client.ensureDir(path.dirname(remote).replace(/\\/g, '/'));
			await client.uploadFrom(local, remote);
			console.log(`  ✓ ${rel}`);
		}
		console.log('\n✓ Deploy complete.');
	} catch (err) {
		console.error(`✗ ${err.message}`);
		process.exit(1);
	} finally {
		client.close();
	}
}
main();
