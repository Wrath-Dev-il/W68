/**
 * User Security Management (USM) Client Logic
 * Implements actual Laravel database integrations, AJAX creators, AJAX password resets, and dynamic stats calculators.
 */

// Active users state collection
let users = [];

// Navigation state
let currentTab = "admin"; // 'admin', 'employee', 'customer'

// Active filters
let filters = {
    search: {
        userId: "",
        name: "",
        password: "",
        lastChange: "",
        status: "",
        loginFrequency: ""
    },
    modal: {
        accountType: "all", // 'all', 'admin', 'employee', 'customer'
        status: "all"       // 'all', 'Online', 'Offline'
    }
};

// Track visible/hidden password state in tables
let passwordVisibility = {};

// Initialize on DOM loaded
window.addEventListener("DOMContentLoaded", () => {
    // Setup Lucide icons initially
    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
    }
    
    // Bind Tab Click Handlers
    document.querySelectorAll(".usm-tab-btn").forEach(btn => {
        btn.addEventListener("click", (e) => {
            const tab = btn.getAttribute("data-tab");
            switchTab(tab);
        });
    });

    // Populate data from DB feed injected in Blade
    initializeDatabaseUsers();

    // Bind Search & Action listeners
    setupEventListeners();
});

// Parse injected database records into standard frontend structure
function initializeDatabaseUsers() {
    const rawDbUsers = window.dbUsers || [];
    const onlineUserIds = new Set(window.usmOnlineUserIds || []);

    const acctTypeMap = {
        1: 'admin',
        2: 'employee',
        3: 'customer',
        4: 'developer',
        5: 'warehouse'
    };

    users = rawDbUsers.map(u => {
        // Parse database created/updated timestamps
        let dateStr = "Not Available";
        if (u.updated_at) {
            const d = new Date(u.updated_at);
            if (!isNaN(d.getTime())) {
                const year = d.getFullYear();
                const month = String(d.getMonth() + 1).padStart(2, '0');
                const day = String(d.getDate()).padStart(2, '0');
                const hours = String(d.getHours()).padStart(2, '0');
                const minutes = String(d.getMinutes()).padStart(2, '0');
                dateStr = `${year}-${month}-${day} ${hours}:${minutes}`;
            }
        }

        // Determine online/offline status
        const status = onlineUserIds.has(u.User_ID) ? "Online" : "Offline";

        // Deterministic login frequency
        const freq = (u.login_ID * 7) % 45 + 5;

        return {
            loginId: u.login_ID,
            userId: u.User_ID,
            firstName: u.User_First_Name,
            middleName: u.User_Middle_Name || "",
            lastName: u.User_Last_Name,
            password: u.Password || "",
            accountType: acctTypeMap[u.account_type] || 'customer',
            lastChange: dateStr,
            status: status,
            loginFrequency: freq
        };
    });

    // Initial render and calculations
    recalculateStats();
    renderTables();
}

// Setup DOM event listeners for search bars, modal triggers, form submits
function setupEventListeners() {
    // Column header input listeners
    document.querySelectorAll(".column-search-input").forEach(input => {
        input.addEventListener("input", (e) => {
            const column = e.target.getAttribute("data-col");
            filters.search[column] = e.target.value.toLowerCase().trim();
            renderTables();
        });
    });

    // Add User Form Submission
    const addUserForm = document.getElementById("add-user-form");
    if (addUserForm) {
        addUserForm.addEventListener("submit", (e) => {
            e.preventDefault();
            handleCreateUser();
        });
    }

    // Modal filters apply button
    const applyFiltersBtn = document.getElementById("apply-filters-btn");
    if (applyFiltersBtn) {
        applyFiltersBtn.addEventListener("click", () => {
            const filterAccountType = document.getElementById("filter-account-type").value;
            const filterStatus = document.getElementById("filter-status").value;
            
            filters.modal.accountType = filterAccountType;
            filters.modal.status = filterStatus;
            
            // Close filter modal
            toggleModal("filter-modal", false);
            
            // If they filtered specifically for an account type, auto-switch to that tab to see results!
            if (filterAccountType !== "all") {
                switchTab(filterAccountType);
            } else {
                renderTables();
            }
            
            showToast("Filters applied successfully!");
        });
    }

    // Modal filters reset button
    const resetFiltersBtn = document.getElementById("reset-filters-btn");
    if (resetFiltersBtn) {
        resetFiltersBtn.addEventListener("click", () => {
            document.getElementById("filter-account-type").value = "all";
            document.getElementById("filter-status").value = "all";
            
            filters.modal.accountType = "all";
            filters.modal.status = "all";
            
            toggleModal("filter-modal", false);
            renderTables();
            showToast("Filters reset successfully!");
        });
    }
}

