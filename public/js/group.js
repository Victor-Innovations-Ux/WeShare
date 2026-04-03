// Configuration
const API_URL = window.location.origin + '/api';

// State management
let authToken = localStorage.getItem('auth_token');
let currentGroup = null;
let currentPhotos = [];
let currentUser = null;
let currentFilter = 'all';
let currentView = 'grid';

// DOM Elements
const groupName = document.getElementById('groupName');
const participantCount = document.getElementById('participantCount');
const photoCount = document.getElementById('photoCount');
const photosContainer = document.getElementById('photosContainer');
const emptyState = document.getElementById('emptyState');
const loadingState = document.getElementById('loadingState');
const uploadBtn = document.getElementById('uploadBtn');
const shareBtn = document.getElementById('shareBtn');
const uploadModal = document.getElementById('uploadModal');
const shareModal = document.getElementById('shareModal');
const photoViewerModal = document.getElementById('photoViewerModal');
const uploadArea = document.getElementById('uploadArea');
const photoInput = document.getElementById('photoInput');
const previewContainer = document.getElementById('previewContainer');
const uploadSubmitBtn = document.getElementById('uploadSubmitBtn');
const uploadForm = document.getElementById('uploadForm');

// Selected files for upload
let selectedFiles = [];

// Initialize
async function init() {
    // Get share code from URL
    const urlParams = new URLSearchParams(window.location.search);
    const shareCode = urlParams.get('id');

    if (!shareCode) {
        window.location.href = '/';
        return;
    }

    // Check authentication
    if (!authToken) {
        // Redirect to join page
        showJoinPrompt(shareCode);
        return;
    }

    // Load user info
    await loadUserInfo();

    // Load group info
    await loadGroup(shareCode);

    // Load photos
    await loadPhotos();

    // Setup event listeners
    setupEventListeners();
}

// Show join prompt for unauthenticated users
function showJoinPrompt(shareCode) {
    document.body.innerHTML = `
        <div class="join-prompt">
            <div class="join-card">
                <h2>Rejoindre le groupe</h2>
                <p>Entrez votre nom pour accéder aux photos</p>
                <form id="quickJoinForm">
                    <input type="hidden" name="share_code" value="${shareCode}">
                    <input type="text" name="name" placeholder="Votre nom" required>
                    <button type="submit" class="btn btn-primary">Rejoindre</button>
                </form>
            </div>
        </div>
        <style>
            .join-prompt {
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            }
            .join-card {
                background: white;
                padding: 2rem;
                border-radius: 1rem;
                box-shadow: var(--shadow-lg);
                max-width: 400px;
                width: 90%;
            }
            .join-card h2 {
                margin-bottom: 1rem;
            }
            .join-card input {
                width: 100%;
                padding: 0.75rem;
                border: 1px solid var(--border-color);
                border-radius: 0.5rem;
                margin: 1rem 0;
            }
        </style>
    `;

    document.getElementById('quickJoinForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        const formData = new FormData(e.target);

        try {
            const response = await fetch(`${API_URL}/auth/join`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    share_code: formData.get('share_code'),
                    name: formData.get('name')
                })
            });

            if (response.ok) {
                const data = await response.json();
                localStorage.setItem('auth_token', data.data.token);
                window.location.reload();
            } else {
                const error = await response.json();
                alert(error.message || 'Erreur lors de la connexion');
            }
        } catch (error) {
            alert('Erreur de connexion');
        }
    });
}

// Load user info
async function loadUserInfo() {
    try {
        const response = await fetch(`${API_URL}/auth/me`, {
            headers: {
                'Authorization': `Bearer ${authToken}`
            }
        });

        if (response.ok) {
            const data = await response.json();
            currentUser = data.data;
        } else {
            // Invalid token
            localStorage.removeItem('auth_token');
            window.location.reload();
        }
    } catch (error) {
        console.error('Error loading user info:', error);
    }
}

// Load group information
async function loadGroup(shareCode) {
    try {
        const response = await fetch(`${API_URL}/groups/${shareCode}`, {
            headers: {
                'Authorization': `Bearer ${authToken}`
            }
        });

        if (response.ok) {
            const data = await response.json();
            currentGroup = data.data;
            updateGroupUI();
        } else {
            window.location.href = '/';
        }
    } catch (error) {
        console.error('Error loading group:', error);
    }
}

