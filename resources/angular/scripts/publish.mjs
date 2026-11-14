/**
 * Copies the production build (dist/pms/browser) into Laravel's public/ directory,
 * mirroring the image build's copy step (spec §11.2, ARC-06). Files published by the
 * previous run are removed first so stale hashed bundles do not pile up.
 */
import { cpSync, existsSync, readFileSync, readdirSync, rmSync, writeFileSync } from 'node:fs';
import { dirname, join, relative } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = dirname(dirname(fileURLToPath(import.meta.url)));
const source = join(root, 'dist', 'pms', 'browser');
const target = join(root, '..', '..', 'public');
const manifestPath = join(target, '.spa-manifest.json');
const protectedFiles = new Set(['index.php', '.htaccess', 'robots.txt']);

if (!existsSync(source)) {
  console.error(`No build found at ${source}. Run "ng build" first.`);
  process.exit(1);
}

if (existsSync(manifestPath)) {
  for (const file of JSON.parse(readFileSync(manifestPath, 'utf8'))) {
    rmSync(join(target, file), { force: true, recursive: true });
  }
}

const published = [];
for (const entry of readdirSync(source)) {
  if (protectedFiles.has(entry)) {
    throw new Error(`Refusing to overwrite Laravel file public/${entry}`);
  }

  cpSync(join(source, entry), join(target, entry), { recursive: true });
  published.push(entry);
}

writeFileSync(manifestPath, JSON.stringify(published, null, 2));
console.log(`Published ${published.length} entries from ${relative(process.cwd(), source)} to public/`);