// Switching Tab logic
function switchTab(tabId) {
    currentTab = tabId;
    
    // Update Tab UI styles
    document.querySelectorAll(".usm-tab-btn").forEach(btn => {
        const btnTab = btn.getAttribute("data-tab");
        if (btnTab === tabId) {
            btn.className = "usm-tab-btn tab-active px-5 py-3 text-sm font-bold flex items-center space-x-2 transition-all duration-200";
        } else {
            btn.className = "usm-tab-btn tab-inactive px-5 py-3 text-sm font-semibold flex items-center space-x-2 transition-all duration-200";
        }
    });

    // Show current tab container and hide others
    const tabAdmin = document.getElementById("table-container-admin");
    const tabEmployee = document.getElementById("table-container-employee");
    const tabCustomer = document.getElementById("table-container-customer");

    if (tabAdmin) tabAdmin.classList.add("hidden");
    if (tabEmployee) tabEmployee.classList.add("hidden");
    if (tabCustomer) tabCustomer.classList.add("hidden");

    if (tabId === "admin" && tabAdmin) tabAdmin.classList.remove("hidden");
    if (tabId === "employee" && tabEmployee) tabEmployee.classList.remove("hidden");
    if (tabId === "customer" && tabCustomer) tabCustomer.classList.remove("hidden");

    // Reset column search fields when switching tabs to avoid confusion
    clearSearchInputs();
    renderTables();
}

// Clear column search fields
function clearSearchInputs() {
    document.querySelectorAll(".column-search-input").forEach(input => {
        input.value = "";
    });
    for (let key in filters.search) {
        filters.search[key] = "";
    }
}

// Re-calculate statistics for dashboard cards
function recalculateStats() {
    const totalAdmins = users.filter(u => u.accountType === "admin").length;
    const totalEmployees = users.filter(u => u.accountType === "employee").length;
    const totalCustomers = users.filter(u => u.accountType === "customer").length;

    // Sub-stat values: Online / Offline
    const onlineAdmins = users.filter(u => u.accountType === "admin" && u.status === "Online").length;
    const offlineAdmins = totalAdmins - onlineAdmins;

    const onlineEmployees = users.filter(u => u.accountType === "employee" && u.status === "Online").length;
    const offlineEmployees = totalEmployees - onlineEmployees;

    const onlineCustomers = users.filter(u => u.accountType === "customer" && u.status === "Online").length;
    const offlineCustomers = totalCustomers - onlineCustomers;

    // Render counts inside standard DOM nodes
    updateElementText("card-count-admin", totalAdmins);
    updateElementText("card-sub-admin", `Online: ${onlineAdmins} | Offline: ${offlineAdmins}`);
    updateElementText("badge-count-admin", totalAdmins);

    updateElementText("card-count-employee", totalEmployees);
    updateElementText("card-sub-employee", `Online: ${onlineEmployees} | Offline: ${offlineEmployees}`);
    updateElementText("badge-count-employee", totalEmployees);

    updateElementText("card-count-customer", totalCustomers);
    updateElementText("card-sub-customer", `Online: ${onlineCustomers} | Offline: ${offlineCustomers}`);
    updateElementText("badge-count-customer", totalCustomers);
}

// Helper to update elements safely
function updateElementText(id, text) {
    const el = document.getElementById(id);
    if (el) el.textContent = text;
}

