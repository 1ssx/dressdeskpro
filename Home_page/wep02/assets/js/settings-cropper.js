/**
 * Image Cropper Integration for Settings Page
 * Handles logo cropping before upload
 */

// This file extends settings.js with cropper functionality
(function () {
    // Wait for DOM and settings.js to load
    window.addEventListener('DOMContentLoaded', function () {
        const STORE_API_URL = 'api/store_settings.php';

        // Cropper Elements
        const logoUpload = document.getElementById('logo-upload');
        const cropperModal = document.getElementById('cropper-modal');
        const imageToCrop = document.getElementById('image-to-crop');
        const closeCropperModal = document.getElementById('close-cropper-modal');
        const rotateLeftBtn = document.getElementById('rotate-left-btn');
        const rotateRightBtn = document.getElementById('rotate-right-btn');
        const flipHorizontalBtn = document.getElementById('flip-horizontal-btn');
        const resetCropBtn = document.getElementById('reset-crop-btn');
        const cropAndUploadBtn = document.getElementById('crop-and-upload-btn');
        const cancelCropBtn = document.getElementById('cancel-crop-btn');

        let cropper = null;
        let selectedFile = null;

        // Override logo upload to use cropper
        if (logoUpload) {
            // Remove existing event listeners by cloning
            const newLogoUpload = logoUpload.cloneNode(true);
            logoUpload.parentNode.replaceChild(newLogoUpload, logoUpload);

            newLogoUpload.addEventListener('change', function (e) {
                const file = e.target.files[0];
                if (!file) return;

                // Validate file type
                const allowedTypes = ['image/png', 'image/jpeg', 'image/jpg'];
                if (!allowedTypes.includes(file.type)) {
                    alert('نوع الملف غير مدعوم. يرجى اختيار صورة بصيغة PNG أو JPG');
                    newLogoUpload.value = '';
                    return;
                }

                // Validate file size (max 2MB)
                if (file.size > 2 * 1024 * 1024) {
                    alert('حجم الصورة كبير جداً. الحد الأقصى 2MB');
                    newLogoUpload.value = '';
                    return;
                }

                // Store file and open cropper
                selectedFile = file;
                openCropper(file);
            });
        }

        // Open cropper modal
        function openCropper(file) {
            // Show modal immediately with loading state
            cropperModal.style.display = 'block';

            // Add loading indicator
            const loadingDiv = document.createElement('div');
            loadingDiv.className = 'cropper-loading active';
            loadingDiv.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
            cropperModal.querySelector('.cropper-modal-content').appendChild(loadingDiv);

            const reader = new FileReader();

            reader.onerror = function (error) {
                console.error('FileReader error:', error);
                alert('فشل قراءة الملف. يرجى المحاولة مرة أخرى.');
                closeCropperModalFunc();
            };

            reader.onload = function (e) {
                try {
                    // Set image source
                    imageToCrop.src = e.target.result;

                    // CRITICAL: Wait for image to fully load before initializing Cropper
                    imageToCrop.onload = function () {
                        try {
                            // Destroy existing cropper instance to prevent conflicts
                            if (cropper) {
                                console.log('Destroying existing cropper instance');
                                cropper.destroy();
                                cropper = null;
                            }

                            // Small delay to ensure DOM is fully updated
                            setTimeout(() => {
                                try {
                                    // Verify Cropper library is loaded
                                    if (typeof Cropper === 'undefined') {
                                        throw new Error('Cropper library not loaded');
                                    }

                                    // Verify image element is visible
                                    if (!imageToCrop.offsetParent) {
                                        console.warn('Image element not visible, attempting to show');
                                        imageToCrop.style.display = 'block';
                                    }

                                    console.log('Initializing Cropper with image dimensions:', {
                                        width: imageToCrop.naturalWidth,
                                        height: imageToCrop.naturalHeight
                                    });

                                    // Initialize new Cropper instance
                                    cropper = new Cropper(imageToCrop, {
                                        aspectRatio: 1, // Square crop for logo
                                        viewMode: 1,
                                        dragMode: 'move',
                                        autoCropArea: 0.8,
                                        restore: false,
                                        guides: true,
                                        center: true,
                                        highlight: false,
                                        cropBoxMovable: true,
                                        cropBoxResizable: true,
                                        toggleDragModeOnDblclick: false,
                                        ready: function () {
                                            console.log('Cropper ready');
                                            // Remove loading indicator
                                            if (loadingDiv && loadingDiv.parentNode) {
                                                loadingDiv.remove();
                                            }
                                        },
                                        cropstart: function () {
                                            console.log('Crop started');
                                        },
                                        cropmove: function () {
                                            // Optional: Add debounced logging if needed
                                        },
                                        cropend: function () {
                                            console.log('Crop ended');
                                        },
                                        zoom: function (e) {
                                            console.log('Zoom level:', e.detail.ratio);
                                        }
                                    });

                                    console.log('Cropper initialized successfully');

                                } catch (cropperError) {
                                    console.error('Cropper initialization error:', cropperError);

                                    // Remove loading indicator
                                    if (loadingDiv && loadingDiv.parentNode) {
                                        loadingDiv.remove();
                                    }

                                    // Fallback: Allow upload without cropping
                                    if (confirm('فشل تحميل محرر الصور. هل تريد تحميل الصورة بدون قص؟')) {
                                        uploadWithoutCropping(selectedFile);
                                    } else {
                                        closeCropperModalFunc();
                                    }
                                }
                            }, 100); // Small delay ensures DOM is ready

                        } catch (imageLoadError) {
                            console.error('Image load handler error:', imageLoadError);
                            alert('حدث خطأ أثناء تحميل الصورة.');
                            closeCropperModalFunc();
                        }
                    };

                    // Handle image load errors
                    imageToCrop.onerror = function (error) {
                        console.error('Image load error:', error);
                        alert('فشل تحميل الصورة. يرجى التأكد من صحة الملف.');
                        closeCropperModalFunc();
                    };

                } catch (dataUrlError) {
                    console.error('Error setting image src:', dataUrlError);
                    alert('فشل معالجة الصورة.');
                    closeCropperModalFunc();
                }
            };

            // Start reading the file
            reader.readAsDataURL(file);
        }

        // Cropper controls
        if (rotateLeftBtn) {
            rotateLeftBtn.addEventListener('click', function () {
                if (cropper) cropper.rotate(-90);
            });
        }

        if (rotateRightBtn) {
            rotateRightBtn.addEventListener('click', function () {
                if (cropper) cropper.rotate(90);
            });
        }

        if (flipHorizontalBtn) {
            flipHorizontalBtn.addEventListener('click', function () {
                if (cropper) {
                    const data = cropper.getData();
                    cropper.scaleX(data.scaleX === -1 ? 1 : -1);
                }
            });
        }

        if (resetCropBtn) {
            resetCropBtn.addEventListener('click', function () {
                if (cropper) cropper.reset();
            });
        }

        // Crop and upload
        if (cropAndUploadBtn) {
            cropAndUploadBtn.addEventListener('click', async function () {
                if (!cropper) return;

                try {
                    // Get cropped canvas
                    const canvas = cropper.getCroppedCanvas({
                        width: 500,
                        height: 500,
                        imageSmoothingEnabled: true,
                        imageSmoothingQuality: 'high',
                    });

                    // Convert to blob
                    canvas.toBlob(async function (blob) {
                        if (!blob) {
                            alert('فشل معالجة الصورة');
                            return;
                        }

                        // Create form data
                        const formData = new FormData();
                        formData.append('logo', blob, selectedFile.name);

                        try {
                            const response = await fetch(`${STORE_API_URL}?action=upload_logo`, {
                                method: 'POST',
                                body: formData
                            });

                            if (!response.ok) {
                                throw new Error(`HTTP ${response.status}`);
                            }

                            const result = await response.json();
                            if (result.status !== 'success') {
                                throw new Error(result.message || 'Failed to upload logo');
                            }

                            alert('تم تحميل الشعار بنجاح');
                            closeCropperModalFunc();

                            // Reload page to update logo
                            setTimeout(() => {
                                window.location.reload();
                            }, 1000);
                        } catch (error) {
                            console.error('Error uploading logo:', error);
                            alert('فشل تحميل الشعار: ' + error.message);
                        }
                    }, 'image/png', 0.95);
                } catch (error) {
                    console.error('Error cropping image:', error);
                    alert('فشل قص الصورة: ' + error.message);
                }
            });
        }

        // Close cropper modal
        function closeCropperModalFunc() {
            cropperModal.style.display = 'none';
            if (cropper) {
                cropper.destroy();
                cropper = null;
            }
            selectedFile = null;
            if (document.getElementById('logo-upload')) {
                document.getElementById('logo-upload').value = '';
            }
        }

        if (closeCropperModal) {
            closeCropperModal.addEventListener('click', closeCropperModalFunc);
        }

        if (cancelCropBtn) {
            cancelCropBtn.addEventListener('click', closeCropperModalFunc);
        }

        // Close cropper when clicking outside
        window.addEventListener('click', function (event) {
            if (event.target === cropperModal) {
                closeCropperModalFunc();
            }
        });

        // Fallback: Upload without cropping if cropper fails
        async function uploadWithoutCropping(file) {
            if (!file) {
                alert('لم يتم اختيار أي ملف');
                return;
            }

            try {
                const formData = new FormData();
                formData.append('logo', file);

                const response = await fetch(`${STORE_API_URL}?action=upload_logo`, {
                    method: 'POST',
                    body: formData
                });

                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}`);
                }

                const result = await response.json();
                if (result.status !== 'success') {
                    throw new Error(result.message || 'Failed to upload logo');
                }

                alert('تم تحميل الشعار بنجاح');
                closeCropperModalFunc();

                // Reload page to update logo
                setTimeout(() => {
                    window.location.reload();
                }, 1000);
            } catch (error) {
                console.error('Error uploading without cropping:', error);
                alert('فشل تحميل الشعار: ' + error.message);
            }
        }
    });
})();