// Update group UI
function updateGroupUI() {
    groupName.textContent = currentGroup.name;
    participantCount.textContent = `${currentGroup.statistics.participant_count} participants`;
    photoCount.textContent = `${currentGroup.statistics.photo_count} photos`;

    // Setup share modal
    document.getElementById('shareCodeDisplay').textContent = currentGroup.share_code;
    const shareLink = `${window.location.origin}/group.html?id=${currentGroup.share_code}`;
    document.getElementById('shareLinkInput').value = shareLink;
}

// Load photos
async function loadPhotos() {
    showLoading(true);

    try {
        const response = await fetch(`${API_URL}/groups/${currentGroup.id}/photos`, {
            headers: {
                'Authorization': `Bearer ${authToken}`
            }
        });

        if (response.ok) {
            const data = await response.json();
            currentPhotos = data.data;
            displayPhotos();
        }
    } catch (error) {
        console.error('Error loading photos:', error);
    }

    showLoading(false);
}

// Display photos
function displayPhotos() {
    // Filter photos
    let photos = currentPhotos;
    if (currentFilter === 'mine') {
        photos = currentPhotos.filter(photo => {
            if (currentUser.type === 'user') {
                return photo.uploader?.type === 'user' && photo.uploader?.name === currentUser.user.name;
            } else if (currentUser.type === 'participant') {
                return photo.uploader?.name === currentUser.participant.name;
            }
            return false;
        });
    }

    // Update photo count
    photoCount.textContent = `${photos.length} photos`;

    if (photos.length === 0) {
        photosContainer.style.display = 'none';
        emptyState.style.display = 'block';
        return;
    }

    photosContainer.style.display = currentView === 'grid' ? 'grid' : 'block';
    emptyState.style.display = 'none';

    // Clear container
    photosContainer.innerHTML = '';
    photosContainer.className = currentView === 'grid' ? 'photos-grid' : 'photos-list';

    // Render photos
    photos.forEach(photo => {
        if (currentView === 'grid') {
            photosContainer.appendChild(createPhotoCard(photo));
        } else {
            photosContainer.appendChild(createPhotoListItem(photo));
        }
    });
}

// Create photo card
function createPhotoCard(photo) {
    const card = document.createElement('div');
    card.className = 'photo-card';
    card.onclick = () => openPhotoViewer(photo);

    const img = document.createElement('img');
    img.src = `${API_URL}/photos/download/${photo.id}`;
    img.alt = photo.original_name;

    const meta = document.createElement('div');
    meta.className = 'photo-meta';

    if (photo.uploader?.picture) {
        const avatar = document.createElement('img');
        avatar.src = photo.uploader.picture;
        avatar.alt = photo.uploader.name;
        meta.appendChild(avatar);
    } else {
        const avatar = document.createElement('div');
        avatar.className = 'default-avatar';
        avatar.textContent = photo.uploader?.name?.[0] || '?';
        meta.appendChild(avatar);
    }

    const name = document.createElement('span');
    name.textContent = photo.uploader?.name || 'Anonyme';
    meta.appendChild(name);

    card.appendChild(img);
    card.appendChild(meta);

    return card;
}

// Create photo list item
function createPhotoListItem(photo) {
    const item = document.createElement('div');
    item.className = 'photo-list-item';
    item.onclick = () => openPhotoViewer(photo);

    const img = document.createElement('img');
    img.src = `${API_URL}/photos/download/${photo.id}`;
    img.alt = photo.original_name;

    const info = document.createElement('div');
    info.className = 'photo-list-info';

    const title = document.createElement('h4');
    title.textContent = photo.original_name;

    const meta = document.createElement('div');
    meta.className = 'photo-list-meta';
    meta.textContent = `Par ${photo.uploader?.name || 'Anonyme'} • ${formatDate(photo.uploaded_at)}`;

    info.appendChild(title);
    info.appendChild(meta);

    item.appendChild(img);
    item.appendChild(info);

    return item;
}

// Open photo viewer
function openPhotoViewer(photo) {
    const viewer = document.getElementById('photoViewerModal');
    const image = document.getElementById('viewerImage');
    const uploaderAvatar = document.getElementById('uploaderAvatar');
    const uploaderName = document.getElementById('uploaderName');
    const uploadDate = document.getElementById('uploadDate');
    const deleteBtn = document.getElementById('deletePhotoBtn');

    image.src = `${API_URL}/photos/download/${photo.id}`;
    uploaderName.textContent = photo.uploader?.name || 'Anonyme';
    uploadDate.textContent = formatDate(photo.uploaded_at);

    if (photo.uploader?.picture) {
        uploaderAvatar.src = photo.uploader.picture;
        uploaderAvatar.style.display = 'block';
    } else {
        uploaderAvatar.style.display = 'none';
    }

    // Show delete button if user can delete
    const canDelete =
        (currentUser.type === 'user' && currentUser.user.id === currentGroup.creator_id) ||
        (currentUser.type === 'user' && photo.uploader?.type === 'user' && photo.uploader?.name === currentUser.user.name) ||
        (currentUser.type === 'participant' && photo.uploader?.name === currentUser.participant.name);

    deleteBtn.style.display = canDelete ? 'block' : 'none';
    deleteBtn.onclick = () => deletePhoto(photo.id);

    showModal(viewer);
}

