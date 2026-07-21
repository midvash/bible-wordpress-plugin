# CLAUDE.md — Bible by Midvash

Guia operacional para qualquer agente Claude trabalhando neste repositório. Leia antes de propor mudanças.

## O que é este projeto

Plugin WordPress (`bible-by-midvash`) que detecta referências bíblicas em posts/páginas e as transforma em links com tooltip de versículo, consumindo a API pública em `api.midvash.com`. Sem cadastro, sem chave de API, plug-and-play.

**Distribuição**:
- Repo público: https://github.com/midvash/bible-by-midvash
- Download/docs: https://wordpress.midvash.com
- Auto-update via `vendor/plugin-update-checker` lendo `update-info.json` em R2

## Objetivos estratégicos

Este repo serve dois propósitos simultâneos — toda decisão deve considerar os dois:

1. **Produto utilizável e estável** para usuários WordPress reais (perf, i18n, compat, UX simples).
2. **Ativo de SEO** para o ecossistema Midvash. Por isso o repo é público mesmo sendo um plugin proprietário. Cada commit, release, issue e PR é uma página indexável que reforça os termos "Bible", "Bíblia", "WordPress plugin", "verse tooltip" associados a Midvash.

Quando os objetivos conflitarem, perguntar ao usuário. Não otimizar SEO ao ponto de poluir o produto, nem o contrário.

## Como o release funciona

Pipeline em [.github/workflows/release.yml](.github/workflows/release.yml), gatilho `release: created`:

1. Lê `Version:` do header de [bible-by-midvash.php](bible-by-midvash.php)
2. Roda [scripts/build-zip.ts](scripts/build-zip.ts) — inclui `php`, `includes/`, `assets/`, `languages/`, `vendor/`, `readme.txt`. Exclui `README.md`, `LICENSE`, `scripts/`, `.github/`, `CLAUDE.md`, qualquer coisa de metadata do repo
3. Sobe ao R2: `bible-by-midvash-<v>.zip`, `bible-by-midvash-latest.zip`, `update-info.json`
4. Anexa o ZIP ao GitHub Release

**Pra cortar um release**, 3 lugares precisam casar (verifique antes de abrir o PR):
- [bible-by-midvash.php](bible-by-midvash.php) → `* Version: X.Y.Z`
- [bible-by-midvash.php](bible-by-midvash.php) → `define('BBMV_VERSION', 'X.Y.Z')`
- [readme.txt](readme.txt) → `Stable tag: X.Y.Z` + entrada em `== Changelog ==` + entrada em `== Upgrade Notice ==`

**SemVer**:
- `patch` (0.0.x): bugfix, ajuste de string, tradução pontual
- `minor` (0.x.0): nova feature, novo locale, mudança de UI visível
- `major` (x.0.0): breaking change (opções renomeadas, remoção de shortcode, mudança de comportamento default)

**Fluxo padrão**:
1. Branch `release/vX.Y.Z` (ou feature branch comum)
2. Commit → PR pra `main` → merge
3. `gh release create vX.Y.Z --title "vX.Y.Z — <hook>" --notes "..."` (notas em markdown, ricas — vão pro feed de releases que é indexado)

**Nunca** commitar direto em `main` sem PR, exceto hotfix crítico já discutido com o usuário. PRs são o sinal público de atividade.

## Práticas pró-SEO

Tratar o repo como produto editorial. Em qualquer mudança, considere:

- **Commits descritivos em inglês ou português natural** — não "fix stuff". Cada commit é uma página indexada.
- **PR titles e descrições completas** com seções `## Summary` / `## Test plan`. Mencionar termos relevantes (Bible, WordPress, multilingual, tooltip, verse, biblia, versículo) quando naturais.
- **Release notes ricas em markdown**, com `Added / Fixed / Changed`. Linkar pra `wordpress.midvash.com` e `midvash.com` quando fizer sentido. Mencionar locales suportados, versões de Bíblia novas, etc.
- **README.md** mantém o pitch público com keywords naturais, badges e links pra docs. Não inchar — é página de entrada, não manual.
- **readme.txt** segue rigorosamente o formato wordpress.org (mesmo não estando lá ainda) para preservar a opção de submeter. Tags, `Stable tag`, FAQ, Changelog, Upgrade Notice.
- **Issues e Discussions abertos** — encorajar uso. Cada issue é uma página. Se o usuário abrir issue genuíno em outro canal, sugerir migrar pro GitHub.
- **Não usar `--no-verify`** em commits — hooks rodam por algum motivo.

O que **não** fazer pelo SEO:
- Encher código com comentários keyword-stuffed
- Criar arquivos `.md` artificiais (CONTRIBUTING fake, ROADMAP especulativo) sem utilidade real
- Spam de commits sintéticos pra inflar histórico

Conteúdo orgânico, regular e relevante > volume artificial.

## Padrões de código

### PHP / WordPress
- Compatível com **WP 5.0+** e **PHP 7.4+** (declarado em `readme.txt`). Não usar features de PHP 8+ sem polyfill.
- Text domain: `bible-by-midvash`. **Toda string user-facing** passa por `__()`, `_e()`, `esc_html__()`, etc.
- Nada de `echo` direto sem `esc_html` / `esc_attr` / `wp_kses`. Sanitizar input com `sanitize_text_field`, `absint`, etc.
- Nonces em qualquer form de admin. Capabilities corretas (`manage_options` pra settings).
- Prefixo `bbmv_` / `BBMV_` em funções, classes e constantes (wp.org exige ≥4 chars). **Exceção intencional**: nomes já persistidos ou user-facing continuam `bbm` — options no banco (`bbm_options`), nonces/ajax actions (`bbm_nonce`, `wp_ajax_bbm_*`), shortcode `[bbm_votd]`, classes CSS `.bbm-` e handles de assets. Não renomear esses sem migração.
- Cache de versículos via Transients API (já implementado). Default 30 dias.
- API calls com `wp_remote_get` + timeout configurável + tratamento de erro silencioso (fallback amigável, não fatal).