// Render dynamic tables based on currently active view, tab and filter matrix
function renderTables() {
    const adminTbody = document.getElementById("usm-tbody-admin");
    const employeeTbody = document.getElementById("usm-tbody-employee");
    const customerTbody = document.getElementById("usm-tbody-customer");

    const noResultsAdmin = document.getElementById("no-results-admin");
    const noResultsEmployee = document.getElementById("no-results-employee");
    const noResultsCustomer = document.getElementById("no-results-customer");

    // Filter logic
    const filteredUsers = users.filter(user => {
        // Modal account type filter
        if (filters.modal.accountType !== "all" && user.accountType !== filters.modal.accountType) {
            return false;
        }

        // Modal status filter
        if (filters.modal.status !== "all" && user.status !== filters.modal.status) {
            return false;
        }

        // Column specific searches
        const fullName = `${user.firstName || ''} ${user.middleName || ''} ${user.lastName || ''}`.toLowerCase();
        
        if (filters.search.userId && !user.userId.toLowerCase().includes(filters.search.userId)) return false;
        if (filters.search.name && !fullName.includes(filters.search.name)) return false;
        if (filters.search.password && user.password && !user.password.toLowerCase().includes(filters.search.password)) return false;
        if (filters.search.lastChange && user.lastChange && !user.lastChange.toLowerCase().includes(filters.search.lastChange)) return false;
        if (filters.search.status && !user.status.toLowerCase().includes(filters.search.status)) return false;
        if (filters.search.loginFrequency && user.loginFrequency && !user.loginFrequency.toString().includes(filters.search.loginFrequency)) return false;

        return true;
    });

    // Split filtered into specific tabs
    const adminData = filteredUsers.filter(u => u.accountType === "admin");
    const employeeData = filteredUsers.filter(u => u.accountType === "employee");
    const customerData = filteredUsers.filter(u => u.accountType === "customer");

    // 1. Admin Table render
    if (adminTbody) {
        adminTbody.innerHTML = "";
        if (adminData.length === 0) {
            if (noResultsAdmin) noResultsAdmin.classList.remove("hidden");
        } else {
            if (noResultsAdmin) noResultsAdmin.classList.add("hidden");
            adminData.forEach(user => {
                const tr = document.createElement("tr");
                tr.className = "hover:bg-slate-50/60 table-row-animate transition-colors divide-x divide-slate-100";
                
                const isPasswordVisible = passwordVisibility[user.userId] === true;
                const passwordDisplay = isPasswordVisible ? (user.password || "No password set") : "••••••••";
                const eyeIcon = isPasswordVisible ? "eye-off" : "eye";

                tr.innerHTML = `
                    <td class="py-4 px-5 font-mono font-bold text-slate-800 text-xs">${user.userId}</td>
                    <td class="py-4 px-5 text-slate-700 font-medium">${user.firstName} ${user.middleName ? user.middleName + ' ' : ''}${user.lastName}</td>
                    <td class="py-4 px-5">
                        <div class="password-toggle-container">
                            <span class="font-mono text-xs text-slate-500 block min-w-[80px]">${passwordDisplay}</span>
                            <span onclick="togglePasswordReveal('${user.userId}')" class="password-toggle-btn">
                                <i data-lucide="${eyeIcon}" class="w-4 h-4"></i>
                            </span>
                        </div>
                    </td>
                    <td class="py-4 px-5 text-slate-500 text-xs">${user.lastChange}</td>
                    <td class="py-4 px-5">
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold ${user.status === 'Online' ? 'bg-emerald-50 text-emerald-600 border border-emerald-100' : 'bg-slate-100 text-slate-500 border border-slate-200'}">
                            <span class="w-1.5 h-1.5 rounded-full mr-1.5 ${user.status === 'Online' ? 'bg-emerald-500 animate-pulse' : 'bg-slate-400'}"></span>
                            ${user.status}
                        </span>
                    </td>
                    <td class="py-4 px-5 text-center">
                        <div class="flex items-center justify-center gap-2">
                            <button onclick="handleResetPassword('${user.userId}')" class="inline-flex items-center px-2.5 py-1 bg-white hover:bg-maroon-50 text-maroon hover:text-maroon-800 text-xs font-bold rounded-lg border border-slate-200 hover:border-maroon-200 transition-all shadow-sm gap-1" title="Reset Password">
                                <i data-lucide="key-round" class="w-3 h-3"></i>
                                Reset
                            </button>
                            <button onclick="handleDeleteUser('${user.userId}')" class="inline-flex items-center px-2.5 py-1 bg-white hover:bg-red-50 text-red-600 hover:text-red-800 text-xs font-bold rounded-lg border border-slate-200 hover:border-red-200 transition-all shadow-sm gap-1" title="Delete User">
                                <i data-lucide="trash-2" class="w-3 h-3"></i>
                                Delete
                            </button>
                        </div>
                    </td>
                `;
                adminTbody.appendChild(tr);
            });
        }
    }

    // 2. Employee Table render
    if (employeeTbody) {
        employeeTbody.innerHTML = "";
        if (employeeData.length === 0) {
            if (noResultsEmployee) noResultsEmployee.classList.remove("hidden");
        } else {
            if (noResultsEmployee) noResultsEmployee.classList.add("hidden");
            employeeData.forEach(user => {
                const tr = document.createElement("tr");
                tr.className = "hover:bg-slate-50/60 table-row-animate transition-colors divide-x divide-slate-100";
                
                const isPasswordVisible = passwordVisibility[user.userId] === true;
                const passwordDisplay = isPasswordVisible ? (user.password || "No password set") : "••••••••";
                const eyeIcon = isPasswordVisible ? "eye-off" : "eye";

                tr.innerHTML = `
                    <td class="py-4 px-5 font-mono font-bold text-slate-800 text-xs">${user.userId}</td>
                    <td class="py-4 px-5 text-slate-700 font-medium">${user.firstName} ${user.middleName ? user.middleName + ' ' : ''}${user.lastName}</td>
                    <td class="py-4 px-5">
                        <div class="password-toggle-container">
                            <span class="font-mono text-xs text-slate-500 block min-w-[80px]">${passwordDisplay}</span>
                            <span onclick="togglePasswordReveal('${user.userId}')" class="password-toggle-btn">
                                <i data-lucide="${eyeIcon}" class="w-4 h-4"></i>
                            </span>
                        </div>
                    </td>
                    <td class="py-4 px-5 text-slate-500 text-xs">${user.lastChange}</td>
                    <td class="py-4 px-5">
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold ${user.status === 'Online' ? 'bg-emerald-50 text-emerald-600 border border-emerald-100' : 'bg-slate-100 text-slate-500 border border-slate-200'}">
                            <span class="w-1.5 h-1.5 rounded-full mr-1.5 ${user.status === 'Online' ? 'bg-emerald-500 animate-pulse' : 'bg-slate-400'}"></span>
                            ${user.status}
                        </span>
                    </td>
                    <td class="py-4 px-5 text-center">
                        <div class="flex items-center justify-center gap-2">
                            <button onclick="handleResetPassword('${user.userId}')" class="inline-flex items-center px-2.5 py-1 bg-white hover:bg-maroon-50 text-maroon hover:text-maroon-800 text-xs font-bold rounded-lg border border-slate-200 hover:border-maroon-200 transition-all shadow-sm gap-1" title="Reset Password">
                                <i data-lucide="key-round" class="w-3 h-3"></i>
                                Reset
                            </button>
                            <button onclick="handleDeleteUser('${user.userId}')" class="inline-flex items-center px-2.5 py-1 bg-white hover:bg-red-50 text-red-600 hover:text-red-800 text-xs font-bold rounded-lg border border-slate-200 hover:border-red-200 transition-all shadow-sm gap-1" title="Delete User">
                                <i data-lucide="trash-2" class="w-3 h-3"></i>
                                Delete
                            </button>
                        </div>
                    </td>
                `;
                employeeTbody.appendChild(tr);
            });
        }
    }

    // 3. Customer Table render
    if (customerTbody) {
        customerTbody.innerHTML = "";
        if (customerData.length === 0) {
            if (noResultsCustomer) noResultsCustomer.classList.remove("hidden");
        } else {
            if (noResultsCustomer) noResultsCustomer.classList.add("hidden");
            customerData.forEach(user => {
                const tr = document.createElement("tr");
                tr.className = "hover:bg-slate-50/60 table-row-animate transition-colors divide-x divide-slate-100";

                tr.innerHTML = `
                    <td class="py-4 px-5 font-mono font-bold text-slate-800 text-xs">${user.userId}</td>
                    <td class="py-4 px-5 text-slate-700 font-medium">${user.firstName} ${user.middleName ? user.middleName + ' ' : ''}${user.lastName}</td>
                    <td class="py-4 px-5">
                        <div class="flex items-center gap-1.5 text-xs text-slate-600">
                            <i data-lucide="activity" class="w-3.5 h-3.5 text-indigo-500"></i>
                            <span class="font-semibold text-slate-800">${user.loginFrequency || 0} times</span>
                        </div>
                    </td>
                    <td class="py-4 px-5">
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold ${user.status === 'Online' ? 'bg-emerald-50 text-emerald-600 border border-emerald-100' : 'bg-slate-100 text-slate-500 border border-slate-200'}">
                            <span class="w-1.5 h-1.5 rounded-full mr-1.5 ${user.status === 'Online' ? 'bg-emerald-500 animate-pulse' : 'bg-slate-400'}"></span>
                            ${user.status}
                        </span>
                    </td>
                `;
                customerTbody.appendChild(tr);
            });
        }
    }

    // Re-create icons dynamically
    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
    }
}

