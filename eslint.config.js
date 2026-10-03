import js from '@eslint/js';
import prettier from 'eslint-config-prettier';
import react from 'eslint-plugin-react';
import reactHooks from 'eslint-plugin-react-hooks';
import globals from 'globals';

export default [
    js.configs.recommended,
    {
        ...react.configs.flat.recommended,
        ...react.configs.flat['jsx-runtime'],
        settings: { react: { version: 'detect' } },
        rules: {
            ...react.configs.flat.recommended.rules,
            ...react.configs.flat['jsx-runtime'].rules,
            'react/prop-types': 'off',
            'react/no-unescaped-entities': 'off',
        },
    },
    {
        plugins: { 'react-hooks': reactHooks },
        rules: {
            'react-hooks/rules-of-hooks': 'error',
            'react-hooks/exhaustive-deps': 'warn',
        },
    },
    {
        languageOptions: {
            globals: { ...globals.browser, route: 'readonly' },
        },
    },
    { ignores: ['vendor', 'node_modules', 'public', 'bootstrap/ssr'] },
    prettier,
];
