import { initTodoFilters } from './utils/todoFilters';
import { initTodoDashboard } from './utils/dashboard';

const initTodosPage = () => {
    initTodoFilters(document);
    initTodoDashboard(document);
};

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initTodosPage, { once: true });
} else {
    initTodosPage();
}
