// DOM Handles - We use dynamic fetching inside functions for reliability
const toastElement = document.getElementById('toast-message');
const toastText = document.getElementById('toast-text');

// Sidebar Collapse/Expand States
let isFolded = false;

// Reusable toggle function
function toggleSidebar(fold) {
    const sidebar = document.getElementById('sidebar');
    const toggleIcon = document.getElementById('toggle-icon');
    
    if (!sidebar) return;
    if (fold === isFolded) return;
    isFolded = fold;
    
    if (isFolded) {
        // Collapse sidebar
        sidebar.classList.remove('w-64');
        sidebar.classList.add('w-18', 'md:w-[72px]');
        document.querySelectorAll('.sidebar-text').forEach(el => el.classList.add('hidden'));
        const logoBlock = document.getElementById('sidebar-logo-block');
        if (logoBlock) logoBlock.classList.add('hidden');
        if (toggleIcon) {
            toggleIcon.setAttribute('data-lucide', 'chevron-right');
            if (window.lucide) lucide.createIcons();
        }
        
        // Collapse all open submenus
        document.querySelectorAll('.submenu-transition').forEach(el => {
            el.style.maxHeight = '0px';
        });
        document.querySelectorAll('.dropdown-chevron').forEach(el => {
            el.classList.remove('rotate-180');
        });
    } else {
        // Expand sidebar
        sidebar.classList.remove('w-18', 'md:w-[72px]');
        sidebar.classList.add('w-64');
        document.querySelectorAll('.sidebar-text').forEach(el => el.classList.remove('hidden'));
        const logoBlock = document.getElementById('sidebar-logo-block');
        if (logoBlock) logoBlock.classList.remove('hidden');
        if (toggleIcon) {
            toggleIcon.setAttribute('data-lucide', 'chevron-left');
            if (window.lucide) lucide.createIcons();
        }
    }
}

// Desktop Sidebar Toggle Click
document.addEventListener('click', (e) => {
    const btn = e.target.closest('#toggle-sidebar-desktop');
    if (btn) {
        toggleSidebar(!isFolded);
    }
});

// Hover event listeners moved inside DOMContentLoaded listener for safe initialization.

// Mobile Off-canvas Drawer Toggle
document.addEventListener('click', (e) => {
    const btn = e.target.closest('#toggle-sidebar-mobile');
    if (btn) {
        const sidebar = document.getElementById('sidebar');
        if (!sidebar) return;
        
        sidebar.classList.toggle('-translate-x-full');
        if (sidebar.classList.contains('-translate-x-full')) {
            sidebar.style.display = 'none';
        } else {
            sidebar.style.display = 'flex';
        }
    }
});

// Dynamic View Panel Switcher
window.switchView = function (pageId, originLink = null) {
    // Update breadcrumbs and section titles
    const currentBreadcrumb = document.getElementById('breadcrumb-current');
    const titleElement = document.getElementById('workspace-title');
    
    // Format labels cleanly
    let pageLabel = pageId.replace('-', ' ').toUpperCase();
    if (pageId === 'usm') pageLabel = 'System Security: USM';
    if (pageId === 'prod-master') pageLabel = 'Product Master List';
    if (pageId === 'inv-list') pageLabel = 'Inventory Database';

    if (currentBreadcrumb) currentBreadcrumb.innerText = pageLabel;
    if (titleElement) titleElement.innerText = pageLabel;

    // Hide all active content views
    document.querySelectorAll('.page-view').forEach(view => {
        view.classList.add('hidden');
    });

    // Show matched page or use standard custom layout placeholder
    const targetView = document.getElementById(`page-${pageId}`);
    if (targetView) {
        targetView.classList.remove('hidden');
    } else {
        const placeholderName = document.getElementById('placeholder-page-name');
        if (placeholderName) placeholderName.innerText = pageLabel + " Workspace Frame";
        const pagePlaceholder = document.getElementById('page-placeholder');
        if (pagePlaceholder) pagePlaceholder.classList.remove('hidden');
    }

    // Highlight chosen navigation sidebar item
    document.querySelectorAll('#nav-container a').forEach(a => {
        a.classList.remove('bg-goldlining-500', 'text-maroon-950', 'font-semibold', 'shadow');
        a.classList.add('hover:bg-maroon-800', 'text-gray-100');
    });

    if (originLink) {
        originLink.classList.remove('hover:bg-maroon-800', 'text-gray-100');
        originLink.classList.add('bg-goldlining-500', 'text-maroon-950', 'font-semibold', 'shadow');
    }

    // Automatically close open dropdowns on switch
    const notifDropdown = document.getElementById('notif-dropdown');
    const profileDropdown = document.getElementById('profile-dropdown');
    if (notifDropdown) notifDropdown.classList.add('hidden');
    if (profileDropdown) profileDropdown.classList.add('hidden');

    // Collapse drawer on phone display sizes
    const sidebar = document.getElementById('sidebar');
    if (window.innerWidth < 768 && sidebar) {
        sidebar.classList.add('-translate-x-full');
        sidebar.style.display = 'none';
    }
}