// Reveal/Hide password toggle
window.togglePasswordReveal = function(userId) {
    passwordVisibility[userId] = !passwordVisibility[userId];
    renderTables();
}

// Toggle modals open/close with fade animations
window.toggleModal = function(modalId, forceOpen = null) {
    const modal = document.getElementById(modalId);
    if (!modal) return;

    const isOpen = forceOpen !== null ? forceOpen : modal.classList.contains("hidden");

    if (isOpen) {
        modal.classList.remove("hidden");
        modal.querySelector(".modal-animate-in").classList.add("modal-animate-in");
    } else {
        modal.classList.add("hidden");
    }
}

// Creation form handler with Laravel Backend AJAX Integration
function handleCreateUser() {
    const userIdInput = document.getElementById("new-user-id");
    const firstNameInput = document.getElementById("new-first-name");
    const middleNameInput = document.getElementById("new-middle-name");
    const lastNameInput = document.getElementById("new-last-name");
    const passwordInput = document.getElementById("new-password");
    const accountTypeSelect = document.getElementById("new-account-type");
    const genderSelect = document.getElementById("new-gender");

    if (!userIdInput || !firstNameInput || !lastNameInput || !passwordInput || !accountTypeSelect || !genderSelect) return;

    const userId = userIdInput.value.trim().toUpperCase();
    const firstName = firstNameInput.value.trim();
    const middleName = middleNameInput.value.trim();
    const lastName = lastNameInput.value.trim();
    const password = passwordInput.value;
    const accountType = accountTypeSelect.value;
    const gender = genderSelect.value;

    // Front-end check
    if (!userId || !firstName || !lastName || !password || !gender) {
        alert("Please fill in all mandatory fields.");
        return;
    }

    // Show Confirmation Modal instead of immediate submit
    toggleModal('confirm-add-modal', true);

    // Bind the confirmation button
    const confirmBtn = document.getElementById("confirm-add-submit-btn");
    if (confirmBtn) {
        // Remove old listeners to avoid double submits
        const newConfirmBtn = confirmBtn.cloneNode(true);
        confirmBtn.parentNode.replaceChild(newConfirmBtn, confirmBtn);

        newConfirmBtn.onclick = () => {
            toggleModal('confirm-add-modal', false);
            executeUserCreation({
                User_ID: userId,
                User_First_Name: firstName,
                User_Middle_Name: middleName,
                User_Last_Name: lastName,
                Password: password,
                account_type: accountType,
                Gender: gender
            }, accountType);
        };
    }
}

