/* W68_CUSTOMER_PORTAL_VIEW_PASSWORD_20261007 */
(function () {
    'use strict';
    // Removed duplicate card insertion. Portal account is natively rendered inside portal-linked-account in Customer Master.
    document.addEventListener('DOMContentLoaded', () => {
        const existing = document.getElementById('portal-account-management-card');
        if (existing) existing.remove();
    });
})();
