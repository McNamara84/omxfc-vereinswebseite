import { setupDashboardAccessibility } from './dashboard/accessibility';
import { setupDashboardActivityFeed } from './dashboard/activity-feed';

const setupDashboard = () => {
    setupDashboardAccessibility();
    setupDashboardActivityFeed();
};

document.addEventListener('DOMContentLoaded', () => {
    setupDashboard();
});

document.addEventListener('livewire:navigated', () => {
    setupDashboard();
});

window.addEventListener('dashboard-feed-updated', setupDashboardActivityFeed);