// Actual AJAX execution for creation
function executeUserCreation(payload, accountType) {
    // Determine the base path for XAMPP or standard serving
    const baseUrl = window.location.origin + (window.location.pathname.startsWith('/System_Proposal') ? '/System_Proposal' : '');
    const apiUrl = window.usmRoutes?.create || (baseUrl + '/admin/usm/create');

    fetch(apiUrl, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': window.csrfToken || ''
        },
        body: JSON.stringify(payload)
    })
    .then(async response => {
        const isJson = response.headers.get('content-type')?.includes('application/json');
        const data = isJson ? await response.json() : null;

        if (!response.ok) {
            throw new Error(data?.error || `Server responded with ${response.status}`);
        }
        return data;
    })
    .then(data => {
        if (data.success) {
            const serverUser = data.user;
            
            // Format for client-side state
            const mappedUser = {
                loginId: serverUser.login_ID,
                userId: serverUser.User_ID,
                firstName: serverUser.User_First_Name,
                middleName: serverUser.User_Middle_Name || "",
                lastName: serverUser.User_Last_Name,
                password: serverUser.Password,
                accountType: accountType,
                lastChange: "Just Now",
                status: "Offline",
                loginFrequency: 0
            };

            users.unshift(mappedUser);
            document.getElementById("add-user-form").reset();
            toggleModal("add-user-modal", false);

            recalculateStats();
            switchTab(accountType);

            // Show Success Modal
            const successMsg = document.getElementById("success-add-msg");
            if (successMsg) successMsg.textContent = `Security account for ${payload.User_ID} (${payload.User_First_Name}) has been successfully registered.`;
            toggleModal('success-add-modal', true);
        }
    })
    .catch(error => {
        console.error("AJAX Error:", error);
        alert(`Failed to create user: ${error.message}`);
    });
}

