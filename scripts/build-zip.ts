#!/usr/bin/env tsx
/**
 * Empacota o plugin "Midvash Bible References" num ZIP pronto pra distribuir.
 *
 *   `npx tsx scripts/build-zip.ts` → `dist/midvash-bible-references-{version}.zip`
 *
 * É o mesmo ZIP pro diretório do WordPress.org e pro GitHub Release: sem
 * updater próprio (as Plugin Review Guidelines vedam) e sem `.po`/`.mo` (o
 * translate.wordpress.org entrega as traduções). Os `.po` ficam em
 * `translations/`, fora do ZIP.
 *
 * Entrada/saída:
 *   - Versão lida do header `Version:` em `midvash-bible-references.php`.
 *   - Estrutura interna do zip: `midvash-bible-references/` (slug oficial — WP exige).
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
const PLUGIN_PHP = join(ROOT, 'midvash-bible-references.php');
const DIST_DIR = join(ROOT, 'dist');
const SLUG = 'midvash-bible-references';

function readVersion(): string {
  if (!existsSync(PLUGIN_PHP)) {
    throw new Error(`Plugin PHP not found at ${PLUGIN_PHP}`);
  }
  const content = readFileSync(PLUGIN_PHP, 'utf8');
  const match = content.match(/^\s*\*\s*Version:\s*([0-9]+\.[0-9]+\.[0-9]+)/m);
  if (!match) {
    throw new Error('Could not parse `Version:` header in midvash-bible-references.php');
  }
  return match[1];
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

const KEY_LINE = /define\( 'MVBR_PLUGIN_KEY', '' \);/;

/** Fills an empty MVBR_PLUGIN_KEY from the environment, if one is given. */
function injectPluginKey(phpFile: string) {
  const key = process.env.MVBR_PLUGIN_KEY ?? '';
  if (!key) return;
  if (!/^[A-Za-z0-9]+$/.test(key)) throw new Error('MVBR_PLUGIN_KEY must be alphanumeric');
  const php = readFileSync(phpFile, 'utf8');
  if (!KEY_LINE.test(php)) {
    console.warn('[warn] MVBR_PLUGIN_KEY já preenchida no arquivo; env ignorada');
    return;
  }
  writeFileSync(phpFile, php.replace(KEY_LINE, `define( 'MVBR_PLUGIN_KEY', '${key}' );`));
}

function main() {
  const version = readVersion();
  const stagingRoot = join(tmpdir(), `midvash-wp-build-${Date.now()}`);
  const stagingPlugin = join(stagingRoot, SLUG);
  const outFile = join(DIST_DIR, `${SLUG}-${version}.zip`);

  mkdirSync(DIST_DIR, { recursive: true });
  mkdirSync(stagingPlugin, { recursive: true });

  // Files to include — plugin code only, no repo metadata (README.md, LICENSE, scripts, .github, translations)
  const includePaths = ['midvash-bible-references.php', 'readme.txt', 'uninstall.php', 'includes', 'assets'];
  for (const p of includePaths) {
    const src = join(ROOT, p);
    if (!existsSync(src)) continue;
    cpSync(src, join(stagingPlugin, p), { recursive: true });
  }

  minifyAssets(stagingPlugin, version);

  // The plugin key is public in the shipped plugin but kept out of this
  // public repo: CI passes it in MVBR_PLUGIN_KEY and it is filled in here.
  injectPluginKey(join(stagingPlugin, 'midvash-bible-references.php'));

  if (existsSync(outFile)) rmSync(outFile);
  execSync(`cd "${stagingRoot}" && zip -rq "${outFile}" "${SLUG}"`, { stdio: 'inherit' });
  rmSync(stagingRoot, { recursive: true, force: true });

  console.log(`✓ ${outFile} (v${version})`);
}

main();
