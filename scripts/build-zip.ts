#!/usr/bin/env tsx
/**
 * Empacota o plugin "Bible by Midvash" num ZIP pronto pra distribuir.
 *
 * Modos:
 *   `npx tsx scripts/build-zip.ts`                  → ZIP completo (default; com
 *                                                     plugin-update-checker, para
 *                                                     distribuição via R2).
 *   `npx tsx scripts/build-zip.ts --target=wp-org`  → ZIP “limpo”: remove
 *                                                     vendor/plugin-update-checker
 *                                                     e o bloco WPORG_STRIP_*
 *                                                     do bible-by-midvash.php.
 *                                                     Esse é o ZIP que vai pra
 *                                                     submissão no diretório
 *                                                     oficial WordPress.org —
 *                                                     o updater externo é vedado
 *                                                     pelas Plugin Review Guidelines.
 *
 * Entrada/saída:
 *   - Versão lida do header `Version:` em `bible-by-midvash.php`.
 *   - Estrutura interna do zip: `bible-by-midvash/` (slug oficial — WP exige).
 *   - Saída padrão:    `dist/bible-by-midvash-{version}.zip`
 *   - Saída wp-org:    `dist/bible-by-midvash-{version}-wporg.zip`
 *
 * Minifica cada `assets/{js,css}/*` com esbuild e grava `nome-<versão>.min.ext`
 * ao lado do original (o PHP serve o `.min`; com SCRIPT_DEBUG, o original). A
 * versão vai no nome porque plugin de cache tira o `?ver=` e a CDN do site
 * seguiria servindo o arquivo da release anterior. O código
 * legível fica no ZIP, como pede o wordpress.org.
 *
 * Usa o `zip` do macOS/linux e o esbuild via npx.
 */

import { execSync } from 'node:child_process';
import { existsSync, mkdirSync, readFileSync, readdirSync, rmSync, cpSync, writeFileSync } from 'node:fs';
import { join, resolve } from 'node:path';
import { tmpdir } from 'node:os';

const ROOT = resolve(__dirname, '..');
const PLUGIN_PHP = join(ROOT, 'bible-by-midvash.php');
const DIST_DIR = join(ROOT, 'dist');
const SLUG = 'bible-by-midvash';

const WPORG_STRIP_START = '// {{WPORG_STRIP_START}}';
const WPORG_STRIP_END = '// {{WPORG_STRIP_END}}';

type Target = 'default' | 'wp-org';

function parseTarget(): Target {
  const arg = process.argv.find((a) => a.startsWith('--target='));
  if (!arg) return 'default';
  const value = arg.split('=')[1];
  if (value === 'wp-org') return 'wp-org';
  if (value === 'default') return 'default';
  throw new Error(`Unknown --target value: ${value} (expected 'default' or 'wp-org')`);
}

function readVersion(): string {
  if (!existsSync(PLUGIN_PHP)) {
    throw new Error(`Plugin PHP not found at ${PLUGIN_PHP}`);
  }
  const content = readFileSync(PLUGIN_PHP, 'utf8');
  const match = content.match(/^\s*\*\s*Version:\s*([0-9]+\.[0-9]+\.[0-9]+)/m);
  if (!match) {
    throw new Error('Could not parse `Version:` header in bible-by-midvash.php');
  }
  return match[1];
}

/**
 * Removes everything between WPORG_STRIP_START and WPORG_STRIP_END markers
 * (inclusive) from the plugin entry-point. Used for wp.org builds.
 */
function stripWporgBlock(php: string): string {
  const start = php.indexOf(WPORG_STRIP_START);
  if (start === -1) {
    // Already stripped or markers were removed manually — nothing to do.
    return php;
  }
  const end = php.indexOf(WPORG_STRIP_END);
  if (end === -1) {
    throw new Error(`Found ${WPORG_STRIP_START} but no matching ${WPORG_STRIP_END}`);
  }
  // Include the end marker line + trailing newline so we don't leave blank padding.
  const after = php.indexOf('\n', end) + 1;
  return php.slice(0, start) + php.slice(after > 0 ? after : end + WPORG_STRIP_END.length);
}

