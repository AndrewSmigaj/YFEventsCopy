/**
 * Reusable Image Upload Component
 * Supports drag-and-drop, file selection, and camera capture
 * Can be used across different modules
 */

class ImageUploadComponent {
    constructor(config) {
        this.config = {
            container: config.container || '#image-upload-container',
            uploadUrl: config.uploadUrl || '/seller/upload-image.php',
            maxFileSize: config.maxFileSize || 5 * 1024 * 1024, // 5MB default
            acceptedTypes: config.acceptedTypes || ['image/jpeg', 'image/png', 'image/gif', 'image/webp'],
            maxImages: config.maxImages || 10,
            itemId: config.itemId || null,
            onUploadComplete: config.onUploadComplete || null,
            onError: config.onError || null,
            enableCamera: config.enableCamera !== false // default true
        };
        
        this.images = [];
        this.init();
    }
    
    init() {
        this.container = document.querySelector(this.config.container);
        if (!this.container) {
            console.error('Upload container not found');
            return;
        }
        
        this.render();
        this.attachEventListeners();
    }
    
    render() {
        const isMobile = /iPhone|iPad|iPod|Android/i.test(navigator.userAgent);
        const cameraOption = this.config.enableCamera && isMobile ? 'capture="environment"' : '';
        
        this.container.innerHTML = `
            <div class="image-upload-wrapper">
                <div class="upload-area" id="upload-drop-zone">
                    <div class="upload-prompt">
                        <i class="bi bi-cloud-upload"></i>
                        <h5>Drop images here or click to upload</h5>
                        <p class="text-muted">Maximum ${this.config.maxImages} images, up to ${this.formatFileSize(this.config.maxFileSize)} each</p>
                        <input type="file" id="file-input" class="d-none" accept="image/*" multiple ${cameraOption}>
                        <button type="button" class="btn btn-primary" onclick="document.getElementById('file-input').click()">
                            <i class="bi bi-upload"></i> Choose Files
                        </button>
                        ${this.config.enableCamera && isMobile ? `
                            <button type="button" class="btn btn-outline-primary ms-2" onclick="document.getElementById('camera-input').click()">
                                <i class="bi bi-camera"></i> Take Photo
                            </button>
                            <input type="file" id="camera-input" class="d-none" accept="image/*" capture="environment">
                        ` : ''}
                    </div>
                </div>
                <div class="image-preview-container" id="image-preview-container"></div>
                <div class="upload-progress d-none" id="upload-progress">
                    <div class="progress">
                        <div class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width: 0%"></div>
                    </div>
                </div>
            </div>
            <style>
                .image-upload-wrapper {
                    margin: 20px 0;
                }
                .upload-area {
                    border: 2px dashed #dee2e6;
                    border-radius: 10px;
                    padding: 40px;
                    text-align: center;
                    background: #f8f9fa;
                    transition: all 0.3s;
                    cursor: pointer;
                }
                .upload-area.drag-over {
                    border-color: #667eea;
                    background: #f0f3ff;
                }
                .upload-area i {
                    font-size: 3rem;
                    color: #667eea;
                    margin-bottom: 15px;
                }
                .image-preview-container {
                    display: grid;
                    grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
                    gap: 15px;
                    margin-top: 20px;
                }
                .image-preview {
                    position: relative;
                    border-radius: 8px;
                    overflow: hidden;
                    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
                }
                .image-preview img {
                    width: 100%;
                    height: 150px;
                    object-fit: cover;
                }
                .image-preview .remove-btn {
                    position: absolute;
                    top: 5px;
                    right: 5px;
                    background: rgba(255,255,255,0.9);
                    border: none;
                    border-radius: 50%;
                    width: 30px;
                    height: 30px;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    cursor: pointer;
                    box-shadow: 0 2px 4px rgba(0,0,0,0.2);
                }
                .image-preview .primary-badge {
                    position: absolute;
                    bottom: 5px;
                    left: 5px;
                    background: #667eea;
                    color: white;
                    padding: 2px 8px;
                    border-radius: 4px;
                    font-size: 0.75rem;
                }
                .upload-progress {
                    margin-top: 20px;
                }
            </style>
        `;
    }
    