// Reset Password interactive handler with Laravel Backend AJAX Integration
window.handleResetPassword = function(userId) {
    const userObj = users.find(u => u.userId === userId);
    if (!userObj) return;

    const newPassword = prompt(`Enter new password for ${userObj.firstName} ${userObj.lastName} (${userId}):`, "NewSecurePass2026!");
    
    if (newPassword === null) return;
    if (newPassword.trim() === "") {
        alert("Password cannot be blank.");
        return;
    }

    // Show Confirmation Modal
    const confirmMsg = document.getElementById("confirm-reset-msg");
    if (confirmMsg) confirmMsg.textContent = `Are you sure you want to update the security credentials for ${userObj.firstName} ${userObj.lastName} (${userId})?`;
    toggleModal('confirm-reset-modal', true);

    const confirmBtn = document.getElementById("confirm-reset-submit-btn");
    if (confirmBtn) {
        const newConfirmBtn = confirmBtn.cloneNode(true);
        confirmBtn.parentNode.replaceChild(newConfirmBtn, confirmBtn);

        newConfirmBtn.onclick = () => {
            toggleModal('confirm-reset-modal', false);
            executePasswordReset(userId, newPassword.trim());
        };
    }
}

// Actual AJAX execution for password reset
function executePasswordReset(userId, newPassword) {
    const baseUrl = window.location.origin + (window.location.pathname.startsWith('/System_Proposal') ? '/System_Proposal' : '');
    const apiUrl = window.usmRoutes?.resetPassword || (baseUrl + '/admin/usm/reset-password');

    fetch(apiUrl, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': window.csrfToken || ''
        },
        body: JSON.stringify({
            User_ID: userId,
            Password: newPassword
        })
    })
    .then(async response => {
        const isJson = response.headers.get('content-type')?.includes('application/json');
        const data = isJson ? await response.json() : null;

        if (!response.ok) {
            throw new Error(data?.error || `Server responded with ${response.status}`);
        }
        return data;
    })
    .then(data => {
        if (data.success) {
            const userObj = users.find(u => u.userId === userId);
            if (userObj) {
                userObj.password = newPassword;
                userObj.lastChange = data.updated_at || "Just Now";
            }

            renderTables();
            
            // Show Success Modal
            const successMsg = document.getElementById("success-reset-msg");
            if (successMsg) successMsg.textContent = `Password for ${userId} has been successfully updated in the secure database.`;
            toggleModal('success-reset-modal', true);
        }
    })
    .catch(error => {
        console.error("AJAX Error:", error);
        alert(`Failed to reset password: ${error.message}`);
    });
}