const ESBUILD = 'esbuild@0.24.2';

/** Writes `name-<version>.min.ext` next to every .js/.css under assets/js and assets/css. */
function minifyAssets(pluginDir: string, version: string) {
  for (const dir of ['assets/js', 'assets/css']) {
    const abs = join(pluginDir, dir);
    if (!existsSync(abs)) continue;
    for (const file of readdirSync(abs)) {
      if (!/\.(js|css)$/.test(file) || /\.min\.(js|css)$/.test(file)) continue;
      const src = join(abs, file);
      const out = src.replace(/\.(js|css)$/, `-${version}.min.$1`);
      execSync(`npx --yes ${ESBUILD} "${src}" --minify --log-level=warning --outfile="${out}"`, { stdio: 'inherit' });
    }
  }
}

const KEY_LINE = /define\( 'BBMV_PLUGIN_KEY', '' \);/;

/** Fills an empty BBMV_PLUGIN_KEY from the environment, if one is given. */
function injectPluginKey(phpFile: string) {
  const key = process.env.BBMV_PLUGIN_KEY ?? '';
  if (!key) return;
  if (!/^[A-Za-z0-9]+$/.test(key)) throw new Error('BBMV_PLUGIN_KEY must be alphanumeric');
  const php = readFileSync(phpFile, 'utf8');
  if (!KEY_LINE.test(php)) {
    console.warn('[warn] BBMV_PLUGIN_KEY já preenchida no arquivo; env ignorada');
    return;
  }
  writeFileSync(phpFile, php.replace(KEY_LINE, `define( 'BBMV_PLUGIN_KEY', '${key}' );`));
}

function main() {
  const target = parseTarget();
  const version = readVersion();
  const suffix = target === 'wp-org' ? '-wporg' : '';
  const stagingRoot = join(tmpdir(), `midvash-wp-build-${target}-${Date.now()}`);
  const stagingPlugin = join(stagingRoot, SLUG);
  const outFile = join(DIST_DIR, `${SLUG}-${version}${suffix}.zip`);

  mkdirSync(DIST_DIR, { recursive: true });
  mkdirSync(stagingPlugin, { recursive: true });

  // Files to include — plugin code only, no repo metadata (README.md, LICENSE, scripts, .github)
  const includePaths = ['bible-by-midvash.php', 'readme.txt', 'uninstall.php', 'includes', 'assets', 'languages'];
  if (target !== 'wp-org') {
    includePaths.push('vendor');
  }
  for (const p of includePaths) {
    const src = join(ROOT, p);
    if (!existsSync(src)) continue;
    cpSync(src, join(stagingPlugin, p), { recursive: true });
  }

  minifyAssets(stagingPlugin, version);

  // The plugin key is public in the shipped plugin but kept out of this
  // public repo: CI passes it in BBMV_PLUGIN_KEY and it is filled in here.
  injectPluginKey(join(stagingPlugin, 'bible-by-midvash.php'));

  // For wp-org build: strip the auto-update block from the staged entry-point.
  if (target === 'wp-org') {
    const stagedPhp = join(stagingPlugin, 'bible-by-midvash.php');
    const original = readFileSync(stagedPhp, 'utf8');
    const stripped = stripWporgBlock(original);
    if (stripped === original) {
      console.warn(`[warn] --target=wp-org but no ${WPORG_STRIP_START} markers found — nothing was stripped`);
    } else {
      writeFileSync(stagedPhp, stripped);
    }
  }

  if (existsSync(outFile)) rmSync(outFile);
  execSync(`cd "${stagingRoot}" && zip -rq "${outFile}" "${SLUG}"`, { stdio: 'inherit' });
  rmSync(stagingRoot, { recursive: true, force: true });

  console.log(`✓ ${outFile} (v${version}, target=${target})`);
}

main();
