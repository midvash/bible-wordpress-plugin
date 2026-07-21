# Bible by Midvash — WordPress plugin

> 🌐 [English](./README.md) · **Português (BR)** · [Español](./README.es.md)

[![License: GPL v2+](https://img.shields.io/badge/License-GPL%20v2%2B-blue.svg)](LICENSE)
[![WordPress](https://img.shields.io/badge/WordPress-6.0%2B-21759b.svg)](https://wordpress.org/)

Detecta automaticamente referências bíblicas nos seus posts do WordPress e as transforma em links com tooltip ao passar o mouse — sem chave de API, sem cadastro, sem configuração além de instalar o plugin.

> Download e documentação em **[midvash.app/wordpress-plugin](https://midvash.app/wordpress-plugin)**

## O que faz

- Reconhece referências como `John 3:16`, `Jo 3.16`, `Salmos 23`, `Rom 8:28-30` — em **inglês, português e espanhol**, com acentos e abreviações de livros.
- Substitui por links sutis para [midvash.com](https://midvash.com) no frontend, abrindo o versículo completo em um tooltip ao passar o mouse.
- 38 versões bíblicas para escolher como padrão do seu site (NVT, NVI, NLT, KJV, RVR1960 e mais).
- Cor do link, estilo de sublinhado e comportamento do tooltip personalizáveis.
- Grátis para sempre, sem necessidade de conta.

## Instalação

### Pelo site [midvash.app/wordpress-plugin](https://midvash.app/wordpress-plugin)

1. Baixe o `.zip` mais recente em [midvash.app/wordpress-plugin](https://midvash.app/wordpress-plugin)
2. Admin do WordPress → Plugins → Adicionar Novo → Enviar Plugin → selecione o zip
3. Ative e configure em **Configurações → Bible by Midvash**

### Pelo código-fonte (este repo)

```bash
cd wp-content/plugins/
git clone https://github.com/midvash/bible-wordpress-plugin.git bible-by-midvash
```

Depois ative no admin do WordPress.

## Atualizações

A atualização automática vem embutida via [Plugin Update Checker](https://github.com/YahnisElsts/plugin-update-checker). O plugin verifica `midvash.app/api/wordpress/update-info.json` a cada ~12 horas e exibe o banner padrão "Atualização disponível" no admin do WordPress, como os plugins do diretório oficial.

## Arquitetura

- **`bible-by-midvash.php`** — arquivo principal do plugin, hooks, endpoints AJAX, limpeza do cache de transients
- **`includes/class-bbmv-parser.php`** — detecção de referências (regex + dicionários de nomes de livros por locale)
- **`includes/class-bbmv-api.php`** — chama a API pública do Midvash (somente leitura, sem auth) com tratamento de rate-limit e cache de transients
- **`includes/class-bbmv-admin.php`** — tela de configurações
- **`includes/class-bbmv-books.php`** — metadados dos livros (nomes, abreviações, capítulos) por idioma
- **`assets/js/bbm-tooltip.js`** — renderização do tooltip no frontend
- **`languages/`** — arquivos de tradução `.po`/`.mo`

## Segurança

Higiene padrão de segurança do WordPress aplicada:

- Todos os endpoints AJAX protegidos por `wp_nonce` (`check_ajax_referer`)
- Todos os pontos de entrada de arquivo protegidos por `ABSPATH`
- Toda entrada do usuário sanitizada (`sanitize_text_field` + `wp_unslash`)
- Toda saída escapada (`esc_html`, `esc_attr`, `esc_url`)
- Configurações do admin exigem a capability `manage_options`
- Sem execução de código externo, sem `eval`, sem chamadas de shell
- Chamadas de API somente por HTTPS com `sslverify = true`

O plugin apenas **lê** da API pública do Midvash — não envia nenhum conteúdo de post, dado de usuário ou telemetria.

## Contribuindo

PRs são bem-vindos — especialmente para:

- Suporte a novos idiomas (nomes de livros + ajustes de regex em `class-bbmv-books.php` e `class-bbmv-parser.php`)
- Arquivos de tradução `.po` em `languages/`
- Melhorias de performance

Abra uma issue primeiro para mudanças maiores.

## Licença

GPL v2 ou posterior — veja [LICENSE](LICENSE). Bibliotecas embarcadas mantêm suas licenças originais (veja `vendor/plugin-update-checker/license.txt`).

## Projetos relacionados

- **[bible-data](https://github.com/midvash/bible-data)** — o conjunto de dados bíblicos de domínio público (33 versões, 22 idiomas) por trás do leitor Midvash
- **[bible-data-js](https://github.com/midvash/bible-data-js)** — SDK TypeScript para o conjunto de dados
- **[bible-cross-references](https://github.com/midvash/bible-cross-references)** — 453 referências cruzadas temáticas selecionadas
- **[Midvash](https://midvash.com)** — o leitor bíblico online para o qual este plugin linka

## O ecossistema Midvash

Faz parte do [**Midvash**](https://midvash.com) — uma plataforma gratuita de leitura e estudo bíblico. Tudo é aberto e se interliga:

| | |
|---|---|
| 📖 **Leitor (web)** | [midvash.com](https://midvash.com) — 9 idiomas |
| 📱 **App iOS** | [midvash.app/ios](https://midvash.app/ios) |
| 🔌 **API** | [api.midvash.com](https://api.midvash.com) · [`bible-api`](https://github.com/midvash/bible-api) |
| 🤖 **Servidor MCP** | [mcp.midvash.com](https://mcp.midvash.com) · [`bible-mcp`](https://github.com/midvash/bible-mcp) |
| 🧩 **Plugin WordPress** | [midvash.app/wordpress-plugin](https://midvash.app/wordpress-plugin) · [`bible-wordpress-plugin`](https://github.com/midvash/bible-wordpress-plugin) |
| 🧩 **Plugin EmDash** | [midvash.app/emdash-plugin](https://midvash.app/emdash-plugin) · [`emdash-plugin-bible`](https://github.com/midvash/emdash-plugin-bible) |
| 🌐 **Extensão Chrome** | [midvash.app/chrome-extension](https://midvash.app/chrome-extension) · [`bible-chrome-extension`](https://github.com/midvash/bible-chrome-extension) |
| 📦 **Dados abertos** | [`bible-data`](https://github.com/midvash/bible-data) · [`bible-data-js`](https://github.com/midvash/bible-data-js) · [`bible-cross-references`](https://github.com/midvash/bible-cross-references) |

<sub>Gratuito e aberto, feito pela [Midvash](https://midvash.com) · [midvash.com](https://midvash.com) · [midvash.app](https://midvash.app)</sub>
