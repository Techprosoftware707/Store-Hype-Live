const globals = require( 'globals' );

module.exports = [
	{
		files: [ 'wp-live-hype/**/*.js' ],
		ignores: [ '**/*.min.js' ],
		languageOptions: { ecmaVersion: 2017, sourceType: 'script', globals: { ...globals.browser, wplhAdmin: 'readonly' } },
		rules: {
			'no-undef': 'error',
			'no-unused-vars': [ 'error', { args: 'none', caughtErrors: 'none' } ],
			'no-unreachable': 'error',
			'no-redeclare': 'error',
			'no-implied-eval': 'error',
			'no-eval': 'error',
			eqeqeq: 'error',
			'no-empty': [ 'error', { allowEmptyCatch: true } ],
			'no-use-before-define': [ 'error', { functions: false } ],
		},
	},
	{
		files: [ 'tests/e2e/**/*.js' ],
		languageOptions: { ecmaVersion: 2022, sourceType: 'commonjs', globals: { ...globals.node, ...globals.browser } },
	},
];