// Delete photo
async function deletePhoto(photoId) {
    if (!confirm('Voulez-vous vraiment supprimer cette photo ?')) {
        return;
    }

    try {
        const response = await fetch(`${API_URL}/photos/${photoId}`, {
            method: 'DELETE',
            headers: {
                'Authorization': `Bearer ${authToken}`
            }
        });

        if (response.ok) {
            hideModal(photoViewerModal);
            await loadPhotos();
            showMessage('Photo supprimée');
        } else {
            showError('Erreur lors de la suppression');
        }
    } catch (error) {
        showError('Erreur de connexion');
    }
}

// Setup event listeners
function setupEventListeners() {
    // Filter buttons
    document.querySelectorAll('.filter-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            document.querySelector('.filter-btn.active').classList.remove('active');
            btn.classList.add('active');
            currentFilter = btn.dataset.filter;
            displayPhotos();
        });
    });

    // View buttons
    document.querySelectorAll('.view-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            document.querySelector('.view-btn.active').classList.remove('active');
            btn.classList.add('active');
            currentView = btn.dataset.view;
            displayPhotos();
        });
    });

    // Upload button
    uploadBtn.addEventListener('click', () => showModal(uploadModal));

    // Share button
    shareBtn.addEventListener('click', () => showModal(shareModal));

    // Upload area
    uploadArea.addEventListener('click', () => photoInput.click());

    // Drag and drop
    uploadArea.addEventListener('dragover', (e) => {
        e.preventDefault();
        uploadArea.classList.add('dragging');
    });

    uploadArea.addEventListener('dragleave', () => {
        uploadArea.classList.remove('dragging');
    });

    uploadArea.addEventListener('drop', (e) => {
        e.preventDefault();
        uploadArea.classList.remove('dragging');
        handleFiles(e.dataTransfer.files);
    });

    // File input change
    photoInput.addEventListener('change', (e) => {
        handleFiles(e.target.files);
    });

    // Upload form
    uploadForm.addEventListener('submit', handleUpload);

    // Copy buttons
    document.getElementById('copyCodeBtn').addEventListener('click', () => {
        navigator.clipboard.writeText(currentGroup.share_code);
        showMessage('Code copié !');
    });

    document.getElementById('copyLinkBtn').addEventListener('click', () => {
        const link = document.getElementById('shareLinkInput').value;
        navigator.clipboard.writeText(link);
        showMessage('Lien copié !');
    });

    // Modal close buttons
    document.querySelectorAll('.modal-close').forEach(btn => {
        btn.addEventListener('click', () => hideModal(btn.closest('.modal')));
    });

    // Close modals when clicking outside
    document.querySelectorAll('.modal').forEach(modal => {
        modal.addEventListener('click', (e) => {
            if (e.target === modal) {
                hideModal(modal);
            }
        });
    });
}

// Handle files selection
function handleFiles(files) {
    selectedFiles = Array.from(files).filter(file =>
        file.type.startsWith('image/')
    );

    if (selectedFiles.length === 0) {
        showError('Veuillez sélectionner des images');
        return;
    }

    // Show previews
    previewContainer.innerHTML = '';
    selectedFiles.forEach((file, index) => {
        const reader = new FileReader();
        reader.onload = (e) => {
            const preview = document.createElement('div');
            preview.className = 'preview-item';

            const img = document.createElement('img');
            img.src = e.target.result;

            const removeBtn = document.createElement('button');
            removeBtn.className = 'preview-remove';
            removeBtn.innerHTML = '×';
            removeBtn.onclick = () => removeFile(index);

            preview.appendChild(img);
            preview.appendChild(removeBtn);
            previewContainer.appendChild(preview);
        };
        reader.readAsDataURL(file);
    });

    uploadSubmitBtn.style.display = selectedFiles.length > 0 ? 'block' : 'none';
}

// Remove file from selection
function removeFile(index) {
    selectedFiles.splice(index, 1);
    handleFiles(selectedFiles);
}

