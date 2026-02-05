# Autoload

## Overview
Registers a PSR-style autoloader for Spawn classes and abilities.

## Behavior
- Loads classes in the `Spawn\` namespace.
- Routes `Spawn\Abilities\*` to `inc/abilities/class-*.php`.
- Routes other classes to `inc/class-*.php`.
- Includes a class map entry for `Spawn\Google_OAuth`.

## Example
```php
spl_autoload_register(
	function ( string $class ): void {
		if ( strpos( $class, 'Spawn\\' ) !== 0 ) {
			return;
		}
	}
);
```
