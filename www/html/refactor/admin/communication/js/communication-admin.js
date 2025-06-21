// Communication Admin JavaScript

const API_BASE = '/api/communication/admin';

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    loadStatistics();
    loadRecentActivity();
    
    // Initialize tabs
    const triggerTabList = document.querySelectorAll('button[data-bs-toggle="pill"]');
    triggerTabList.forEach(triggerEl => {
        triggerEl.addEventListener('shown.bs.tab', function (event) {
            const target = event.target.getAttribute('data-bs-target');
            handleTabChange(target);
        });
    });
    
    // Initialize forms
    document.getElementById('user-form').addEventListener('submit', handleUserSubmit);
    document.getElementById('channel-form').addEventListener('submit', handleChannelSubmit);
    document.getElementById('settings-form').addEventListener('submit', handleSettingsSubmit);
    
    // Initialize search and filters
    document.getElementById('user-search').addEventListener('input', debounce(loadUsers, 300));
    document.getElementById('user-role-filter').addEventListener('change', loadUsers);
    document.getElementById('user-status-filter').addEventListener('change', loadUsers);
});

// Handle tab changes
function handleTabChange(target) {
    switch(target) {
        case '#users':
            loadUsers();
            break;
        case '#channels':
            loadChannels();
            break;
        case '#messages':
            loadMessageStats();
            loadRecentMessages();
            break;
        case '#moderation':
            loadFlaggedContent();
            loadBannedWords();
            break;
        case '#settings':
            loadSettings();
            break;
    }
}

// Load statistics
async function loadStatistics() {
    try {
        const response = await fetch(`${API_BASE}/statistics`);
        const stats = await response.json();
        
        document.getElementById('stat-users').textContent = stats.totalUsers || 0;
        document.getElementById('stat-channels').textContent = stats.activeChannels || 0;
        document.getElementById('stat-messages').textContent = stats.messagesToday || 0;
        document.getElementById('stat-active').textContent = stats.activeNow || 0;
    } catch (error) {
        console.error('Failed to load statistics:', error);
    }
}

// Load recent activity
async function loadRecentActivity() {
    try {
        const response = await fetch(`${API_BASE}/activity/recent`);
        const activities = await response.json();
        
        const container = document.getElementById('recent-activity');
        if (activities.length === 0) {
            container.innerHTML = '<p class="text-muted text-center">No recent activity</p>';
            return;
        }
        
        let html = '<div class="list-group">';
        activities.forEach(activity => {
            html += `
                <div class="list-group-item">
                    <div class="d-flex w-100 justify-content-between">
                        <h6 class="mb-1">${escapeHtml(activity.description)}</h6>
                        <small>${formatTime(activity.created_at)}</small>
                    </div>
                    <p class="mb-1">${escapeHtml(activity.details || '')}</p>
                    <small class="text-muted">${activity.user_name || 'System'}</small>
                </div>
            `;
        });
        html += '</div>';
        container.innerHTML = html;
    } catch (error) {
        console.error('Failed to load activity:', error);
        document.getElementById('recent-activity').innerHTML = 
            '<p class="text-danger text-center">Failed to load activity</p>';
    }
}

// Load users
async function loadUsers() {
    const search = document.getElementById('user-search').value;
    const role = document.getElementById('user-role-filter').value;
    const status = document.getElementById('user-status-filter').value;
    
    const params = new URLSearchParams();
    if (search) params.append('search', search);
    if (role) params.append('role', role);
    if (status) params.append('status', status);
    
    try {
        const response = await fetch(`${API_BASE}/users?${params}`);
        const users = await response.json();
        
        const tbody = document.querySelector('#users-table tbody');
        if (users.length === 0) {
            tbody.innerHTML = '<tr><td colspan="8" class="text-center">No users found</td></tr>';
            return;
        }
        
        let html = '';
        users.forEach(user => {
            const statusBadge = user.status === 'active' 
                ? '<span class="badge bg-success">Active</span>'
                : '<span class="badge bg-danger">Suspended</span>';
                
            html += `
                <tr>
                    <td>${user.id}</td>
                    <td>${escapeHtml(user.username)}</td>
                    <td>${escapeHtml(user.email)}</td>
                    <td><span class="badge bg-primary">${user.role}</span></td>
                    <td>${formatDate(user.created_at)}</td>
                    <td>${formatTime(user.last_active_at)}</td>
                    <td>${statusBadge}</td>
                    <td>
                        <div class="table-actions">
                            <button class="btn btn-sm btn-outline-primary" 
                                    onclick="editUser(${user.id})">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button class="btn btn-sm btn-outline-warning" 
                                    onclick="toggleUserStatus(${user.id}, '${user.status}')">
                                <i class="fas fa-${user.status === 'active' ? 'ban' : 'check'}"></i>
                            </button>
                            <button class="btn btn-sm btn-outline-danger" 
                                    onclick="deleteUser(${user.id})">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </td>
                </tr>
            `;
        });
        tbody.innerHTML = html;
    } catch (error) {
        console.error('Failed to load users:', error);
    }
}

