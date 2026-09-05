

<?php $__env->startPush('styles'); ?>
    <link rel="stylesheet" href="<?php echo e(asset('css/admin-profile.css')); ?>?v=<?php echo e(time()); ?>">
<?php $__env->stopPush(); ?>

<?php $__env->startSection('admin_profile_content'); ?>
    <div id="page-admin-profile-root" class="space-y-6 animate-fade-in">
        <div class="grid grid-cols-1 xl:grid-cols-[320px_1fr] gap-6">
            <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
                <div class="profile-hero p-6 text-white">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <p class="text-[10px] font-black uppercase tracking-[0.28em] text-gold/80">Profile</p>
                            <h2 class="mt-3 text-2xl font-black tracking-tight">Administrator Identity</h2>
                            <p class="mt-2 text-sm text-white/75">Manage your account details, profile image, and login activity from one secure workspace.</p>
                        </div>
                        <div class="profile-icon-badge">
                            <i data-lucide="shield-user" class="w-6 h-6 text-gold"></i>
                        </div>
                    </div>
                </div>

                <div class="p-6">
                    <div class="profile-avatar-card">
                        <div class="profile-avatar-wrap profile-avatar-wrap-main">
                            <?php if($profilePictureUrl): ?>
                                <img id="admin-profile-picture-preview" src="<?php echo e($profilePictureUrl); ?>" alt="Admin profile picture" class="profile-avatar-image">
                            <?php else: ?>
                                <div id="admin-profile-picture-fallback" class="profile-avatar-fallback">
                                    <?php echo e(strtoupper(substr($profileUser->User_First_Name ?? 'A', 0, 1) . substr($profileUser->User_Last_Name ?? 'D', 0, 1))); ?>

                                </div>
                                <img id="admin-profile-picture-preview" src="" alt="Admin profile picture" class="profile-avatar-image hidden">
                            <?php endif; ?>
                        </div>

                        <div class="text-center">
                            <h3 id="admin-profile-display-name" class="text-lg font-black text-slate-800">
                                <?php echo e(trim(collect([$profileUser->User_First_Name, $profileUser->User_Middle_Name, $profileUser->User_Last_Name])->filter()->implode(' '))); ?>

                            </h3>
                            <p class="mt-1 text-xs font-semibold text-slate-400 uppercase tracking-widest">
                                <?php echo e($accountTypeLabel); ?> Account
                            </p>
                            <p class="mt-3 text-[11px] text-slate-500">
                                Profile picture is stored in the database as a secure blob record.
                            </p>
                        </div>

                        <div class="w-full space-y-3">
                            <button type="button" id="admin-profile-open-picture-modal" class="w-full px-4 py-3 bg-maroon text-white text-[11px] font-black rounded-xl hover:bg-maroon-800 transition-all shadow-sm flex items-center justify-center gap-2">
                                <i data-lucide="image-up" class="w-4 h-4 text-gold"></i>
                                <span>Change Profile Picture</span>
                            </button>
                            <p class="text-[11px] text-center font-semibold text-slate-400">Accepted: JPG, PNG, WEBP, GIF up to 3MB.</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="space-y-6">
                <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
                    <div class="p-5 border-b border-slate-100 bg-slate-50/60 flex items-center justify-between">
                        <div>
                            <h3 class="text-xs font-black text-slate-700 uppercase tracking-[0.25em]">Profile Information</h3>
                            <p class="mt-1 text-xs text-slate-400 font-semibold">Current user information is loaded below and can be updated from this form.</p>
                        </div>
                        <div class="profile-section-badge">
                            <i data-lucide="badge-check" class="w-4 h-4 text-gold"></i>
                        </div>
                    </div>

                    <form id="admin-profile-form" class="p-5 space-y-5">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="profile-input-group">
                                <label for="profile-account-type" class="profile-input-label">Account Type</label>
                                <div class="profile-input-icon">
                                    <i data-lucide="briefcase-business" class="w-4 h-4 text-gold"></i>
                                </div>
                                <input id="profile-account-type" type="text" class="profile-input-field profile-input-readonly" value="<?php echo e($accountTypeLabel); ?>" readonly>
                            </div>

                            <div class="profile-input-group">
                                <label for="profile-user-id" class="profile-input-label">User ID</label>
                                <div class="profile-input-icon">
                                    <i data-lucide="hash" class="w-4 h-4 text-gold"></i>
                                </div>
                                <input id="profile-user-id" type="text" class="profile-input-field profile-input-readonly" value="<?php echo e($profileUser->User_ID); ?>" readonly>
                            </div>

                            <div class="profile-input-group">
                                <label for="profile-first-name" class="profile-input-label">First Name</label>
                                <div class="profile-input-icon">
                                    <i data-lucide="user-round" class="w-4 h-4 text-gold"></i>
                                </div>
                                <input id="profile-first-name" name="User_First_Name" type="text" class="profile-input-field" value="<?php echo e($profileUser->User_First_Name); ?>" required>
                            </div>

                            <div class="profile-input-group">
                                <label for="profile-middle-name" class="profile-input-label">Middle Name</label>
                                <div class="profile-input-icon">
                                    <i data-lucide="badge-info" class="w-4 h-4 text-gold"></i>
                                </div>
                                <input id="profile-middle-name" name="User_Middle_Name" type="text" class="profile-input-field" value="<?php echo e($profileUser->User_Middle_Name); ?>">
                            </div>

                            <div class="profile-input-group md:col-span-2">
                                <label for="profile-last-name" class="profile-input-label">Last Name</label>
                                <div class="profile-input-icon">
                                    <i data-lucide="scan-face" class="w-4 h-4 text-gold"></i>
                                </div>
                                <input id="profile-last-name" name="User_Last_Name" type="text" class="profile-input-field" value="<?php echo e($profileUser->User_Last_Name); ?>" required>
                            </div>
                        </div>

                        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 border-t border-slate-100 pt-5">
                            <p id="admin-profile-form-status" class="text-[11px] font-semibold text-slate-400">Update the fields you want to change, then save the profile.</p>
                            <button type="button" id="admin-profile-save-trigger" class="px-5 py-3 bg-maroon text-white text-[11px] font-black rounded-xl hover:bg-maroon-800 transition-all shadow-sm flex items-center justify-center gap-2">
                                <i data-lucide="save" class="w-4 h-4 text-gold"></i>
                                <span>Save Profile</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
            <div class="p-5 border-b border-slate-100 bg-slate-50/60 flex items-center justify-between">
                <div>
                    <h3 class="text-xs font-black text-slate-700 uppercase tracking-[0.25em]">Login Frequency</h3>
                    <p class="mt-1 text-xs text-slate-400 font-semibold">Successful login counts for the last 7 days.</p>
                </div>
                <div class="profile-section-badge">
                    <i data-lucide="bar-chart-3" class="w-4 h-4 text-gold"></i>
                </div>
            </div>

            <div class="p-5">
                <div class="profile-chart-panel">
                    <canvas id="admin-profile-login-chart" height="120"></canvas>
                </div>
            </div>
        </div>

        <div id="profile-save-confirm-modal" class="profile-modal hidden">
            <div class="profile-modal-backdrop" data-close-modal="profile-save-confirm-modal"></div>
            <div class="profile-modal-panel max-w-md">
                <div class="profile-modal-header">
                    <div class="profile-modal-header-icon">
                        <i data-lucide="badge-alert" class="w-5 h-5 text-gold"></i>
                    </div>
                    <div>
                        <h3 class="profile-modal-title">Confirm Profile Update</h3>
                        <p class="profile-modal-subtitle">Please review before saving the new profile information.</p>
                    </div>
                </div>
                <div class="profile-modal-body">
                    <p class="text-sm text-slate-600 font-semibold leading-relaxed">Are you sure you want to save the updated profile information?</p>
                </div>
                <div class="profile-modal-footer">
                    <button type="button" class="profile-btn-secondary" data-close-modal="profile-save-confirm-modal">Cancel</button>
                    <button type="button" id="profile-confirm-save-btn" class="profile-btn-primary">Confirm Save</button>
                </div>
            </div>
        </div>

        <div id="profile-picture-modal" class="profile-modal hidden">
            <div class="profile-modal-backdrop" data-close-modal="profile-picture-modal"></div>
            <div class="profile-modal-panel max-w-2xl">
                <div class="profile-modal-header">
                    <div class="profile-modal-header-icon">
                        <i data-lucide="image-up" class="w-5 h-5 text-gold"></i>
                    </div>
                    <div>
                        <h3 class="profile-modal-title">Change Profile Picture</h3>
                        <p class="profile-modal-subtitle">View the current image, choose a new one, and preview it before upload.</p>
                    </div>
                </div>
                <div class="profile-modal-body">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                        <div class="profile-picture-preview-card">
                            <span class="profile-input-label">Current / Selected Image</span>
                            <div class="profile-avatar-wrap profile-avatar-wrap-modal">
                                <?php if($profilePictureUrl): ?>
                                    <img id="admin-profile-picture-modal-preview" src="<?php echo e($profilePictureUrl); ?>" alt="Profile preview" class="profile-avatar-image">
                                <?php else: ?>
                                    <div id="admin-profile-picture-modal-fallback" class="profile-avatar-fallback">
                                        <?php echo e(strtoupper(substr($profileUser->User_First_Name ?? 'A', 0, 1) . substr($profileUser->User_Last_Name ?? 'D', 0, 1))); ?>

                                    </div>
                                    <img id="admin-profile-picture-modal-preview" src="" alt="Profile preview" class="profile-avatar-image hidden">
                                <?php endif; ?>
                                <button type="button" id="admin-profile-picture-pen-trigger" class="profile-picture-pen-btn">
                                    <i data-lucide="pen" class="w-4 h-4 text-gold"></i>
                                </button>
                            </div>
                        </div>

                        <div class="space-y-4">
                            <div class="profile-upload-dropzone">
                                <input type="file" id="admin-profile-picture-input" class="hidden" accept="image/png,image/jpeg,image/webp,image/gif">
                                <button type="button" id="admin-profile-picture-select-btn" class="profile-btn-primary w-full">
                                    Select New Image
                                </button>
                                <p id="admin-profile-upload-status" class="text-[11px] text-center font-semibold text-slate-400">Accepted: JPG, PNG, WEBP, GIF up to 3MB.</p>
                            </div>

                            <div class="rounded-2xl border border-slate-100 bg-slate-50 p-4">
                                <span class="profile-input-label">Selected File</span>
                                <p id="admin-profile-selected-file-name" class="mt-2 text-sm font-bold text-slate-700">No file selected.</p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="profile-modal-footer">
                    <button type="button" class="profile-btn-secondary" data-close-modal="profile-picture-modal">Cancel</button>
                    <button type="button" id="admin-profile-picture-save-btn" class="profile-btn-primary">Save Picture</button>
                </div>
            </div>
        </div>

        <div id="profile-success-modal" class="profile-modal hidden">
            <div class="profile-modal-backdrop" data-close-modal="profile-success-modal"></div>
            <div class="profile-modal-panel max-w-md">
                <div class="profile-modal-header">
                    <div class="profile-modal-header-icon profile-modal-header-icon-success">
                        <i data-lucide="check-circle-2" class="w-5 h-5 text-gold"></i>
                    </div>
                    <div>
                        <h3 class="profile-modal-title">Success</h3>
                        <p class="profile-modal-subtitle">The profile update has been completed.</p>
                    </div>
                </div>
                <div class="profile-modal-body">
                    <p id="profile-success-message" class="text-sm text-slate-600 font-semibold leading-relaxed">Profile saved successfully.</p>
                </div>
                <div class="profile-modal-footer">
                    <button type="button" class="profile-btn-primary w-full" data-close-modal="profile-success-modal">Done</button>
                </div>
            </div>
        </div>
    </div>
<?php $__env->stopSection(); ?>

<?php $__env->startPush('scripts'); ?>
    <script>
        window.adminProfileData = {
            chartLabels: <?php echo json_encode($loginGraphLabels, 15, 512) ?>,
            chartValues: <?php echo json_encode($loginGraphData, 15, 512) ?>,
            profilePictureUrl: <?php echo json_encode($profilePictureUrl, 15, 512) ?>,
        };
        window.adminProfileRoutes = {
            updateProfile: "<?php echo e(route('special.profile.update')); ?>",
            uploadPicture: "<?php echo e(route('special.profile.picture.update')); ?>"
        };
    </script>
    <script src="<?php echo e(asset('js/admin-profile.js')); ?>?v=<?php echo e(time()); ?>"></script>
<?php $__env->stopPush(); ?>



<?php echo $__env->make('partials.special_user.special_sidebar_navbar', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\xampp\htdocs\hatdog\resources\views\partials\special_user\Special_Profile.blade.php ENDPATH**/ ?>