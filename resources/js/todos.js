import { initTodoFilters } from './utils/todoFilters';
import { initTodoDashboard } from './utils/dashboard';

document.addEventListener('DOMContentLoaded', () => {
    initTodoFilters(document);
    initTodoDashboard(document);
});
