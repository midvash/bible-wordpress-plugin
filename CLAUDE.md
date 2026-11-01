# CLAUDE.md — Midvash Bible References

Guia operacional para qualquer agente Claude trabalhando neste repositório. Leia antes de propor mudanças.

## O que é este projeto

Plugin WordPress "Midvash Bible References" (slug `midvash-bible-references`, autor Midvash; até 0.10.3 se chamava "Bible by Midvash", slug `bible-by-midvash`) que detecta referências bíblicas em posts/páginas e as transforma em links com tooltip de versículo, consumindo a API pública em `api.midvash.com`. Sem cadastro, sem chave de API, plug-and-play.

**Distribuição**:
- Repo público: https://github.com/midvash/bible-by-midvash
- Download/docs: https://midvash.com/wordpress-plugin
- Atualização: pelo diretório do wordpress.org (enviado em out/2026, slug `midvash-bible-references`). O canal antigo (R2 + `update-info.json` + `vendor/plugin-update-checker`) ficou congelado na 0.10.3: as instalações antigas, na pasta `bible-by-midvash`, seguem funcionando sem receber o plugin renomeado (trocar o arquivo principal desativaria o plugin). `vendor/` não entra mais no ZIP.

## Objetivos estratégicos

Este repo serve dois propósitos simultâneos — toda decisão deve considerar os dois:

1. **Produto utilizável e estável** para usuários WordPress reais (perf, i18n, compat, UX simples).
2. **Ativo de SEO** para o ecossistema Midvash. Por isso o repo é público mesmo sendo um plugin proprietário. Cada commit, release, issue e PR é uma página indexável que reforça os termos "Bible", "Bíblia", "WordPress plugin", "verse tooltip" associados a Midvash.

Quando os objetivos conflitarem, perguntar ao usuário. Não otimizar SEO ao ponto de poluir o produto, nem o contrário.

## Como o release funciona

Pipeline em [.github/workflows/release.yml](.github/workflows/release.yml), gatilho `release: created`:

1. Lê `Version:` do header de [midvash-bible-references.php](midvash-bible-references.php)
2. Roda [scripts/build-zip.ts](scripts/build-zip.ts) — inclui o PHP principal, `uninstall.php`, `includes/`, `assets/`, `readme.txt`. Exclui `translations/`, `vendor/`, `README.md`, `LICENSE`, `scripts/`, `.github/`, `CLAUDE.md`, qualquer coisa de metadata do repo
3. Anexa `midvash-bible-references-<v>.zip` ao GitHub Release (o mesmo ZIP vai pro wordpress.org)

**Pra cortar um release**, 3 lugares precisam casar (verifique antes de abrir o PR):
- [midvash-bible-references.php](midvash-bible-references.php) → `* Version: X.Y.Z`
- [midvash-bible-references.php](midvash-bible-references.php) → `define( 'MVBR_VERSION', 'X.Y.Z' )`
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
- **Release notes ricas em markdown**, com `Added / Fixed / Changed`. Linkar pra `midvash.com/wordpress-plugin` e `midvash.com` quando fizer sentido. Mencionar locales suportados, versões de Bíblia novas, etc.
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
- Nada de crédito ou link pra Midvash no site público ligado por padrão (guideline 10 do wp.org): logo do tooltip e link do selo ficam atrás de `show_branding`, desligado. O link da referência e o "Ler mais" são a função do plugin.
- Text domain: `midvash-bible-references` (igual ao slug). Sem `load_plugin_textdomain` e sem `Domain Path`: o wordpress.org entrega as traduções (translate.wordpress.org) e o WordPress as carrega sozinho. **Toda string user-facing** passa por `__()`, `_e()`, `esc_html__()`, etc.
- Nada de `echo` direto sem `esc_html` / `esc_attr` / `wp_kses`. Sanitizar input com `sanitize_text_field`, `absint`, etc.
- Nonces em qualquer form de admin. Capabilities corretas (`manage_options` pra settings).
- Prefixo único `mvbr_` / `MVBR_` / `mvbr-` em tudo: funções, classes, constantes, option (`mvbr_options`), transients, nonce e ajax (`mvbr_nonce`, `wp_ajax_mvbr_*`), shortcode `[mvbr_votd]`, widget `mvbr_votd_widget`, handles, classes CSS `.mvbr-` e variáveis `--mvbr-`. O bloco é `midvash-bible-references/verse`. A 1.0.0 trocou o antigo `bbm`/`bbmv` (a revisão do wp.org exige ≥4 letras em tudo); `mvbr_migrate_legacy_data()` copia `bbm_options` e os widgets `bbm_votd_widget` uma vez e apaga os antigos. Renomear de novo só com migração.
- Cache de versículos via Transients API (já implementado). Default 30 dias.
- API calls com `wp_remote_get` + timeout configurável + tratamento de erro silencioso (fallback amigável, não fatal).

