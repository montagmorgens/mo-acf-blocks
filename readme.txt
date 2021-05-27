=== MONTAGMORGENS ACF Blocks ===
Contributors: herrschuessler
Requires at least: 5.0.0
Tested up to: 5.7.1
Requires PHP: 7.2.0
Stable tag: 1.5.0
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Dieses Plugin stellt eine YAML-basierte ACF-Block-API für MONTAGMORGENS-Themes zur Verfügung.

== Changelog ==

= 1.5.0 =
* Add preview image for block inserter preview panel

= 1.4.2 =
* Run legacy register_acf_block filter without underscored name if this name differs from underscored name.

= 1.4.1 =
* Fix filter order

= 1.4.0 =
* Add filter register_acf_block/{block_name}

= 1.3.3 =
* Run legacy render_acf_block_preview and render_acf_block filters without underscored names only if those names differ from underscored names.

= 1.3.2 =
* Include `child/blocks` template location for child themes

= 1.3.1 =
* Add filter render_acf_block_preview for all blocks

= 1.3.0 =
* Add filter render_acf_block_preview/{block_name}
* Update dependencies

= 1.2.0 =
* Move theme blocks info page to themes menu, update wording
* Update dependencies

= 1.1.5 =
* Fix filter mo_acf_blocks_directories

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
icon: '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24"></svg>'
mode: 'auto'
align: 'full'
attach_style: 'block-xyz'
keywords: ['xyz']
supports:
  align: false
  mode: true
  customClassName: false
  anchor: true
````

The file name (without the `.yml` extension) will be used as internal block name.

A twig template with the same file name (but with `.twig` extension,
obviously) will be automatically called by the render_callback of the
ACF block.

A jpg file with the same file name will be automatically used as block inserter preview image in the block editor.

### Block Registration Filter hook 

To prevent the registration of a specific block under some conditions, hook into:

```php
apply_filters( 'mo_acf_blocks/register_acf_block/{block_name}', true )
```

### Data Filter hooks (Frontend)

There are two filter hooks to filter the data object that gets passed to any twig view:

#### General filter, applies to all blocks.
```php
apply_filters( 'mo_acf_blocks/render_acf_block', $data, $block, $name )
```

#### Specific filter, applies only to {block_name}.
```php
apply_filters( 'mo_acf_blocks/render_acf_block/{block_name}', $data, $block )
```

#### Parameters
* `$data` *(Array)* An array of ACF field values.
* `$block` *(Array)* The block settings and attributes.
* `$name` *(String)* The block slug (same as twig view name).

### Data Filter hooks (Backend)

There are two filter hooks to filter the data object that gets passed to any block preview:

#### General filter, applies to all blocks.
```php
apply_filters( 'mo_acf_blocks/render_acf_block_preview', $preview_data, $data, $block, $name )
```

#### Specific filter, applies only to {block_name}.
```php
apply_filters( 'mo_acf_blocks/render_acf_block_preview/{block_name}', $preview_data, $data, $block )
```
#### Parameters
* `$preview_data` *(Array)* An empty array.
* `$data` *(Array)* An array of ACF field values.
* `$block` *(Array)* The block settings and attributes.
* `$name` *(String)* The block slug (same as twig view name).

