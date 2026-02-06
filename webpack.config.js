const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );
const CopyPlugin = require( 'copy-webpack-plugin' );
const path = require( 'path' );
const fs = require( 'fs' );

// Get all block directories.
const blocksDir = path.resolve( __dirname, 'blocks' );
const blocks = fs.readdirSync( blocksDir ).filter( ( file ) => {
	return fs.statSync( path.join( blocksDir, file ) ).isDirectory();
} );

// Helper to find entry file with multiple extensions.
function findEntryFile( blockDir, baseName ) {
	const extensions = [ '.tsx', '.ts', '.jsx', '.js' ];
	for ( const ext of extensions ) {
		const filePath = path.join( blockDir, baseName + ext );
		if ( fs.existsSync( filePath ) ) {
			return filePath;
		}
	}
	return null;
}

// Create entry points for each block.
const entry = {};
blocks.forEach( ( block ) => {
	const blockDir = path.join( blocksDir, block );
	const indexPath = findEntryFile( blockDir, 'index' );
	const viewPath = findEntryFile( blockDir, 'view' );

	if ( indexPath ) {
		entry[ `blocks/${ block }/index` ] = indexPath;
	}
	if ( viewPath ) {
		entry[ `blocks/${ block }/view` ] = viewPath;
	}
} );

// Copy block.json, CSS, and PHP files to build directory.
const copyPatterns = blocks.flatMap( ( block ) => {
	const patterns = [];
	const blockDir = path.join( blocksDir, block );

	// Always copy block.json.
	if ( fs.existsSync( path.join( blockDir, 'block.json' ) ) ) {
		patterns.push( {
			from: path.join( blockDir, 'block.json' ),
			to: path.join( __dirname, 'build/blocks', block, 'block.json' ),
		} );
	}

	// Copy CSS files (both explicit and generated).
	[ 'index.css', 'style-index.css', 'style.css' ].forEach( ( cssFile ) => {
		if ( fs.existsSync( path.join( blockDir, cssFile ) ) ) {
			patterns.push( {
				from: path.join( blockDir, cssFile ),
				to: path.join( __dirname, 'build/blocks', block, cssFile ),
			} );
		}
	} );

	// Copy render.php if exists.
	if ( fs.existsSync( path.join( blockDir, 'render.php' ) ) ) {
		patterns.push( {
			from: path.join( blockDir, 'render.php' ),
			to: path.join( __dirname, 'build/blocks', block, 'render.php' ),
		} );
	}

	return patterns;
} );

module.exports = {
	...defaultConfig,
	entry,
	output: {
		...defaultConfig.output,
		path: path.resolve( __dirname, 'build' ),
	},
	resolve: {
		...defaultConfig.resolve,
		extensions: [ '.tsx', '.ts', '.jsx', '.js', ...( defaultConfig.resolve?.extensions || [] ) ],
	},
	plugins: [
		...( defaultConfig.plugins || [] ),
		new CopyPlugin( { patterns: copyPatterns } ),
	],
};
