(function () {
    let selectedProfilePictureFile = null;

    function setTextTone(element, message, tone) {
        const targetEl = typeof element === 'string' ? document.getElementById(element) : element;
        if (!targetEl) return;

        const toneClasses = {
            success: 'text-emerald-600',
            error: 'text-red-500',
            muted: 'text-slate-400',
        };

        targetEl.textContent = message;
        targetEl.classList.remove('text-emerald-600', 'text-red-500', 'text-slate-400');
        targetEl.classList.add(toneClasses[tone] || toneClasses.muted);
    }

    function setUploadStatus(message, tone) {
        const statusEl = document.getElementById('admin-profile-upload-status');
        if (!statusEl) return;

        setTextTone(statusEl, message, tone);
    }

    function openModal(modalId) {
        const modal = document.getElementById(modalId);
        if (!modal) return;
        modal.classList.remove('hidden');
    }

    function closeModal(modalId) {
        const modal = document.getElementById(modalId);
        if (!modal) return;
        modal.classList.add('hidden');
    }

    function updateProfilePreview(imageUrl) {
        const previewElements = [
            document.getElementById('admin-profile-picture-preview'),
            document.getElementById('admin-profile-picture-modal-preview'),
        ];
        const fallbackElements = [
            document.getElementById('admin-profile-picture-fallback'),
            document.getElementById('admin-profile-picture-modal-fallback'),
        ];

        if (!imageUrl) return;

        previewElements.forEach(function (previewEl) {
            if (!previewEl) return;
            previewEl.src = imageUrl;
            previewEl.classList.remove('hidden');
        });

        fallbackElements.forEach(function (fallbackEl) {
            if (fallbackEl) {
                fallbackEl.classList.add('hidden');
            }
        });
    }

    function showSuccessModal(message) {
        const messageEl = document.getElementById('profile-success-message');
        if (messageEl) {
            messageEl.textContent = message;
        }
        openModal('profile-success-modal');
    }

    function getProfileFormPayload() {
        return {
            User_First_Name: document.getElementById('profile-first-name')?.value.trim() || '',
            User_Middle_Name: document.getElementById('profile-middle-name')?.value.trim() || '',
            User_Last_Name: document.getElementById('profile-last-name')?.value.trim() || '',
        };
    }

    function buildFullName(payload) {
        return [payload.User_First_Name, payload.User_Middle_Name, payload.User_Last_Name]
            .filter(Boolean)
            .join(' ');
    }

    async function saveProfileInformation() {
        const payload = getProfileFormPayload();
        if (!payload.User_First_Name || !payload.User_Last_Name) {
            setTextTone('admin-profile-form-status', 'First name and last name are required.', 'error');
            closeModal('profile-save-confirm-modal');
            return;
        }

        setTextTone('admin-profile-form-status', 'Saving profile information...', 'muted');

        try {
            const response = await fetch(window.adminProfileRoutes?.updateProfile, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                },
                body: JSON.stringify(payload),
            });

            const result = await response.json();
            if (!response.ok) {
                throw new Error(result.error || 'Unable to save profile information.');
            }

            const displayNameEl = document.getElementById('admin-profile-display-name');
            if (displayNameEl) {
                displayNameEl.textContent = result.full_name || buildFullName(payload);
            }

            setTextTone('admin-profile-form-status', 'Profile information saved successfully.', 'success');
            showSuccessModal(result.message || 'Profile information saved successfully.');
        } catch (error) {
            setTextTone('admin-profile-form-status', error.message || 'Unable to save profile information.', 'error');
        } finally {
            closeModal('profile-save-confirm-modal');
        }
    }

    function resetPictureModalState() {
        selectedProfilePictureFile = null;
        const fileInput = document.getElementById('admin-profile-picture-input');
        if (fileInput) {
            fileInput.value = '';
        }
        const currentImage = window.adminProfileData?.profilePictureUrl || '';
        if (currentImage) {
            updateProfilePreview(currentImage);
        }
        const fileNameEl = document.getElementById('admin-profile-selected-file-name');
        if (fileNameEl) {
            fileNameEl.textContent = 'No file selected.';
        }
        setUploadStatus('Accepted: JPG, PNG, WEBP, GIF up to 3MB.', 'muted');
    }

    function bindModalControls() {
        document.querySelectorAll('[data-close-modal]').forEach(function (button) {
            button.addEventListener('click', function () {
                const modalId = button.getAttribute('data-close-modal');
                closeModal(modalId);
            });
        });
    }

    function initProfileChart() {
        const chartCanvas = document.getElementById('admin-profile-login-chart');
        if (!chartCanvas || typeof Chart === 'undefined') return;

        const chartLabels = window.adminProfileData?.chartLabels || [];
        const chartValues = window.adminProfileData?.chartValues || [];

        new Chart(chartCanvas, {
            type: 'bar',
            data: {
                labels: chartLabels,
                datasets: [{
                    label: 'Login Count',
                    data: chartValues,
                    borderRadius: 12,
                    backgroundColor: '#4A0612',
                    hoverBackgroundColor: '#7a1528',
                    borderSkipped: false,
                    maxBarThickness: 42,
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false,
                    },
                    tooltip: {
                        backgroundColor: '#0f172a',
                        titleFont: {
                            weight: '700',
                        },
                        bodyFont: {
                            weight: '600',
                        },
                    },
                },
                scales: {
                    x: {
                        grid: {
                            display: false,
                        },
                        ticks: {
                            color: '#64748b',
                            font: {
                                weight: '700',
                            },
                        },
                    },
                    y: {
                        beginAtZero: true,
                        ticks: {
                            precision: 0,
                            color: '#64748b',
                            font: {
                                weight: '700',
                            },
                        },
                        grid: {
                            color: 'rgba(148, 163, 184, 0.18)',
                        },
                    },
                },
            },
        });
    }

    function initProfilePictureUpload() {
        const openPictureModalBtn = document.getElementById('admin-profile-open-picture-modal');
        const penTrigger = document.getElementById('admin-profile-picture-pen-trigger');
        const selectButton = document.getElementById('admin-profile-picture-select-btn');
        const savePictureButton = document.getElementById('admin-profile-picture-save-btn');
        const fileInput = document.getElementById('admin-profile-picture-input');
        const fileNameEl = document.getElementById('admin-profile-selected-file-name');

        if (!fileInput) return;

        [openPictureModalBtn, penTrigger, selectButton].forEach(function (trigger) {
            if (!trigger) return;
            trigger.addEventListener('click', function () {
                if (trigger === openPictureModalBtn) {
                    resetPictureModalState();
                    openModal('profile-picture-modal');
                    return;
                }
                fileInput.click();
            });
        });

        fileInput.addEventListener('change', async function () {
            const selectedFile = fileInput.files?.[0];
            if (!selectedFile) return;

            selectedProfilePictureFile = selectedFile;
            if (fileNameEl) {
                fileNameEl.textContent = selectedFile.name;
            }
            setUploadStatus('Selected image ready for upload.', 'muted');

            const reader = new FileReader();
            reader.onload = function (event) {
                if (event.target?.result) {
                    const modalPreview = document.getElementById('admin-profile-picture-modal-preview');
                    const modalFallback = document.getElementById('admin-profile-picture-modal-fallback');
                    if (modalPreview) {
                        modalPreview.src = event.target.result;
                        modalPreview.classList.remove('hidden');
                    }
                    if (modalFallback) {
                        modalFallback.classList.add('hidden');
                    }
                }
            };
            reader.readAsDataURL(selectedFile);
        });

        if (savePictureButton) {
            savePictureButton.addEventListener('click', async function () {
                if (!selectedProfilePictureFile) {
                    setUploadStatus('Please select an image first.', 'error');
                    return;
                }

                setUploadStatus('Uploading profile picture...', 'muted');

                const formData = new FormData();
                formData.append('profile_picture', selectedProfilePictureFile);

                try {
                    const response = await fetch(window.adminProfileRoutes?.uploadPicture, {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                            'Accept': 'application/json',
                        },
                        body: formData,
                    });

                    const payload = await response.json();
                    if (!response.ok) {
                        throw new Error(payload.error || 'Unable to upload profile picture.');
                    }

                    window.adminProfileData.profilePictureUrl = payload.image_url;
                    updateProfilePreview(payload.image_url);
                    closeModal('profile-picture-modal');
                    showSuccessModal('Profile picture updated successfully.');
                } catch (error) {
                    setUploadStatus(error.message || 'Upload failed.', 'error');
                }
            });
        }
    }

    window.addEventListener('DOMContentLoaded', function () {
        bindModalControls();
        initProfileChart();
        initProfilePictureUpload();

        const saveTrigger = document.getElementById('admin-profile-save-trigger');
        const confirmSaveBtn = document.getElementById('profile-confirm-save-btn');

        if (saveTrigger) {
            saveTrigger.addEventListener('click', function () {
                openModal('profile-save-confirm-modal');
            });
        }

        if (confirmSaveBtn) {
            confirmSaveBtn.addEventListener('click', saveProfileInformation);
        }

        if (window.adminProfileData?.profilePictureUrl) {
            updateProfilePreview(window.adminProfileData.profilePictureUrl);
        }
    });
})();
