import tseslint from '@typescript-eslint/eslint-plugin';
import tsParser from '@typescript-eslint/parser';

export default [
    {
        files: ['src/**/*.ts', 'tests/**/*.ts'],
        languageOptions: {
            parser: tsParser,
            parserOptions: {
                ecmaVersion: 2022,
                sourceType: 'module',
            },
        },
        plugins: {
            '@typescript-eslint': tseslint,
        },
        rules: {
            '@typescript-eslint/no-unused-vars': ['warn', { argsIgnorePattern: '^_' }],
            'no-console': 'off',
        },
    },
    {
        ignores: ['dist/**', 'node_modules/**'],
    },
];