### Frontend (assets/)
- Vanilla JS, sem framework. Plugin tem que carregar leve.
- CSS encapsulado por prefixo `.mvbr-` pra não vazar. Valor de opção que vai pro CSS inline é validado na hora (`mvbr_css_color()`, lista fixa pro estilo de sublinhado).
- Suportar dark mode via `prefers-color-scheme`.

### i18n
- 9 locales canônicos (alinhados à extensão Midvash): `pt_BR, en_US, es_ES, fr_FR, de_DE, it_IT, ru_RU, ko_KR, zh_CN`.
- Source of truth: [translations/midvash-bible-references.pot](translations/midvash-bible-references.pot), gerado via `xgettext` da source PHP. Os `.po` ficam em `translations/`, **fora do ZIP** (o wp.org recusa traduções no pacote): servem pra importar no translate.wordpress.org depois da aprovação. Não gerar `.mo`.
- Ao adicionar/mudar strings: regenerar `.pot`, mesclar nos `.po` (`msgmerge`), traduzir, conferir com `msgfmt --statistics -o /dev/null`.
- Comando pra regenerar POT:
  ```bash
  xgettext --from-code=UTF-8 -L PHP \
    --keyword=__ --keyword=_e --keyword=esc_html__ --keyword=esc_attr__ \
    --keyword=esc_html_e --keyword=esc_attr_e \
    --keyword=_x:1,2c --keyword=_n:1,2 --keyword=_nx:1,2,4c \
    -o translations/midvash-bible-references.pot \
    $(find includes midvash-bible-references.php -name '*.php')
  ```

## Estrutura do repo