// Handle upload
async function handleUpload(e) {
    e.preventDefault();

    console.log('=== DEBUT UPLOAD ===');
    console.log('selectedFiles:', selectedFiles);
    console.log('currentGroup:', currentGroup);
    console.log('authToken:', authToken ? 'présent' : 'MANQUANT');

    if (selectedFiles.length === 0) {
        showError('Aucune photo sélectionnée');
        return;
    }

    uploadSubmitBtn.disabled = true;
    uploadSubmitBtn.textContent = 'Téléversement en cours...';

    let successCount = 0;
    let errorCount = 0;
    let errorMessages = [];

    for (const file of selectedFiles) {
        console.log(`\n--- Upload de ${file.name} (${file.size} bytes) ---`);

        const formData = new FormData();
        formData.append('photo', file);
        formData.append('group_id', currentGroup.id);

        console.log('FormData créé:', {
            photo: file.name,
            group_id: currentGroup.id
        });

        console.log('URL:', `${API_URL}/photos`);
        console.log('Token:', authToken ? authToken.substring(0, 20) + '...' : 'MANQUANT');

        try {
            console.log('Envoi de la requête fetch...');
            const response = await fetch(`${API_URL}/photos`, {
                method: 'POST',
                headers: {
                    'Authorization': `Bearer ${authToken}`
                },
                body: formData
            });

            console.log('Réponse reçue:', response.status, response.statusText);

            if (response.ok) {
                successCount++;
                const data = await response.json();
                console.log(`✓ Uploaded: ${file.name}`, data);
            } else {
                errorCount++;
                const errorData = await response.json().catch(() => ({ message: 'Erreur serveur' }));
                const errorMsg = errorData.message || 'Erreur inconnue';
                console.error(`✗ Failed to upload ${file.name}:`, errorMsg, errorData);
                errorMessages.push(`${file.name}: ${errorMsg}`);
            }
        } catch (error) {
            errorCount++;
            console.error(`✗ Network error uploading ${file.name}:`, error);
            console.error('Error details:', {
                message: error.message,
                stack: error.stack
            });
            errorMessages.push(`${file.name}: Erreur réseau - ${error.message}`);
        }
    }

    uploadSubmitBtn.disabled = false;
    uploadSubmitBtn.textContent = 'Téléverser les photos';

    if (successCount > 0) {
        hideModal(uploadModal);
        await loadPhotos();
        showMessage(`${successCount} photo(s) téléversée(s) avec succès`);

        // Reset form
        selectedFiles = [];
        previewContainer.innerHTML = '';
        uploadSubmitBtn.style.display = 'none';
        photoInput.value = '';
    }

    if (errorCount > 0) {
        const errorDetail = errorMessages.length > 0 ? '\n\n' + errorMessages.slice(0, 3).join('\n') : '';
        showError(`${errorCount} photo(s) n'ont pas pu être téléversées${errorDetail}`);
    }
}

// Utility functions
function showModal(modal) {
    modal.classList.add('show');
}

function hideModal(modal) {
    modal.classList.remove('show');
}

function showLoading(show) {
    loadingState.style.display = show ? 'block' : 'none';
}

function formatDate(dateString) {
    const date = new Date(dateString);
    const now = new Date();
    const diff = now - date;
    const days = Math.floor(diff / (1000 * 60 * 60 * 24));

    if (days === 0) {
        const hours = Math.floor(diff / (1000 * 60 * 60));
        if (hours === 0) {
            const minutes = Math.floor(diff / (1000 * 60));
            return minutes <= 1 ? "À l'instant" : `Il y a ${minutes} minutes`;
        }
        return hours === 1 ? 'Il y a 1 heure' : `Il y a ${hours} heures`;
    } else if (days === 1) {
        return 'Hier';
    } else if (days < 30) {
        return `Il y a ${days} jours`;
    } else {
        return date.toLocaleDateString('fr-FR');
    }
}

function showMessage(message) {
    const div = document.createElement('div');
    div.textContent = message;
    div.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        background: var(--success-color);
        color: white;
        padding: 1rem 1.5rem;
        border-radius: 0.5rem;
        z-index: 2000;
        animation: slideIn 0.3s ease;
    `;
    document.body.appendChild(div);
    setTimeout(() => div.remove(), 3000);
}

function showError(message) {
    const div = document.createElement('div');
    div.textContent = message;
    div.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        background: var(--error-color);
        color: white;
        padding: 1rem 1.5rem;
        border-radius: 0.5rem;
        z-index: 2000;
        animation: slideIn 0.3s ease;
    `;
    document.body.appendChild(div);
    setTimeout(() => div.remove(), 5000);
}

// Start the app
document.addEventListener('DOMContentLoaded', init);