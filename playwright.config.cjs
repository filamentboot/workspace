// @ts-check
const { defineConfig } = require('@playwright/test');

module.exports = defineConfig({
    globalSetup: './tests/e2e/global-setup.cjs',
    use: {
        baseURL: process.env.BASE_URL || 'http://localhost:8099',
        storageState: '/tmp/pw-uat/auth.json',
        headless: true,
    },
    reporter: 'list',
    timeout: 30000,
});