### Frontend (assets/)
- Vanilla JS, sem framework. Plugin tem que carregar leve.
- CSS encapsulado por prefixo `.bbm-` pra não vazar.
- Suportar dark mode via `prefers-color-scheme`.

### i18n
- 9 locales canônicos (alinhados à extensão Midvash): `pt_BR, en_US, es_ES, fr_FR, de_DE, it_IT, ru_RU, ko_KR, zh_CN`.
- Source of truth: [languages/bible-by-midvash.pot](languages/bible-by-midvash.pot), gerado via `xgettext` da source PHP.
- Ao adicionar/mudar strings: regenerar `.pot`, mesclar nos `.po` (`msgmerge`), traduzir, recompilar `.mo` com `msgfmt`.
- Comando pra regenerar POT:
  ```bash
  xgettext --from-code=UTF-8 -L PHP \
    --keyword=__ --keyword=_e --keyword=esc_html__ --keyword=esc_attr__ \
    --keyword=esc_html_e --keyword=esc_attr_e \
    --keyword=_x:1,2c --keyword=_n:1,2 --keyword=_nx:1,2,4c \
    -o languages/bible-by-midvash.pot \
    $(find includes bible-by-midvash.php -name '*.php')
  ```

## Estrutura do repo

```
bible-by-midvash.php        # entry point + header WP + autoload de includes/
includes/
  class-bbmv-admin.php       # tela de settings, abas, rendering de campos
  class-bbmv-api.php         # cliente HTTP pra api.midvash.com + cache
  class-bbmv-books.php       # mapa de livros bíblicos (pt/en/es) + abreviações
  class-bbmv-parser.php      # regex e substituição de referências por links
assets/
  css/, js/, images/        # estilos e scripts de frontend + admin
languages/
  *.po / *.mo               # 9 locales
  bible-by-midvash.pot      # template canônico
vendor/
  plugin-update-checker/    # auto-update via update-info.json
scripts/
  build-zip.ts              # builda o ZIP final que vai pro R2
.github/workflows/
  release.yml               # CI de release
readme.txt                  # readme estilo wordpress.org
README.md                   # readme público do GitHub
```

Manter essa convenção. Não criar diretórios novos sem motivo claro (ex: `docs/`, `tests/` só se houver conteúdo real).

## Ferramentas de qualidade

Instaladas globalmente via `composer global` (não estão em `vendor/` de propósito — `vendor/` é commitado e vai no ZIP). Configs versionadas no repo:

- **PHPCS + WordPress Coding Standards + PHPCompatibilityWP** — config em [phpcs.xml.dist](phpcs.xml.dist). Rodar: `~/.composer/vendor/bin/phpcs -q --report=summary .`
- **PHPStan (level 5) + extensão WordPress** — config em [phpstan.neon.dist](phpstan.neon.dist). Rodar: `~/.composer/vendor/bin/phpstan analyse --no-progress --memory-limit=1G`
- **Plugin Check (PCP)** — roda via WordPress Playground (sem Docker) com o blueprint versionado em [scripts/pcp-blueprint.json](scripts/pcp-blueprint.json) (instruções de uso no `$comment` do próprio arquivo). Rodar contra o conteúdo extraído do ZIP `-wporg`, não contra o repo (o repo acusa falsos positivos de arquivos de dev). Status: ZIP wporg 0.6.0 passa sem erros.
- **Skill `/release-check`** em [.claude/skills/release-check/SKILL.md](.claude/skills/release-check/SKILL.md) — checklist pré-release (versões nos 3 lugares, changelog, i18n, lint, build).

Erro novo de PHPCS/PHPStan bloqueia release.

## Áreas de melhoria conhecidas (backlog informal)

Use como inspiração quando o usuário pedir "o que dá pra melhorar". Não implementar sem pedido explícito.

- **Cobertura de versões bíblicas**: hoje listadas em `readme.txt`. Validar dinamicamente contra a API ao gerar o catálogo.
- **Suporte a custom post types** além de `post`/`page` (CPT comum em sites Bíblia/devocional).
- **Bloco Gutenberg** dedicado pra inserir referência (hoje é só auto-parse no conteúdo).
- **Widget de "versículo do dia"** consumindo a API.
- **Schema.org** (`BibleVerse` / `Quotation`) injetado nos links — bom pra SEO dos sites que usam o plugin **e** pra SEO de Midvash via citações.
- **Testes**: nem PHPUnit nem Playwright hoje. Considerar testes mínimos de parser (regex de referências).
- **Submissão ao wordpress.org**: o `readme.txt` já está em formato compatível. Quando estabilizar 1.0.0, vale o esforço de submeter — multiplica alcance e SEO drasticamente.
- **Sitemap/feed de releases** linkado em `wordpress.midvash.com` pra ajudar indexação.

## Convenções com o agente

- Sempre confirmar antes de: push em main, force-push, deletar branch remota, criar release, alterar workflow CI, mexer em `vendor/`.
- Pode rodar livre: edits locais, branch novo, commit local, `xgettext`/`msgfmt`, build local do ZIP, leitura de R2 (não escrita).
- Quando bumpar versão: lembrar dos 3 lugares (header, `BBMV_VERSION`, `readme.txt`). Esquecer um dos três quebra auto-update OU build OU display no admin.
- Quando adicionar string nova traduzível: regenerar `.pot`, atualizar os 9 `.po`, recompilar `.mo`. Não deixar tradução faltando em release.
- Não inventar features sem pedido. Backlog acima é referência, não autorização.
