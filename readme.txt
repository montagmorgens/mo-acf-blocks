=== MONTAGMORGENS ACF Blocks ===
Contributors: herrschuessler
Requires at least: 5.0.0
Tested up to: 5.5.1
Requires PHP: 7.0.0
Stable tag: 1.1.4
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Dieses Plugin stellt eine YAML-basierte ACF-Block-API für MONTAGMORGENS-Themes zur Verfügung.

== Changelog ==

= 1.1.4 =
* Update readme.txt

= 1.1.3 =
* Update readme.txt

= 1.1.2 =
* Add description to readme.txt

= 1.1.1 =
* Export-ignore .vscode

= 1.1.0 =
* Apply filters to block data
* Update symfony/yaml to 5.1

= 1.0.3 =
* Fix error message formating
* Update dependencies

= 1.0.2 =
* Add check if attach_style parameter is set

= 1.0.0 =
* Initial release

== Description ==

### Block registration

Register ACF blocks by YAML config files in `views/blocks`.

The YAML files follow the pattern:

```yaml
title: 'The Block Name'
category: 'theme'
mode: 'edit'
align: 'full'
attach_style: 'block-xyz'
keywords: ['xyz']
supports:
  align: false
  mode: true
icon: '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24"></svg>'
````

The file name (without the `.yml` extension) will be used as internal block name.

A twig template with the same file name (but with `.twig` extension,
obviously) will be automatically called by the render_callback of the
ACF block.

### Filter hooks

There are two filter hooks to filter the data object that gets passed to any twig view:

#### General filter, applies to all blocks.
```php
apply_filters( 'mo_acf_blocks/render_acf_block', $data, $block, $name )
```

#### Specific filter, applies only to (block name).
```php
apply_filters( 'mo_acf_blocks/render_acf_block/(block name)', $data, $block )
```

#### Parameters
* `$data` *(Array)* An array of ACF field values.
* `$block` *(Array)* The block settings and attributes.
* `$name` *(String)* The block slug (same as twig view name).

