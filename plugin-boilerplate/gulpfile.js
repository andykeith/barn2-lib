var gulp = require( 'gulp' ),
	pump = require( 'pump' ),
	jshint = require( 'gulp-jshint' ),
	minify = require( 'gulp-uglify' ),
	cleancss = require( 'gulp-clean-css' ),
	fs = require( 'fs' ),
	header = require( 'gulp-header' ),
	rename = require( 'gulp-rename' ),
	run = require( 'gulp-run' ),
	debug = require( 'gulp-debug' ),
	checktextdomain = require( 'gulp-checktextdomain' ),
	GulpSSH = require( 'gulp-ssh' ),
	replace = require( 'gulp-replace' );

const pluginSlug = '[slug]';
const textDomain = '[domain]';

const pluginArchive = '/Users/andy/Dropbox/Barn2 Media/Plugins/Plugin archive/';
const readmeDir = '/Users/andy/Documents/localhost/barn2/wp-content/uploads/plugin-readme/';
const zipFile = pluginSlug + '.zip';

var sshBarn2 = new GulpSSH( {
	ignoreErrors: false,
	sshConfig: {
		host: '35.234.152.223',
		port: 48537,
		username: 'barn2',
		password: 'x0wKmA6qA1fiOji'
	}
} );

var sshDemo = new GulpSSH( {
	ignoreErrors: false,
	sshConfig: {
		host: '35.234.152.223',
		port: 1234,
		username: '???',
		password: '???'
	}
} );

var getVersion = function() {
	var readme = fs.readFileSync( 'readme.txt', 'utf8' );
	var version = readme.match( /Stable tag\:\s(.*)\s/i );
	return ( 1 in version ) ? version[1] : false;
};

var getCopyright = function() {
	return fs.readFileSync( 'copyright.txt' );
};

var refreshLib = function() {
	return gulp
		.src( ['license/*.php'], { cwd: '../../barn2-lib', base: '../../barn2-lib' } )
		//.pipe( debug() )
		.pipe( replace( /'barn2'|'easy-digital-downloads'/g, "'" + textDomain + "'" ) )
		.pipe( gulp.dest( 'includes' ) );
};

var scripts = function( cb ) {
	pump( [
		gulp.src( ['assets/js/*.js', 'assets/js/admin/*.js', '!**/*.min.js'], { base: './' } ),
		debug(),
		header( getCopyright(), { 'version': getVersion() } ),
		minify( { compress: { negate_iife: false }, output: { comments: '/^\/*!/' } } ),
		rename( { suffix: '.min' } ),
		gulp.dest( '.' )
	], cb );
};

var styles = function( cb ) {
	pump( [
		gulp.src( ['assets/css/*.css', 'assets/css/admin/*.css', '!**/*.min.css'], { base: './' } ),
		debug(),
		header( getCopyright( ), { 'version': getVersion( ) } ),
		cleancss( { compatibility: 'ie9' } ),
		rename( { suffix: '.min' } ),
		gulp.dest( '.' )
	], cb );
};

var lint = function() {
	return gulp.src( ['assets/js/*.js', 'assets/js/admin/*.js', '!**/*.min.js'] )
		.pipe( jshint() )
		.pipe( jshint.reporter() ); // Dump results
};

var textdomain = function() {
	return gulp
		.src( ['**/*.php'] )
		.pipe(
			checktextdomain( {
				text_domain: textDomain,
				keywords: [
					'__:1,2d',
					'_e:1,2d',
					'_x:1,2c,3d',
					'esc_html__:1,2d',
					'esc_html_e:1,2d',
					'esc_html_x:1,2c,3d',
					'esc_attr__:1,2d',
					'esc_attr_e:1,2d',
					'esc_attr_x:1,2c,3d',
					'_ex:1,2c,3d',
					'_n:1,2,4d',
					'_nx:1,2,4c,5d',
					'_n_noop:1,2,3d',
					'_nx_noop:1,2,3c,4d'
				]
			} )
			);
};

var createZipFile = function() {
	var zipCommand = `cd .. && rm ${zipFile}; zip -r ${zipFile} ${pluginSlug} -x "*/vendor/*" "*/node_modules/*" *.git* *.DS_Store */package*.json *gulpfile.js *copyright.txt`;

	return run( zipCommand ).exec();
};

var zip = gulp.series( gulp.parallel( scripts, styles, refreshLib ), createZipFile );

var archiveZipFile = function() {
	var pluginDir = pluginArchive + pluginSlug,
		deployDir = pluginDir + '/' + getVersion();

	if ( !fs.existsSync( pluginDir ) ) {
		fs.mkdirSync( pluginDir );
	}
	if ( !fs.existsSync( deployDir ) ) {
		fs.mkdirSync( deployDir );
	}

	return gulp.src( zipFile, { cwd: '../' } )
		.pipe( debug() )
		.pipe( gulp.dest( deployDir ) );
};

var readme = function() {
	return gulp.src( 'readme.txt' )
		.pipe( gulp.dest( readmeDir + pluginSlug ) ) // copy to barn2 local site
		.pipe( sshBarn2.sftp( 'write', 'public/wp-content/uploads/plugin-readme/' + pluginSlug + '/readme.txt' ) ); // upload to live
};

var demo = function() {
	//sshDemo.shell( 'rm -rf /wp-content/plugins/' + pluginSlug + '/*' );
	return gulp.src( ['**/*.*', '!node_modules/**', '!vendor/**', '!**/.DS_Store', '!package*.json', '!copyright.txt', '!gulpfile.js', '!.git/**', '!.git*'] )
		//.pipe( debug() )
		.pipe( sshDemo.dest( 'public/wp-content/plugins/' + pluginSlug + '/' ) );
};


var build = gulp.parallel( zip, lint, textdomain );
var release = gulp.series( build, gulp.parallel( demo, archiveZipFile, readme ) );

module.exports = { build: build, release: release, default: build };