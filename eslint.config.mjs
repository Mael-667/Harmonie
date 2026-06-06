import js from '@eslint/js'
import globals from 'globals'
import react from 'eslint-plugin-react'
import reactHooks from 'eslint-plugin-react-hooks'

export default [
  {
    ignores: [
      'public/build/**',
      'node_modules/**',
      'vendor/**',
      'assets/vendor/**',
      'assets/bootstrap.js',
      'assets/stimulus_bootstrap.js',
      'assets/scripts/app.js', // JS vanilla hérité, remplacé progressivement par React
    ],
  },
  {
    files: ['**/*.{js,jsx}'],
    languageOptions: {
      ecmaVersion: 'latest',
      sourceType: 'module',
      parserOptions: { ecmaFeatures: { jsx: true } },
      globals: {
        ...globals.browser,
      },
    },
    settings: { react: { version: 'detect' } },
    plugins: { react, 'react-hooks': reactHooks },
    rules: {
      ...js.configs.recommended.rules,
      ...react.configs.recommended.rules,
      ...react.configs['jsx-runtime'].rules, // pas besoin d'importer React
      ...reactHooks.configs.recommended.rules,
      // Projet sans PropTypes ni TypeScript -> on coupe la validation de props
      'react/prop-types': 'off',
      // Beaucoup d'apostrophes françaises dans le JSX -> on coupe l'échappement
      'react/no-unescaped-entities': 'off',
    },
  },
]
