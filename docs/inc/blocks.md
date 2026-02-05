# Blocks

## Overview
Registers Spawn Gutenberg blocks from `build/blocks` or `blocks` directories.

## Blocks
- `domain-search`
- `tier-select`
- `checkout`
- `login`
- `dashboard`
- `account`
- `chat`

## Methods
- `init(): void`
- `register_blocks(): void`

## Example
```php
add_action( 'init', [ \Spawn\Blocks::class, 'register_blocks' ] );
```
