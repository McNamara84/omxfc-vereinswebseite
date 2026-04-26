export default {
  testEnvironment: 'jsdom',
  testPathIgnorePatterns: ['/node_modules/', '/vendor/'],
  testMatch: ['**/tests/Jest/**/*.test.js'],
  moduleNameMapper: {
    '\\.(css)$': '<rootDir>/tests/Jest/__mocks__/styleMock.js',
    '\\.(png|jpe?g|gif|webp|svg)$': '<rootDir>/tests/Jest/__mocks__/fileMock.js',
    '^chart.js/auto$': '<rootDir>/tests/Jest/__mocks__/chartMock.js',
  },
};