// Load channels
async function loadChannels() {
    try {
        const response = await fetch(`${API_BASE}/channels`);
        const channels = await response.json();
        
        const tbody = document.querySelector('#channels-table tbody');
        if (channels.length === 0) {
            tbody.innerHTML = '<tr><td colspan="7" class="text-center">No channels found</td></tr>';
            return;
        }
        
        let html = '';
        channels.forEach(channel => {
            const typeBadge = getChannelTypeBadge(channel.type);
            
            html += `
                <tr>
                    <td>
                        <i class="fas fa-${channel.type === 'private' ? 'lock' : 'hashtag'}"></i>
                        ${escapeHtml(channel.name)}
                    </td>
                    <td>${typeBadge}</td>
                    <td>${channel.participant_count || 0}</td>
                    <td>${channel.message_count || 0}</td>
                    <td>${formatDate(channel.created_at)}</td>
                    <td>${formatTime(channel.last_activity_at)}</td>
                    <td>
                        <div class="table-actions">
                            <button class="btn btn-sm btn-outline-primary" 
                                    onclick="editChannel(${channel.id})">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button class="btn btn-sm btn-outline-info" 
                                    onclick="viewChannelDetails(${channel.id})">
                                <i class="fas fa-eye"></i>
                            </button>
                            <button class="btn btn-sm btn-outline-danger" 
                                    onclick="deleteChannel(${channel.id})">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </td>
                </tr>
            `;
        });
        tbody.innerHTML = html;
    } catch (error) {
        console.error('Failed to load channels:', error);
    }
}

// Load message statistics
async function loadMessageStats() {
    try {
        const response = await fetch(`${API_BASE}/messages/statistics`);
        const stats = await response.json();
        
        // Update chart
        const ctx = document.getElementById('message-chart').getContext('2d');
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: stats.labels || [],
                datasets: [{
                    label: 'Messages per Day',
                    data: stats.data || [],
                    borderColor: 'rgb(13, 110, 253)',
                    tension: 0.1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                }
            }
        });
        
        // Update top channels
        const topChannels = stats.topChannels || [];
        let html = '<div class="list-group">';
        topChannels.forEach(channel => {
            html += `
                <div class="list-group-item d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="mb-0">${escapeHtml(channel.name)}</h6>
                        <small class="text-muted">${channel.type}</small>
                    </div>
                    <span class="badge bg-primary rounded-pill">${channel.message_count}</span>
                </div>
            `;
        });
        html += '</div>';
        document.getElementById('top-channels-list').innerHTML = html;
    } catch (error) {
        console.error('Failed to load message stats:', error);
    }
}

// Load recent messages
async function loadRecentMessages() {
    try {
        const response = await fetch(`${API_BASE}/messages/recent`);
        const messages = await response.json();
        
        const container = document.getElementById('recent-messages');
        if (messages.length === 0) {
            container.innerHTML = '<p class="text-muted text-center">No recent messages</p>';
            return;
        }
        
        let html = '<div class="list-group">';
        messages.forEach(message => {
            html += `
                <div class="list-group-item">
                    <div class="d-flex w-100 justify-content-between">
                        <h6 class="mb-1">
                            ${escapeHtml(message.user_name)} in #${escapeHtml(message.channel_name)}
                        </h6>
                        <small>${formatTime(message.created_at)}</small>
                    </div>
                    <p class="mb-1">${escapeHtml(message.content)}</p>
                    <div class="mt-2">
                        <button class="btn btn-sm btn-outline-danger" 
                                onclick="deleteMessage(${message.id})">
                            <i class="fas fa-trash"></i> Delete
                        </button>
                    </div>
                </div>
            `;
        });
        html += '</div>';
        container.innerHTML = html;
    } catch (error) {
        console.error('Failed to load recent messages:', error);
    }
}