// Submenu Accordion Toggle Handler
window.toggleDropdown = function (dropdownId, element) {
    const toggleSidebarDesktopBtn = document.getElementById('toggle-sidebar-desktop');
    if (isFolded && toggleSidebarDesktopBtn) {
        toggleSidebarDesktopBtn.click();
    }

    const dropdown = document.getElementById(dropdownId);
    const chevron = element.querySelector('.dropdown-chevron');

    if (dropdown.style.maxHeight && dropdown.style.maxHeight !== '0px') {
        dropdown.style.maxHeight = '0px';
        chevron.classList.remove('rotate-180');
    } else {
        // Close other submenus first
        document.querySelectorAll('.submenu-transition').forEach(el => {
            el.style.maxHeight = '0px';
        });
        document.querySelectorAll('.dropdown-chevron').forEach(el => {
            el.classList.remove('rotate-180');
        });

        // Expand current dropdown
        dropdown.style.maxHeight = dropdown.scrollHeight + 'px';
        chevron.classList.add('rotate-180');
    }
}

// Notification Bell dropdown panel trigger
document.addEventListener('click', (e) => {
    const btn = e.target.closest('#notif-btn');
    if (btn) {
        const dropdown = document.getElementById('notif-dropdown');
        const profile = document.getElementById('profile-dropdown');
        if (dropdown) dropdown.classList.toggle('hidden');
        if (profile) profile.classList.add('hidden');
    }
});

// User Profile quick view dropdown trigger
document.addEventListener('click', (e) => {
    const btn = e.target.closest('#profile-dropdown-btn');
    if (btn) {
        const dropdown = document.getElementById('profile-dropdown');
        const notif = document.getElementById('notif-dropdown');
        if (dropdown) dropdown.classList.toggle('hidden');
        if (notif) notif.classList.add('hidden');
    }
});

// Click off-screen listeners to clear open elements
document.addEventListener('click', (e) => {
    const isProfileClick = e.target.closest('#profile-dropdown-btn') || e.target.closest('#profile-dropdown');
    const isNotifClick = e.target.closest('#notif-btn') || e.target.closest('#notif-dropdown');
    
    if (!isProfileClick) {
        const profile = document.getElementById('profile-dropdown');
        if (profile) profile.classList.add('hidden');
    }
    if (!isNotifClick) {
        const notif = document.getElementById('notif-dropdown');
        if (notif) notif.classList.add('hidden');
    }
});

// Premium simulated UI response toasts
function showSimulatedAlert(message) {
    if (toastText && toastElement) {
        toastText.innerText = message;
        toastElement.classList.remove('translate-y-20', 'opacity-0');
        toastElement.classList.add('translate-y-0', 'opacity-100');
        
        setTimeout(() => {
            toastElement.classList.remove('translate-y-0', 'opacity-100');
            toastElement.classList.add('translate-y-20', 'opacity-0');
        }, 3000);
    }
}

window.simulatedLogout = function () {
    showSimulatedAlert("Clearing secure access tokens & logging out...");
    setTimeout(() => {
        window.location.href = 'logout';
    }, 1500);
}

// Live Date & Time Counter Functionality
function initLiveDateTime() {
    const dateTimeDisplay = document.getElementById('live-datetime');
    
    function updateTime() {
        const now = new Date();
        
        // Formatted string options
        const dateOptions = { weekday: 'short', year: 'numeric', month: 'short', day: 'numeric' };
        const timeOptions = { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true };
        
        const formattedDate = now.toLocaleDateString('en-US', dateOptions);
        const formattedTime = now.toLocaleTimeString('en-US', timeOptions);
        
        if (dateTimeDisplay) {
            dateTimeDisplay.innerHTML = `${formattedDate} <span class="mx-1.5 text-maroon-400">|</span> <span class="font-bold text-maroon-900">${formattedTime}</span>`;
        }
    }
    
    updateTime();
    setInterval(updateTime, 1000);
}

// Initial setup and screen listener adjustments
window.addEventListener('DOMContentLoaded', () => {
    if (window.lucide) {
        lucide.createIcons();
    }
    initLiveDateTime();
    
    const sidebar = document.getElementById('sidebar');
    if (!sidebar) return;

    if (window.innerWidth < 768) {
        sidebar.classList.add('-translate-x-full');
        sidebar.style.display = 'none';
    } else {
        // Desktop default: keep sidebar folded unless it's currently hovered.
        // This prevents "stays open after reload" behavior.
        const isHoveredOnLoad = sidebar.matches(':hover');
        toggleSidebar(!isHoveredOnLoad);
    }

    // Auto-close/expand sidebar on hover
    sidebar.addEventListener('mouseenter', () => {
        if (window.innerWidth < 768) return;
        if (isFolded) toggleSidebar(false);
    });
    sidebar.addEventListener('mouseleave', () => {
        if (window.innerWidth < 768) return;
        if (!isFolded) toggleSidebar(true);
    });
    
    // Auto-fold safety net: Catch cases where the mouse leaves before JS is fully loaded
    // or when the browser's initial :hover state is stale.
    let firstMoveDone = false;
    document.addEventListener('mousemove', function _firstMove(e) {
        if (firstMoveDone) return;
        firstMoveDone = true;
        document.removeEventListener('mousemove', _firstMove);
        
        if (window.innerWidth < 768) return;
        const sidebar = document.getElementById('sidebar');
        if (!sidebar) return;
        
        // If the very first mouse movement is outside the sidebar, force it closed
        const isOverSidebar = e.target.closest('#sidebar');
        if (!isOverSidebar && !isFolded) {
            toggleSidebar(true);
        }
    });
});

window.addEventListener('resize', () => {
    const sidebar = document.getElementById('sidebar');
    if (sidebar) {
        if (window.innerWidth >= 768) {
            sidebar.classList.remove('-translate-x-full');
            sidebar.style.display = 'flex';
        } else {
            sidebar.classList.add('-translate-x-full');
            sidebar.style.display = 'none';
        }
    }
});
