#!/usr/bin/env tsx
/**
 * Sincroniza dados de livros e versões a partir da API pública do Midvash.
 *
 * - Busca https://api.midvash.com/books → reescreve init_books() entre os
 *   marcadores `// {{SYNCED_BOOKS_START}}` e `// {{SYNCED_BOOKS_END}}` em
 *   includes/class-bbmv-books.php.
 * - Busca https://api.midvash.com/versions → reescreve o conteúdo de
 *   DEFAULT_VERSIONS entre `// {{SYNCED_VERSIONS_START}}` e
 *   `// {{SYNCED_VERSIONS_END}}`.
 *
 * Idempotente: rodar de novo sem mudança na API não gera diff.
 *
 * Uso: `npx tsx scripts/sync-books.ts`
 */

import { readFileSync, writeFileSync } from 'node:fs';
import { join, resolve } from 'node:path';

const ROOT = resolve(__dirname, '..');
const BOOKS_PHP = join(ROOT, 'includes', 'class-bbmv-books.php');

const LOCALES = ['en', 'pt-br', 'es', 'fr', 'de', 'it', 'ru', 'ko', 'zh'] as const;
type Locale = (typeof LOCALES)[number];

const PREFERRED_VERSIONS: Record<Locale, string> = {
  en: 'bsb',
  'pt-br': 'onbv',
  es: 'rvr1909',
  fr: 'lsg',
  de: 'luth1912',
  it: 'nri',
  ru: 'synodal',
  ko: 'kor',
  zh: 'cuv',
};

interface ApiBook {
  id: number;
  chapters: number;
  testament: 'old' | 'new';
  category?: string;
  name: Partial<Record<Locale, string>>;
  slug: Partial<Record<Locale, string>>;
  abbrev: Partial<Record<Locale, string>>;
}

interface ApiVersion {
  slug: string;
  name: string;
  shortName: string;
  language: string;
}

// Versões que a API pública deixou de distribuir (direitos reservados, sem
// licença). Espelha BBMV_Books::RETIRED_VERSIONS; nunca viram padrão, mesmo
// que a API ainda as liste durante a transição.
const RETIRED_VERSIONS = new Set([
  'aa', 'jfaa', 'acf', 'ara', 'arc', 'as21', 'naa', 'kjf', 'bpt',
  'nvi', 'nvt', 'ntlh', 'nbv', 'kja', 'msgpt', 'ol',
  'esv', 'niv', 'nlt', 'msg', 'nkjv',
  'ntv', 'nvies', 'rvr1960',
  'nvl', 'bhs', 'mh',
]);

async function fetchJson<T>(url: string): Promise<T> {
  const res = await fetch(url);
  if (!res.ok) {
    throw new Error(`GET ${url} → ${res.status} ${res.statusText}`);
  }
  return (await res.json()) as T;
}