// User management functions
function showAddUserModal() {
    document.getElementById('userModalTitle').textContent = 'Add User';
    document.getElementById('user-form').reset();
    document.getElementById('user-id').value = '';
    document.getElementById('user-password').required = true;
    const modal = new bootstrap.Modal(document.getElementById('userModal'));
    modal.show();
}

async function editUser(userId) {
    try {
        const response = await fetch(`${API_BASE}/users/${userId}`);
        const user = await response.json();
        
        document.getElementById('userModalTitle').textContent = 'Edit User';
        document.getElementById('user-id').value = user.id;
        document.getElementById('user-username').value = user.username;
        document.getElementById('user-email').value = user.email;
        document.getElementById('user-firstname').value = user.first_name;
        document.getElementById('user-lastname').value = user.last_name;
        document.getElementById('user-role').value = user.role;
        document.getElementById('user-password').required = false;
        
        const modal = new bootstrap.Modal(document.getElementById('userModal'));
        modal.show();
    } catch (error) {
        console.error('Failed to load user:', error);
        showAlert('Failed to load user details', 'danger');
    }
}

async function handleUserSubmit(event) {
    event.preventDefault();
    
    const userId = document.getElementById('user-id').value;
    const data = {
        username: document.getElementById('user-username').value,
        email: document.getElementById('user-email').value,
        first_name: document.getElementById('user-firstname').value,
        last_name: document.getElementById('user-lastname').value,
        role: document.getElementById('user-role').value
    };
    
    const password = document.getElementById('user-password').value;
    if (password) {
        data.password = password;
    }
    
    try {
        const url = userId ? `${API_BASE}/users/${userId}` : `${API_BASE}/users`;
        const method = userId ? 'PUT' : 'POST';
        
        const response = await fetch(url, {
            method: method,
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(data)
        });
        
        if (response.ok) {
            bootstrap.Modal.getInstance(document.getElementById('userModal')).hide();
            loadUsers();
            showAlert(userId ? 'User updated successfully' : 'User created successfully', 'success');
        } else {
            const error = await response.json();
            showAlert(error.message || 'Failed to save user', 'danger');
        }
    } catch (error) {
        console.error('Failed to save user:', error);
        showAlert('Failed to save user', 'danger');
    }
}

async function toggleUserStatus(userId, currentStatus) {
    const newStatus = currentStatus === 'active' ? 'suspended' : 'active';
    const action = currentStatus === 'active' ? 'suspend' : 'activate';
    
    if (!confirm(`Are you sure you want to ${action} this user?`)) {
        return;
    }
    
    try {
        const response = await fetch(`${API_BASE}/users/${userId}/status`, {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ status: newStatus })
        });
        
        if (response.ok) {
            loadUsers();
            showAlert(`User ${action}d successfully`, 'success');
        } else {
            showAlert(`Failed to ${action} user`, 'danger');
        }
    } catch (error) {
        console.error('Failed to update user status:', error);
        showAlert('Failed to update user status', 'danger');
    }
}

async function deleteUser(userId) {
    if (!confirm('Are you sure you want to delete this user? This action cannot be undone.')) {
        return;
    }
    
    try {
        const response = await fetch(`${API_BASE}/users/${userId}`, {
            method: 'DELETE'
        });
        
        if (response.ok) {
            loadUsers();
            showAlert('User deleted successfully', 'success');
        } else {
            showAlert('Failed to delete user', 'danger');
        }
    } catch (error) {
        console.error('Failed to delete user:', error);
        showAlert('Failed to delete user', 'danger');
    }
}

// Channel management functions
function showCreateChannelModal() {
    document.getElementById('channel-form').reset();
    const modal = new bootstrap.Modal(document.getElementById('channelModal'));
    modal.show();
}

async function handleChannelSubmit(event) {
    event.preventDefault();
    
    const data = {
        name: document.getElementById('channel-name').value,
        description: document.getElementById('channel-description').value,
        type: document.getElementById('channel-type').value
    };
    
    try {
        const response = await fetch(`${API_BASE}/channels`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(data)
        });
        
        if (response.ok) {
            bootstrap.Modal.getInstance(document.getElementById('channelModal')).hide();
            loadChannels();
            showAlert('Channel created successfully', 'success');
        } else {
            const error = await response.json();
            showAlert(error.message || 'Failed to create channel', 'danger');
        }
    } catch (error) {
        console.error('Failed to create channel:', error);
        showAlert('Failed to create channel', 'danger');
    }
}