    attachEventListeners() {
        const dropZone = document.getElementById('upload-drop-zone');
        const fileInput = document.getElementById('file-input');
        const cameraInput = document.getElementById('camera-input');
        
        // File input change
        fileInput.addEventListener('change', (e) => this.handleFileSelect(e.target.files));
        
        // Camera input change
        if (cameraInput) {
            cameraInput.addEventListener('change', (e) => this.handleFileSelect(e.target.files));
        }
        
        // Drag and drop events
        dropZone.addEventListener('dragenter', (e) => {
            e.preventDefault();
            dropZone.classList.add('drag-over');
        });
        
        dropZone.addEventListener('dragover', (e) => {
            e.preventDefault();
        });
        
        dropZone.addEventListener('dragleave', (e) => {
            e.preventDefault();
            dropZone.classList.remove('drag-over');
        });
        
        dropZone.addEventListener('drop', (e) => {
            e.preventDefault();
            dropZone.classList.remove('drag-over');
            this.handleFileSelect(e.dataTransfer.files);
        });
        
        // Click to upload
        dropZone.addEventListener('click', (e) => {
            if (e.target.tagName !== 'BUTTON' && e.target.tagName !== 'INPUT') {
                fileInput.click();
            }
        });
    }
    
    handleFileSelect(files) {
        const remainingSlots = this.config.maxImages - this.images.length;
        const filesToUpload = Array.from(files).slice(0, remainingSlots);
        
        filesToUpload.forEach(file => {
            if (this.validateFile(file)) {
                this.uploadFile(file);
            }
        });
    }
    
    validateFile(file) {
        // Check file type
        if (!this.config.acceptedTypes.includes(file.type)) {
            this.showError(`Invalid file type: ${file.name}. Only images are allowed.`);
            return false;
        }
        
        // Check file size
        if (file.size > this.config.maxFileSize) {
            this.showError(`File too large: ${file.name}. Maximum size is ${this.formatFileSize(this.config.maxFileSize)}.`);
            return false;
        }
        
        return true;
    }
    
    uploadFile(file) {
        const formData = new FormData();
        formData.append('image', file);
        if (this.config.itemId) {
            formData.append('item_id', this.config.itemId);
        }
        
        // Show progress
        const progressBar = document.querySelector('#upload-progress .progress-bar');
        document.getElementById('upload-progress').classList.remove('d-none');
        
        fetch(this.config.uploadUrl, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                this.addImagePreview(data);
                if (this.config.onUploadComplete) {
                    this.config.onUploadComplete(data);
                }
            } else {
                this.showError(data.error || 'Upload failed');
            }
        })
        .catch(error => {
            this.showError('Network error during upload');
        })
        .finally(() => {
            document.getElementById('upload-progress').classList.add('d-none');
            progressBar.style.width = '0%';
        });
    }
    
    addImagePreview(imageData) {
        const container = document.getElementById('image-preview-container');
        const preview = document.createElement('div');
        preview.className = 'image-preview';
        preview.dataset.imageUrl = imageData.url;
        
        preview.innerHTML = `
            <img src="${imageData.url}" alt="Uploaded image">
            <button type="button" class="remove-btn" onclick="imageUploader.removeImage('${imageData.url}')">
                <i class="bi bi-x"></i>
            </button>
            ${imageData.is_primary ? '<span class="primary-badge">Primary</span>' : ''}
        `;
        
        container.appendChild(preview);
        this.images.push(imageData);
        
        // Hide upload area if max images reached
        if (this.images.length >= this.config.maxImages) {
            document.getElementById('upload-drop-zone').style.display = 'none';
        }
    }
    
    removeImage(url) {
        const preview = document.querySelector(`[data-image-url="${url}"]`);
        if (preview) {
            preview.remove();
        }
        
        this.images = this.images.filter(img => img.url !== url);
        
        // Show upload area if under max images
        if (this.images.length < this.config.maxImages) {
            document.getElementById('upload-drop-zone').style.display = 'block';
        }
    }
    
    getImages() {
        return this.images;
    }
    
    formatFileSize(bytes) {
        if (bytes < 1024) return bytes + ' bytes';
        else if (bytes < 1048576) return Math.round(bytes / 1024) + ' KB';
        else return Math.round(bytes / 1048576) + ' MB';
    }
    
    showError(message) {
        if (this.config.onError) {
            this.config.onError(message);
        } else {
            alert(message);
        }
    }
}

// Make it globally available
window.ImageUploadComponent = ImageUploadComponent;