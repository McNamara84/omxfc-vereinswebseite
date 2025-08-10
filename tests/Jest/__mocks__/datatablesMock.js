import { jest } from '@jest/globals';

export const DataTable = jest.fn(function () {
  this.sortColumn = jest.fn();
});
