---
name: release-check
description: Valida se o repo está pronto pra cortar um release do Bible by Midvash — versões casando nos 3 lugares, changelog, i18n compilado, lint limpo e build do ZIP. Use antes de abrir PR de release ou rodar gh release create.
---

# Release Check — Bible by Midvash

Rode cada etapa abaixo e reporte um checklist final com ✅/❌ por item. Não corrija nada automaticamente — reporte e pergunte antes de corrigir.

## 1. Versões casando (3 lugares)

Extraia e compare — os três DEVEM ser idênticos:

- `bible-by-midvash.php` → linha `* Version: X.Y.Z` do header
- `bible-by-midvash.php` → `define('BBMV_VERSION', 'X.Y.Z')`
- `readme.txt` → `Stable tag: X.Y.Z`

```bash
grep -E '^\s*\*\s*Version:' bible-by-midvash.php
grep "BBMV_VERSION" bible-by-midvash.php
grep "Stable tag:" readme.txt
```

## 2. Changelog e Upgrade Notice

Em `readme.txt`, confirme que a versão-alvo tem:
- Entrada em `== Changelog ==` (`= X.Y.Z =` com bullets)
- Entrada em `== Upgrade Notice ==`

## 3. SemVer coerente

Compare a versão-alvo com a última tag git (`git describe --tags --abbrev=0`). Avalie se o bump (patch/minor/major) condiz com o diff desde a tag — patch pra bugfix, minor pra feature/locale/UI, major pra breaking. Se parecer errado, sinalize.

## 4. i18n em dia

- Regenere o POT (comando canônico no CLAUDE.md, seção i18n) num arquivo temporário e compare com `languages/bible-by-midvash.pot` — se houver strings novas/removidas (além de headers/line numbers), o POT está desatualizado.
- Confirme que existem os 9 pares `.po`/`.mo`: `pt_BR, en_US, es_ES, fr_FR, de_DE, it_IT, ru_RU, ko_KR, zh_CN`.
- Confirme que nenhum `.mo` está mais antigo que seu `.po` (`find languages -name '*.po' -newer` correspondente).
- Rode `msgfmt --statistics` em cada `.po` e sinalize traduções untranslated/fuzzy.

## 5. Lint e análise estática

```bash
~/.composer/vendor/bin/phpcs -q --report=summary .
```

```bash
~/.composer/vendor/bin/phpstan analyse --no-progress
```

Erros novos bloqueiam release; warnings pré-existentes só sinalizar.

## 6. Build do ZIP

Rode `scripts/build-zip.ts` localmente e confirme:
- Build conclui sem erro
- ZIP contém `bible-by-midvash.php`, `includes/`, `assets/`, `languages/`, `vendor/`, `readme.txt`
- ZIP NÃO contém `README.md`, `LICENSE`, `scripts/`, `.github/`, `CLAUDE.md`, `phpcs.xml.dist`, `phpstan.neon*`, `.claude/`

## 7. Git limpo

- Working tree sem mudanças não commitadas relevantes
- Branch atual não é `main` (release sai de branch `release/vX.Y.Z` ou feature branch, via PR)

## Saída

Tabela final: item → ✅/❌ → observação curta. Se tudo ✅, informe o comando sugerido de release:

```
gh release create vX.Y.Z --title "vX.Y.Z — <hook>" --notes "..."
```

Lembre: notas de release ricas em markdown (Added/Fixed/Changed, links pra wordpress.midvash.com quando fizer sentido) — são indexadas.
