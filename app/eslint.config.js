import js from '@eslint/js';
import globals from 'globals';
import reactHooks from 'eslint-plugin-react-hooks';
import reactRefresh from 'eslint-plugin-react-refresh';
import tseslint from 'typescript-eslint';
import prettier from 'eslint-config-prettier';

export default tseslint.config(
    {
        // Legacy Breeze scaffolding, replaced in Phase 5 (auth/profile restyle).
        // Excluded from strict analysis until then; our code is fully covered.
        ignores: [
            'vendor/',
            'public/build/',
            'node_modules/',
            'storage/',
            'bootstrap/cache/',
            'resources/js/Pages/Welcome.tsx',
            'resources/js/Layouts/GuestLayout.tsx',
        ],
    },
    {
        extends: [js.configs.recommended, ...tseslint.configs.strictTypeChecked, ...tseslint.configs.stylisticTypeChecked],
        files: ['resources/js/**/*.{ts,tsx}'],
        languageOptions: {
            ecmaVersion: 2022,
            globals: globals.browser,
            parserOptions: {
                projectService: true,
                tsconfigRootDir: import.meta.dirname,
            },
        },
        plugins: {
            'react-hooks': reactHooks,
            'react-refresh': reactRefresh,
        },
        rules: {
            ...reactHooks.configs.recommended.rules,
            'react-refresh/only-export-components': ['warn', { allowConstantExport: true }],
            '@typescript-eslint/no-unused-vars': ['error', { argsIgnorePattern: '^_' }],
            '@typescript-eslint/consistent-type-imports': 'error',
        },
    },
    {
        // Non-component modules: variant maps, hooks, and config exports are
        // intentionally exported alongside (or instead of) components.
        files: [
            'resources/js/components/ui/**/*.{ts,tsx}',
            'resources/js/components/nav-config.ts',
            'resources/js/components/theme-provider.tsx',
            'resources/js/hooks/**/*.{ts,tsx}',
            'resources/js/lib/**/*.{ts,tsx}',
        ],
        rules: {
            'react-refresh/only-export-components': 'off',
        },
    },
    prettier,
);
