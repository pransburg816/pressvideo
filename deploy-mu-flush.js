/**
 * One-shot: upload the OPcache flush MU-plugin to the server.
 * Run once: node deploy-mu-flush.js
 * WordPress will load + auto-delete it on the next admin page load.
 */

const path = require('path');
const fs   = require('fs');
const { Client } = require('basic-ftp');

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
		console.log('✓ Connected');
		const local  = path.join(__dirname, 'pv-opcache-flush.php');
		const remote = '/devsite.iac-intl.com/public_html/wp-content/mu-plugins/pv-opcache-flush.php';
		await client.ensureDir('/devsite.iac-intl.com/public_html/wp-content/mu-plugins');
		await client.uploadFrom(local, remote);
		console.log('✓ Uploaded pv-opcache-flush.php to mu-plugins/');
		console.log('\nNow load any WP admin page once — it will flush OPcache and self-delete.');
	} catch (err) {
		console.error(`✗ ${err.message}`);
		process.exit(1);
	} finally {
		client.close();
	}
}
main();
