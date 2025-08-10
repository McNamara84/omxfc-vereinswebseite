export default {
  testEnvironment: 'jsdom',
  testPathIgnorePatterns: ['/node_modules/', '/vendor/'],
  testMatch: ['**/tests/Jest/**/*.test.js'],
  moduleNameMapper: {
    '\\.(css)$': '<rootDir>/tests/Jest/__mocks__/styleMock.js',
    '^chart.js/auto$': '<rootDir>/tests/Jest/__mocks__/chartMock.js',
    '^simple-datatables$': '<rootDir>/tests/Jest/__mocks__/datatablesMock.js',
  },
};