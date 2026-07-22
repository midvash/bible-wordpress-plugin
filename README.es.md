# Bible by Midvash — WordPress plugin

> 🌐 [English](./README.md) · [Português (BR)](./README.pt-BR.md) · **Español**

[![License: GPL v2+](https://img.shields.io/badge/License-GPL%20v2%2B-blue.svg)](LICENSE)
[![WordPress](https://img.shields.io/badge/WordPress-6.0%2B-21759b.svg)](https://wordpress.org/)

Detecta automáticamente referencias bíblicas en tus entradas de WordPress y las convierte en enlaces con tooltip al pasar el cursor — sin clave de API, sin registro, sin configuración más allá de instalar el plugin.

> Descarga y documentación en **[midvash.app/wordpress-plugin](https://midvash.app/wordpress-plugin)**

## Qué hace

- Reconoce referencias como `John 3:16`, `Jo 3.16`, `Salmos 23`, `Rom 8:28-30` — en **inglés, portugués y español**, con acentos y abreviaturas de libros.
- Las reemplaza con enlaces sutiles a [midvash.com](https://midvash.com) en el frontend, abriendo el versículo completo en un tooltip al pasar el cursor.
- 50+ versiones bíblicas para elegir como predeterminada de tu sitio (NVT, NVI, NLT, KJV, RVR1960 y más).
- Color del enlace, estilo de subrayado y comportamiento del tooltip personalizables.
- Gratis para siempre, sin necesidad de cuenta.

## Instalación

### Desde el sitio [midvash.app/wordpress-plugin](https://midvash.app/wordpress-plugin)

1. Descarga el `.zip` más reciente en [midvash.app/wordpress-plugin](https://midvash.app/wordpress-plugin)
2. Admin de WordPress → Plugins → Añadir nuevo → Subir plugin → selecciona el zip
3. Activa y configura en **Ajustes → Bible by Midvash**

### Desde el código fuente (este repo)

```bash
cd wp-content/plugins/
git clone https://github.com/midvash/bible-wordpress-plugin.git bible-by-midvash
```

Luego actívalo en el admin de WordPress.

## Actualizaciones

La actualización automática viene integrada mediante [Plugin Update Checker](https://github.com/YahnisElsts/plugin-update-checker). El plugin verifica `midvash.app/api/wordpress/update-info.json` cada ~12 horas y muestra el banner estándar "Actualización disponible" en el admin de WordPress, como los plugins del directorio oficial.

## Arquitectura

- **`bible-by-midvash.php`** — archivo principal del plugin, hooks, endpoints AJAX, limpieza de la caché de transients
- **`includes/class-bbmv-parser.php`** — detección de referencias (regex + diccionarios de nombres de libros por locale)
- **`includes/class-bbmv-api.php`** — llama a la API pública de Midvash (solo lectura, sin auth) con manejo de rate-limit y caché de transients
- **`includes/class-bbmv-admin.php`** — pantalla de ajustes
- **`includes/class-bbmv-books.php`** — metadatos de los libros (nombres, abreviaturas, capítulos) por idioma
- **`assets/js/bbm-tooltip.js`** — renderizado del tooltip en el frontend
- **`languages/`** — archivos de traducción `.po`/`.mo`

## Seguridad

Higiene de seguridad estándar de WordPress aplicada:

- Todos los endpoints AJAX protegidos por `wp_nonce` (`check_ajax_referer`)
- Todos los puntos de entrada de archivo protegidos por `ABSPATH`
- Toda entrada del usuario sanitizada (`sanitize_text_field` + `wp_unslash`)
- Toda salida escapada (`esc_html`, `esc_attr`, `esc_url`)
- Los ajustes del admin requieren la capability `manage_options`
- Sin ejecución de código externo, sin `eval`, sin llamadas de shell
- Llamadas a la API solo por HTTPS con `sslverify = true`

El plugin solo **lee** de la API pública de Midvash — no envía ningún contenido de entrada, dato de usuario ni telemetría.

## Contribuir

Los PRs son bienvenidos — especialmente para:

- Soporte de nuevos idiomas (nombres de libros + ajustes de regex en `class-bbmv-books.php` y `class-bbmv-parser.php`)
- Archivos de traducción `.po` en `languages/`
- Mejoras de rendimiento

Abre un issue primero para cambios mayores.

## Licencia

GPL v2 o posterior — consulta [LICENSE](LICENSE). Las bibliotecas incluidas conservan sus licencias originales (consulta `vendor/plugin-update-checker/license.txt`).

## Proyectos relacionados

- **[bible-data](https://github.com/midvash/bible-data)** — el conjunto de datos bíblicos de dominio público (33 versiones, 22 idiomas) detrás del lector Midvash
- **[bible-data-js](https://github.com/midvash/bible-data-js)** — SDK TypeScript para el conjunto de datos
- **[bible-cross-references](https://github.com/midvash/bible-cross-references)** — 453 referencias cruzadas temáticas seleccionadas
- **[Midvash](https://midvash.com)** — el lector bíblico en línea al que enlaza este plugin

## El ecosistema Midvash

Parte de [**Midvash**](https://midvash.com) — una plataforma gratuita de lectura y estudio bíblico. Todo es abierto y se interconecta:

| | |
|---|---|
| 📖 **Lector (web)** | [midvash.com](https://midvash.com) — 9 idiomas |
| 📱 **App iOS** | [midvash.app/ios](https://midvash.app/ios) |
| 🔌 **API** | [api.midvash.com](https://api.midvash.com) · [`bible-api`](https://github.com/midvash/bible-api) |
| 🤖 **Servidor MCP** | [mcp.midvash.com](https://mcp.midvash.com) · [`bible-mcp`](https://github.com/midvash/bible-mcp) |
| 🧩 **Plugin de WordPress** | [midvash.app/wordpress-plugin](https://midvash.app/wordpress-plugin) · [`bible-wordpress-plugin`](https://github.com/midvash/bible-wordpress-plugin) |
| 🧩 **Plugin de EmDash** | [midvash.app/emdash-plugin](https://midvash.app/emdash-plugin) · [`emdash-plugin-bible`](https://github.com/midvash/emdash-plugin-bible) |
| 🌐 **Extensión de Chrome** | [midvash.app/chrome-extension](https://midvash.app/chrome-extension) · [`bible-chrome-extension`](https://github.com/midvash/bible-chrome-extension) |
| 📦 **Datos abiertos** | [`bible-data`](https://github.com/midvash/bible-data) · [`bible-data-js`](https://github.com/midvash/bible-data-js) · [`bible-cross-references`](https://github.com/midvash/bible-cross-references) |

<sub>Gratuito y abierto, hecho por [Midvash](https://midvash.com) · [midvash.com](https://midvash.com) · [midvash.app](https://midvash.app)</sub>
