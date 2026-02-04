const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );
const CopyPlugin = require( 'copy-webpack-plugin' );
const path = require( 'path' );
const fs = require( 'fs' );

// Get all block directories.
const blocksDir = path.resolve( __dirname, 'blocks' );
const blocks = fs.readdirSync( blocksDir ).filter( ( file ) => {
	return fs.statSync( path.join( blocksDir, file ) ).isDirectory();
} );

// Create entry points for each block.
const entry = {};
blocks.forEach( ( block ) => {
	const indexPath = path.join( blocksDir, block, 'index.js' );
	const viewPath = path.join( blocksDir, block, 'view.js' );

	if ( fs.existsSync( indexPath ) ) {
		entry[ `blocks/${ block }/index` ] = indexPath;
	}
	if ( fs.existsSync( viewPath ) ) {
		entry[ `blocks/${ block }/view` ] = viewPath;
	}
} );

// Copy block.json and CSS files to build directory.
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

	// Copy CSS files.
	[ 'index.css', 'style-index.css' ].forEach( ( cssFile ) => {
		if ( fs.existsSync( path.join( blockDir, cssFile ) ) ) {
			patterns.push( {
				from: path.join( blockDir, cssFile ),
				to: path.join( __dirname, 'build/blocks', block, cssFile ),
			} );
		}
	} );

	return patterns;
} );

module.exports = {
	...defaultConfig,
	entry,
	output: {
		...defaultConfig.output,
		path: path.resolve( __dirname, 'build' ),
	},
	plugins: [
		...( defaultConfig.plugins || [] ),
		new CopyPlugin( { patterns: copyPatterns } ),
	],
};