```
midvash-bible-references.php # entry point + header WP + autoload de includes/ + migração do 0.x
includes/
  class-mvbr-admin.php       # tela de settings, abas, rendering de campos
  class-mvbr-api.php         # cliente HTTP pra api.midvash.com + cache
  class-mvbr-books.php       # mapa de livros bíblicos (pt/en/es) + abreviações
  class-mvbr-parser.php      # regex e substituição de referências por links
  class-mvbr-widget.php      # widget e shortcode do versículo do dia
  class-mvbr-block.php       # bloco Gutenberg do versículo
assets/
  css/, js/, images/        # estilos e scripts de frontend + admin
translations/               # fora do ZIP
  *.po                      # 9 locales, pra importar no translate.wordpress.org
  midvash-bible-references.pot # template canônico
vendor/
  plugin-update-checker/    # canal antigo (R2), não entra mais no ZIP
scripts/
  build-zip.ts              # builda o ZIP (wp.org + GitHub Release)
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
- **Plugin Check (PCP)** — roda via WordPress Playground (sem Docker, **Node 24**) com o blueprint versionado em [scripts/pcp-blueprint.json](scripts/pcp-blueprint.json) (instruções no `meta.description` do próprio arquivo). Rodar contra o conteúdo extraído do ZIP, não contra o repo (o repo acusa falsos positivos de arquivos de dev). Status: ZIP 1.0.0 passa sem erros em PHP 7.4 e 8.3, WP 7.1.4.
- **Teste de fumaça no WordPress** — [scripts/smoke-blueprint.json](scripts/smoke-blueprint.json) + [scripts/smoke.php](scripts/smoke.php): ativa o plugin, renderiza um post, abre configurações, widget e bloco, e acusa qualquer aviso de PHP do plugin.
- **Skill `/release-check`** em [.claude/skills/release-check/SKILL.md](.claude/skills/release-check/SKILL.md) — checklist pré-release (versões nos 3 lugares, changelog, i18n, lint, build).

- **Testes do parser** em [tests/test-parser.php](tests/test-parser.php), sem WordPress nem PHPUnit: `php tests/test-parser.php`. Mudou regex, livro ou URL? Rode e acrescente o caso.

Erro novo de PHPCS/PHPStan ou teste vermelho bloqueia release.

## Pendências pra submeter ao wordpress.org

Só o dono resolve:

- **Chave do plugin.** `MVBR_PLUGIN_KEY` em [midvash-bible-references.php](midvash-bible-references.php) fica vazia no repo (público). O CI preenche a partir do secret `BBMV_PLUGIN_KEY` (nome antigo mantido); build local: `MVBR_PLUGIN_KEY=<chave> npx tsx scripts/build-zip.ts`. Sem ela a API só serve as versões livres.
- **Conta no wordpress.org:** `midvash`, e-mail contact@midvash.com (`Contributors: midvash` no [readme.txt](readme.txt)). `Author: Midvash` / `https://midvash.com` no cabeçalho, pra bater com o domínio do e-mail.
- **Imagens da página do plugin** prontas em [.wordpress-org/](.wordpress-org/) (banner, ícone, 4 screenshots; fontes em `.wordpress-org/src/`). Vão pro `/assets` do SVN do wp.org depois da aprovação, não no ZIP.
- **Secrets do GitHub Actions** (agente não grava secret): `BBMV_PLUGIN_KEY` (a chave, que o `build-zip.ts` injeta no ZIP). Os da Cloudflare não são mais usados pelo workflow.

## Áreas de melhoria conhecidas (backlog informal)

Use como inspiração quando o usuário pedir "o que dá pra melhorar". Não implementar sem pedido explícito.

- **Cobertura de versões bíblicas**: hoje listadas em `readme.txt`. Validar dinamicamente contra a API ao gerar o catálogo.
- **Suporte a custom post types** além de `post`/`page` (CPT comum em sites Bíblia/devocional).
- **Bloco Gutenberg** dedicado pra inserir referência (hoje é só auto-parse no conteúdo).
- **Widget de "versículo do dia"** consumindo a API.
- **Schema.org** (`BibleVerse` / `Quotation`) injetado nos links — bom pra SEO dos sites que usam o plugin **e** pra SEO de Midvash via citações.
- **Testes**: nem PHPUnit nem Playwright hoje. Considerar testes mínimos de parser (regex de referências).
- **Submissão ao wordpress.org**: o `readme.txt` já está em formato compatível. Quando estabilizar 1.0.0, vale o esforço de submeter — multiplica alcance e SEO drasticamente.
- **Sitemap/feed de releases** linkado em `midvash.com/wordpress-plugin` pra ajudar indexação.

## Convenções com o agente

- Sempre confirmar antes de: push em main, force-push, deletar branch remota, criar release, alterar workflow CI, mexer em `vendor/`.
- Pode rodar livre: edits locais, branch novo, commit local, `xgettext`/`msgfmt`, build local do ZIP, leitura de R2 (não escrita).
- Quando bumpar versão: lembrar dos 3 lugares (header, `MVBR_VERSION`, `readme.txt`). Esquecer um dos três quebra o build OU o display no admin.
- Quando adicionar string nova traduzível: regenerar `.pot`, atualizar os 9 `.po`. Não deixar tradução faltando em release.
- Não inventar features sem pedido. Backlog acima é referência, não autorização.
