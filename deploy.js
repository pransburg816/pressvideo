/**
 * PressVideo — Full deploy.
 * Uploads the entire plugin tree to devsite via FTP.
 *
 * Usage (from plugin root):
 *   node deploy.js
 *
 * Credentials: .env in the IAC storefront-child theme folder,
 * or place a .env in this directory (takes priority).
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

const SKIP = new Set([
	'node_modules', '.git', '.gitattributes', '.gitignore',
	'.env', 'deploy.js', 'deploy-ui.js', 'package.json', 'package-lock.json',
	'session-log', 'CLAUDE.md', 'Site-Resources', 'languages',
]);

async function deployDir(client, localDir, remoteDir) {
	await client.ensureDir(remoteDir);
	for (const entry of fs.readdirSync(localDir, { withFileTypes: true })) {
		if (SKIP.has(entry.name)) continue;
		const localPath  = path.join(localDir, entry.name);
		const remotePath = remoteDir + '/' + entry.name;
		if (entry.isDirectory()) {
			await deployDir(client, localPath, remotePath);
		} else {
			process.stdout.write(`  → ${remotePath}\n`);
			await client.uploadFrom(localPath, remotePath);
		}
	}
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
		console.log(`✓ Connected to ${process.env.FTP_HOST}`);
		await deployDir(client, PLUGIN_LOCAL, REMOTE_PLUGIN);
		console.log('\n✓ Plugin deployed successfully.');
	} catch (err) {
		console.error(`✗ ${err.message}`);
		process.exit(1);
	} finally {
		client.close();
	}
}
main();