async function deleteChannel(channelId) {
    if (!confirm('Are you sure you want to delete this channel? All messages will be lost.')) {
        return;
    }
    
    try {
        const response = await fetch(`${API_BASE}/channels/${channelId}`, {
            method: 'DELETE'
        });
        
        if (response.ok) {
            loadChannels();
            showAlert('Channel deleted successfully', 'success');
        } else {
            showAlert('Failed to delete channel', 'danger');
        }
    } catch (error) {
        console.error('Failed to delete channel:', error);
        showAlert('Failed to delete channel', 'danger');
    }
}

// Message management
async function deleteMessage(messageId) {
    if (!confirm('Are you sure you want to delete this message?')) {
        return;
    }
    
    try {
        const response = await fetch(`${API_BASE}/messages/${messageId}`, {
            method: 'DELETE'
        });
        
        if (response.ok) {
            loadRecentMessages();
            showAlert('Message deleted successfully', 'success');
        } else {
            showAlert('Failed to delete message', 'danger');
        }
    } catch (error) {
        console.error('Failed to delete message:', error);
        showAlert('Failed to delete message', 'danger');
    }
}

// Settings management
async function loadSettings() {
    try {
        const response = await fetch(`${API_BASE}/settings`);
        const settings = await response.json();
        
        document.getElementById('allow-registration').checked = settings.allow_registration;
        document.getElementById('default-role').value = settings.default_role;
        document.getElementById('message-retention').value = settings.message_retention;
        document.getElementById('max-file-size').value = settings.max_file_size;
        document.getElementById('enable-email-notifications').checked = settings.enable_email_notifications;
        document.getElementById('enable-push-notifications').checked = settings.enable_push_notifications;
    } catch (error) {
        console.error('Failed to load settings:', error);
    }
}

async function handleSettingsSubmit(event) {
    event.preventDefault();
    
    const data = {
        allow_registration: document.getElementById('allow-registration').checked,
        default_role: document.getElementById('default-role').value,
        message_retention: parseInt(document.getElementById('message-retention').value),
        max_file_size: parseInt(document.getElementById('max-file-size').value),
        enable_email_notifications: document.getElementById('enable-email-notifications').checked,
        enable_push_notifications: document.getElementById('enable-push-notifications').checked
    };
    
    try {
        const response = await fetch(`${API_BASE}/settings`, {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(data)
        });
        
        if (response.ok) {
            showAlert('Settings saved successfully', 'success');
        } else {
            showAlert('Failed to save settings', 'danger');
        }
    } catch (error) {
        console.error('Failed to save settings:', error);
        showAlert('Failed to save settings', 'danger');
    }
}

// Utility functions
function getChannelTypeBadge(type) {
    switch(type) {
        case 'public':
            return '<span class="badge bg-success">Public</span>';
        case 'private':
            return '<span class="badge bg-warning">Private</span>';
        case 'announcement':
            return '<span class="badge bg-info">Announcement</span>';
        default:
            return '<span class="badge bg-secondary">' + type + '</span>';
    }
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString();
}

function formatTime(dateString) {
    if (!dateString) return 'Never';
    const date = new Date(dateString);
    const now = new Date();
    const diff = now - date;
    
    if (diff < 60000) {
        return 'Just now';
    } else if (diff < 3600000) {
        return Math.floor(diff / 60000) + ' min ago';
    } else if (diff < 86400000) {
        return Math.floor(diff / 3600000) + ' hours ago';
    } else {
        return date.toLocaleDateString();
    }
}

function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

function showAlert(message, type = 'info') {
    const alertHtml = `
        <div class="alert alert-${type} alert-dismissible fade show position-fixed top-0 start-50 translate-middle-x mt-3" 
             style="z-index: 9999;">
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    `;
    document.body.insertAdjacentHTML('beforeend', alertHtml);
    
    setTimeout(() => {
        const alert = document.querySelector('.alert');
        if (alert) {
            alert.remove();
        }
    }, 5000);
}