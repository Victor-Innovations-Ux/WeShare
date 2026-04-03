// Configuration
const API_URL = window.location.origin + '/api';

// State management
let authToken = localStorage.getItem('auth_token');
let userInfo = null;

// DOM Elements
const createGroupBtn = document.getElementById('createGroupBtn');
const getStartedBtn = document.getElementById('getStartedBtn');
const joinGroupBtn = document.getElementById('joinGroupBtn');

const createGroupModal = document.getElementById('createGroupModal');
const joinGroupModal = document.getElementById('joinGroupModal');
const successModal = document.getElementById('successModal');

const createGroupForm = document.getElementById('createGroupForm');
const joinGroupForm = document.getElementById('joinGroupForm');

// Modal functions
function showModal(modal) {
    modal.classList.add('show');
}

function hideModal(modal) {
    modal.classList.remove('show');
}

// Close modals when clicking outside
document.querySelectorAll('.modal').forEach(modal => {
    modal.addEventListener('click', (e) => {
        if (e.target === modal) {
            hideModal(modal);
        }
    });
});

// Close buttons
document.querySelectorAll('.modal-close').forEach(btn => {
    btn.addEventListener('click', () => {
        hideModal(btn.closest('.modal'));
    });
});

// Event Listeners
createGroupBtn.addEventListener('click', handleCreateGroup);
getStartedBtn.addEventListener('click', handleCreateGroup);
joinGroupBtn.addEventListener('click', () => showModal(joinGroupModal));

createGroupForm.addEventListener('submit', handleCreateGroupSubmit);
joinGroupForm.addEventListener('submit', handleJoinGroupSubmit);

// Check authentication status
async function checkAuth() {
    if (!authToken) return;

    try {
        const response = await fetch(`${API_URL}/auth/me`, {
            headers: {
                'Authorization': `Bearer ${authToken}`
            }
        });

        if (response.ok) {
            const data = await response.json();
            userInfo = data.data;

            // If user is authenticated and is a user type, they can create groups
            if (userInfo.type === 'user') {
                // User is logged in
                updateUIForLoggedInUser();
            } else if (userInfo.type === 'participant') {
                // Redirect to group page
                window.location.href = `/group.html?id=${userInfo.group.share_code}`;
            }
        } else {
            // Invalid token
            localStorage.removeItem('auth_token');
            authToken = null;
        }
    } catch (error) {
        console.error('Auth check error:', error);
    }
}

// Handle create group button click
function handleCreateGroup() {
    showModal(createGroupModal);
}

// Handle Google login
async function handleGoogleLogin() {
    try {
        // Get Google auth URL from backend
        const response = await fetch(`${API_URL}/auth/google/login`, {
            method: 'GET'
        });

        if (response.ok) {
            const data = await response.json();
            // Redirect to Google OAuth
            window.location.href = data.data.auth_url;
        } else {
            showError('Failed to initiate Google login');
        }
    } catch (error) {
        console.error('Google login error:', error);
        showError('Connection error. Please try again.');
    }
}

// Handle OAuth callback (if redirected back)
async function handleOAuthCallback() {
    const urlParams = new URLSearchParams(window.location.search);
    const code = urlParams.get('code');
    const state = urlParams.get('state');

    if (code && state) {
        try {
            const response = await fetch(`${API_URL}/auth/google/callback?code=${code}&state=${state}`, {
                method: 'GET'
            });

            if (response.ok) {
                const data = await response.json();

                // Store token
                authToken = data.data.token;
                localStorage.setItem('auth_token', authToken);
                userInfo = { type: 'user', user: data.data.user };

                // Clear URL parameters
                window.history.replaceState({}, document.title, "/");

                // Update UI
                updateUIForLoggedInUser();

                // Show create group modal
                handleCreateGroup();
            } else {
                showError('Authentication failed');
            }
        } catch (error) {
            console.error('OAuth callback error:', error);
            showError('Authentication error');
        }
    }
}

// Handle create group form submission
async function handleCreateGroupSubmit(e) {
    e.preventDefault();

    const formData = new FormData(e.target);
    const name = formData.get('name');
    const creatorName = formData.get('creator_name');

    try {
        // Create headers object
        const headers = {
            'Content-Type': 'application/json'
        };

        // Add auth token if available (for logged in users)
        if (authToken) {
            headers['Authorization'] = `Bearer ${authToken}`;
        }

        const response = await fetch(`${API_URL}/groups`, {
            method: 'POST',
            headers: headers,
            body: JSON.stringify({
                name,
                creator_name: creatorName
            })
        });

        if (response.ok) {
            const data = await response.json();

            // Store token if returned (for anonymous users)
            if (data.data.token) {
                authToken = data.data.token;
                localStorage.setItem('auth_token', authToken);
            }

            // Hide create modal
            hideModal(createGroupModal);

            // Show success modal with share code
            document.getElementById('shareCodeDisplay').textContent = data.data.share_code;
            showModal(successModal);

            // Setup copy button
            document.getElementById('copyCodeBtn').onclick = () => {
                navigator.clipboard.writeText(data.data.share_code);
                showMessage('Code copié !');
            };

            // Setup go to group button
            document.getElementById('goToGroupBtn').onclick = () => {
                window.location.href = `/group.html?id=${data.data.share_code}`;
            };
        } else {
            const error = await response.json();
            showError(error.message || 'Erreur lors de la création du groupe');
        }
    } catch (error) {
        console.error('Create group error:', error);
        showError('Erreur de connexion. Réessayez.');
    }
}

// Handle join group form submission
async function handleJoinGroupSubmit(e) {
    e.preventDefault();

    const formData = new FormData(e.target);
    const shareCode = formData.get('share_code').toUpperCase();
    const name = formData.get('name');

    try {
        const response = await fetch(`${API_URL}/auth/join`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                share_code: shareCode,
                name: name
            })
        });

        if (response.ok) {
            const data = await response.json();

            // Store token
            authToken = data.data.token;
            localStorage.setItem('auth_token', authToken);

            // Redirect to group page
            window.location.href = `/group.html?id=${shareCode}`;
        } else {
            const error = await response.json();
            showError(error.message || 'Failed to join group');
        }
    } catch (error) {
        console.error('Join group error:', error);
        showError('Connection error. Please try again.');
    }
}

// Update UI for logged in user
function updateUIForLoggedInUser() {
    if (userInfo && userInfo.type === 'user') {
        // Update create button to show user is logged in
        createGroupBtn.innerHTML = `
            <img src="${userInfo.user.picture_url}" alt="${userInfo.user.name}" style="width: 24px; height: 24px; border-radius: 50%;">
            Créer un groupe
        `;
    }
}

// Show error message
function showError(message) {
    const errorDiv = document.createElement('div');
    errorDiv.className = 'error-message';
    errorDiv.textContent = message;
    errorDiv.style.cssText = `
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

    document.body.appendChild(errorDiv);

    setTimeout(() => {
        errorDiv.remove();
    }, 5000);
}

// Show success message
function showMessage(message) {
    const messageDiv = document.createElement('div');
    messageDiv.className = 'success-message-toast';
    messageDiv.textContent = message;
    messageDiv.style.cssText = `
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

    document.body.appendChild(messageDiv);

    setTimeout(() => {
        messageDiv.remove();
    }, 3000);
}

// Initialize
document.addEventListener('DOMContentLoaded', () => {
    checkAuth();
    handleOAuthCallback();
});