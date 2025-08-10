import { jest } from '@jest/globals';
export const mockSortColumn = jest.fn();
export const DataTable = jest.fn(() => ({ sortColumn: mockSortColumn }));