function phpString(s: string): string {
  // PHP single-quoted strings only escape \\ and \'
  return "'" + s.replace(/\\/g, '\\\\').replace(/'/g, "\\'") + "'";
}

function phpLocaleMap(m: Partial<Record<Locale, string>>): string {
  const parts = LOCALES.map((loc) => `'${loc}' => ${phpString(m[loc] ?? '')}`);
  return `array(${parts.join(', ')})`;
}

function generateBooksBlock(books: ApiBook[]): string {
  books.sort((a, b) => a.id - b.id);
  const lines: string[] = [];
  let currentTestament = '';
  for (const book of books) {
    if (book.testament !== currentTestament) {
      if (lines.length > 0) lines.push('');
      lines.push(book.testament === 'old' ? '            // Old Testament' : '            // New Testament');
      currentTestament = book.testament;
    }
    lines.push(`            ${book.id} => array(`);
    lines.push(`                'id' => ${book.id},`);
    lines.push(`                'chapters' => ${book.chapters},`);
    lines.push(`                'testament' => '${book.testament}',`);
    lines.push(`                'slugs' => ${phpLocaleMap(book.slug)},`);
    lines.push(`                'names' => ${phpLocaleMap(book.name)},`);
    lines.push(`                'abbrev' => ${phpLocaleMap(book.abbrev)},`);
    lines.push(`            ),`);
  }
  return lines.join('\n');
}

function generateVersionsBlock(defaults: Record<Locale, string>): string {
  const lines: string[] = [];
  for (const loc of LOCALES) {
    lines.push(`        '${loc}' => '${defaults[loc]}',`);
  }
  return lines.join('\n');
}

function replaceBetweenMarkers(
  source: string,
  startMarker: string,
  endMarker: string,
  replacement: string,
): string {
  const startIdx = source.indexOf(startMarker);
  const endIdx = source.indexOf(endMarker);
  if (startIdx === -1 || endIdx === -1) {
    throw new Error(`Markers not found: ${startMarker} / ${endMarker}`);
  }
  // Preserve indentation of the END marker line
  const lineStart = source.lastIndexOf('\n', endIdx) + 1;
  const endIndent = source.slice(lineStart, endIdx);
  const before = source.slice(0, startIdx + startMarker.length);
  const after = source.slice(endIdx);
  return before + '\n' + replacement + '\n' + endIndent + after;
}

function pickDefaultVersion(
  locale: Locale,
  versions: ApiVersion[],
): { slug: string; warning?: string } {
  const inLocale = versions.filter(
    (v) => v.language === locale && !RETIRED_VERSIONS.has(v.slug.toLowerCase()),
  );
  if (inLocale.length === 0) {
    return { slug: PREFERRED_VERSIONS[locale], warning: `[warn] no versions in API for locale '${locale}'; using preferred '${PREFERRED_VERSIONS[locale]}'` };
  }
  const preferred = PREFERRED_VERSIONS[locale];
  const match = inLocale.find((v) => v.slug.toLowerCase() === preferred);
  if (match) return { slug: match.slug.toLowerCase() };
  return {
    slug: inLocale[0].slug.toLowerCase(),
    warning: `[warn] preferred '${preferred}' not in API for '${locale}'; falling back to first available '${inLocale[0].slug}'`,
  };
}

async function main() {
  console.log('Fetching books from https://api.midvash.com/books ...');
  const booksRes = await fetchJson<{ books: ApiBook[] }>('https://api.midvash.com/books');
  if (!Array.isArray(booksRes.books)) {
    throw new Error('Unexpected API response shape: missing `books` array');
  }
  const books = booksRes.books;

  // Validate locale coverage
  for (const book of books) {
    const missing: string[] = [];
    for (const loc of LOCALES) {
      if (!book.name?.[loc]) missing.push(`name.${loc}`);
      if (!book.slug?.[loc]) missing.push(`slug.${loc}`);
      if (!book.abbrev?.[loc]) missing.push(`abbrev.${loc}`);
    }
    if (missing.length > 0) {
      console.warn(`[warn] book ${book.id} missing: ${missing.join(', ')}`);
    }
  }

  console.log('Fetching versions from https://api.midvash.com/versions ...');
  const versionsRes = await fetchJson<{ versions: ApiVersion[] }>('https://api.midvash.com/versions');
  if (!Array.isArray(versionsRes.versions)) {
    throw new Error('Unexpected API response shape: missing `versions` array');
  }

  const defaults: Record<Locale, string> = {} as Record<Locale, string>;
  for (const loc of LOCALES) {
    const picked = pickDefaultVersion(loc, versionsRes.versions);
    defaults[loc] = picked.slug;
    if (picked.warning) console.warn(picked.warning);
  }

  // Edit the PHP file
  let php = readFileSync(BOOKS_PHP, 'utf8');
  php = replaceBetweenMarkers(
    php,
    '// {{SYNCED_VERSIONS_START}}',
    '// {{SYNCED_VERSIONS_END}}',
    generateVersionsBlock(defaults),
  );
  php = replaceBetweenMarkers(
    php,
    '// {{SYNCED_BOOKS_START}}',
    '// {{SYNCED_BOOKS_END}}',
    generateBooksBlock(books),
  );
  writeFileSync(BOOKS_PHP, php);

  const defaultsSummary = LOCALES.map((l) => `${l}=${defaults[l]}`).join(', ');
  console.log(`Synced ${books.length} books across ${LOCALES.length} locales; defaults: ${defaultsSummary}`);
}

main().catch((err) => {
  console.error(err);
  process.exit(1);
});
