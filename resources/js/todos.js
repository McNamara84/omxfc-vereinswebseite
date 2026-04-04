import { initTodoDashboard } from './utils/dashboard';

const initTodosPage = () => {
    initTodoDashboard(document);
};

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initTodosPage, { once: true });
} else {
    initTodosPage();
}

document.addEventListener('livewire:navigated', initTodosPage);