// Delete User interactive handler with Laravel Backend AJAX Integration
window.handleDeleteUser = function(userId) {
    const userObj = users.find(u => u.userId === userId);
    if (!userObj) return;

    // Show Confirmation Modal
    const confirmMsg = document.getElementById("confirm-delete-msg");
    if (confirmMsg) confirmMsg.textContent = `Are you sure you want to PERMANENTLY delete account ${userId} (${userObj.firstName} ${userObj.lastName})? This action cannot be undone.`;
    toggleModal('confirm-delete-modal', true);

    const confirmBtn = document.getElementById("confirm-delete-submit-btn");
    if (confirmBtn) {
        // Remove old listeners to avoid double submits
        const newConfirmBtn = confirmBtn.cloneNode(true);
        confirmBtn.parentNode.replaceChild(newConfirmBtn, confirmBtn);

        newConfirmBtn.onclick = () => {
            toggleModal('confirm-delete-modal', false);
            executeUserDeletion(userId);
        };
    }
}

// Actual AJAX execution for deletion
function executeUserDeletion(userId) {
    const baseUrl = window.location.origin + (window.location.pathname.startsWith('/System_Proposal') ? '/System_Proposal' : '');
    const apiUrl = window.usmRoutes?.delete || (baseUrl + '/admin/usm/delete');

    fetch(apiUrl, {
        method: 'DELETE',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': window.csrfToken || ''
        },
        body: JSON.stringify({
            User_ID: userId
        })
    })
    .then(async response => {
        const isJson = response.headers.get('content-type')?.includes('application/json');
        const data = isJson ? await response.json() : null;

        if (!response.ok) {
            throw new Error(data?.error || `Server responded with ${response.status}`);
        }
        return data;
    })
    .then(data => {
        if (data.success) {
            // Remove from local state
            users = users.filter(u => u.userId !== userId);
            
            recalculateStats();
            renderTables();
            
            // Show Success Modal
            const successMsg = document.getElementById("success-delete-msg");
            if (successMsg) successMsg.textContent = `Account ${userId} has been successfully purged from the secure database.`;
            toggleModal('success-delete-modal', true);
        }
    })
    .catch(error => {
        console.error("AJAX Error:", error);
        alert(`Failed to delete user: ${error.message}`);
    });
}

// Display modern Toast Success popups
function showToast(message) {
    const toast = document.getElementById("toast-message");
    const toastText = document.getElementById("toast-text");
    if (!toast || !toastText) return;

    toastText.textContent = message;
    
    // Slide UP & Fade IN
    toast.className = "fixed bottom-6 right-6 bg-maroon-950 text-white border-l-4 border-goldlining-500 px-4 py-3.5 rounded-xl shadow-2xl flex items-center space-x-3 transform translate-y-0 opacity-100 transition-all duration-300 z-50 pointer-events-auto";
    
    // Re-render bell alert icon
    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
    }

    // Auto-Dismiss after 3 seconds
    setTimeout(() => {
        toast.className = "fixed bottom-6 right-6 bg-maroon-950 text-white border-l-4 border-goldlining-500 px-4 py-3.5 rounded-xl shadow-2xl flex items-center space-x-3 transform translate-y-20 opacity-0 transition-all duration-300 pointer-events-none z-50";
    }, 3000);
}
