import { setupDashboardAccessibility } from './dashboard/accessibility';

document.addEventListener('DOMContentLoaded', () => {
    setupDashboardAccessibility();
});

document.addEventListener('livewire:navigated', () => {
    setupDashboardAccessibility();
});
