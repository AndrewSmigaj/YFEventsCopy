/**
 * YFEvents Communication Hub JavaScript
 */

const CommunicationApp = {
    basePath: window.basePath || '',
    currentChannel: null,
    currentUserId: window.currentUserId,
    currentUserName: window.currentUserName,
    channels: [],
    messages: [],
    messageCache: new Map(),
    eventSource: null,
    
    /**
     * Initialize the application
     */
    async init() {
        this.bindEvents();
        await this.loadChannels();
        this.setupRealTimeUpdates();
        this.requestNotificationPermission();
    },
    
    /**
     * Bind UI events
     */
    bindEvents() {
        // Message form
        document.getElementById('message-form').addEventListener('submit', (e) => {
            e.preventDefault();
            this.sendMessage();
        });
        
        // Enter key to send (Shift+Enter for new line)
        document.getElementById('message-input').addEventListener('keydown', (e) => {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                this.sendMessage();
            }
        });
        
        // Create channel form
        document.getElementById('create-channel-form').addEventListener('submit', (e) => {
            e.preventDefault();
            this.createChannel();
        });
        
        // Search form
        document.getElementById('search-form').addEventListener('submit', (e) => {
            e.preventDefault();
            this.searchMessages();
        });
        
        // Preferences form
        document.getElementById('preferences-form').addEventListener('submit', (e) => {
            e.preventDefault();
            this.updatePreferences();
        });
    },
    
    /**
     * Load user's channels
     */
    async loadChannels() {
        try {
            const response = await fetch(`${this.basePath}/api/communication/channels`);
            const data = await response.json();
            
            if (data.success) {
                this.channels = data.data;
                this.renderChannels();
            } else {
                this.showError('Failed to load channels');
            }
        } catch (error) {
            console.error('Error loading channels:', error);
            this.showError('Network error loading channels');
        }
    },
    
    /**
     * Render channels in sidebar
     */
    renderChannels() {
        const publicChannels = document.getElementById('public-channels');
        const eventChannels = document.getElementById('event-channels');
        const vendorChannels = document.getElementById('vendor-channels');
        const announcementChannels = document.getElementById('announcement-channels');
        
        // Clear existing
        publicChannels.innerHTML = '';
        eventChannels.innerHTML = '';
        if (vendorChannels) vendorChannels.innerHTML = '';
        announcementChannels.innerHTML = '';
        
        // Group channels by type
        this.channels.forEach(channel => {
            const channelElement = this.createChannelElement(channel);
            
            switch (channel.type) {
                case 'public':
                    publicChannels.appendChild(channelElement);
                    break;
                case 'event':
                    eventChannels.appendChild(channelElement);
                    break;
                case 'vendor':
                    if (vendorChannels) vendorChannels.appendChild(channelElement);
                    break;
                case 'announcement':
                    announcementChannels.appendChild(channelElement);
                    break;
            }
        });
        
        // Show empty state if no channels
        if (this.channels.length === 0) {
            publicChannels.innerHTML = '<div class="text-muted small p-2">No channels yet</div>';
        }
    },
    
    /**
     * Create channel element
     */
    createChannelElement(channel) {
        const div = document.createElement('div');
        div.className = 'channel-item';
        div.dataset.channelId = channel.id;
        
        if (this.currentChannel && this.currentChannel.id === channel.id) {
            div.classList.add('active');
        }
        
        const icon = this.getChannelIcon(channel.type);
        
        // Special handling for Picks channel
        if (channel.slug === 'picks') {
            div.innerHTML = `
                <span class="channel-name">
                    <span class="channel-icon">${icon}</span>
                    ${this.escapeHtml(channel.name)}
                    <a href="${this.basePath}/communication/picks.php" class="btn btn-sm btn-outline-primary ms-2" title="View Map" onclick="event.stopPropagation();">
                        <i class="fas fa-map-marked-alt"></i>
                    </a>
                </span>
                ${channel.unread_count > 0 ? `<span class="unread-count">${channel.unread_count}</span>` : ''}
            `;
        } else {
            div.innerHTML = `
                <span class="channel-name">
                    <span class="channel-icon">${icon}</span>
                    ${this.escapeHtml(channel.name)}
                </span>
                ${channel.unread_count > 0 ? `<span class="unread-count">${channel.unread_count}</span>` : ''}
            `;
        }
        
        div.addEventListener('click', (e) => {
            // Don't select channel if clicking on the map link
            if (!e.target.closest('a')) {
                this.selectChannel(channel);
            }
        });
        
        return div;
    },
    
    /**
     * Get channel icon based on type
     */
    getChannelIcon(type) {
        const icons = {
            'public': '#',
            'private': '🔒',
            'event': '📅',
            'vendor': '🏪',
            'announcement': '📢'
        };
        return icons[type] || '#';
    },
    
    /**
     * Select a channel
     */
    async selectChannel(channel) {
        this.currentChannel = channel;
        
        // Update UI
        document.querySelectorAll('.channel-item').forEach(el => {
            el.classList.remove('active');
        });
        document.querySelector(`[data-channel-id="${channel.id}"]`).classList.add('active');
        
        // Update header
        document.getElementById('channel-name').textContent = `${this.getChannelIcon(channel.type)} ${channel.name}`;
        document.getElementById('channel-description').textContent = channel.description || '';
        
        // Show channel actions
        document.getElementById('btn-pinned').style.display = 'inline-block';
        document.getElementById('btn-search').style.display = 'inline-block';
        document.getElementById('btn-info').style.display = 'inline-block';
        
        // Show message input
        document.getElementById('message-input-area').style.display = 'block';
        
        // Load messages
        await this.loadMessages();
        
        // Mark as read
        this.markChannelAsRead();
    },
    
    /**
     * Load messages for current channel
     */
    async loadMessages() {
        if (!this.currentChannel) return;
        
        const container = document.getElementById('messages-container');
        container.innerHTML = '<div class="loading"><div class="loading-spinner"></div></div>';
        
        try {
            const response = await fetch(`${this.basePath}/api/communication/channels/${this.currentChannel.id}/messages`);
            const data = await response.json();
            
            if (data.success) {
                this.messages = data.data;
                this.renderMessages();
                
                // Update unread count
                if (data.unread_count === 0) {
                    const channelEl = document.querySelector(`[data-channel-id="${this.currentChannel.id}"] .unread-count`);
                    if (channelEl) channelEl.remove();
                }
            } else {
                this.showError('Failed to load messages');
            }
        } catch (error) {
            console.error('Error loading messages:', error);
            container.innerHTML = '<div class="error-message">Failed to load messages</div>';
        }
    },
    
    /**
     * Render messages
     */
    renderMessages() {
        const container = document.getElementById('messages-container');
        container.innerHTML = '';
        
        if (this.messages.length === 0) {
            container.innerHTML = `
                <div class="empty-state">
                    <i class="fas fa-comments fa-3x mb-3"></i>
                    <p>No messages yet. Start the conversation!</p>
                </div>
            `;
            return;
        }
        
        this.messages.forEach(message => {
            container.appendChild(this.createMessageElement(message));
        });
        
        // Scroll to bottom
        container.scrollTop = container.scrollHeight;
    },
    
    /**
     * Create message element
     */
    createMessageElement(message) {
        const div = document.createElement('div');
        div.className = 'message';
        div.dataset.messageId = message.id;
        
        if (message.content_type === 'system') {
            div.classList.add('system');
            div.innerHTML = `
                <div class="message-content">
                    ${this.formatMessageContent(message.content)}
                </div>
            `;
        } else {
            if (message.content_type === 'announcement') {
                div.classList.add('announcement');
            }
            if (message.is_pinned) {
                div.classList.add('pinned');
            }
            
            div.innerHTML = `
                <div class="message-avatar">
                    <img src="${message.user.avatar}" alt="${message.user.name}">
                </div>
                <div class="message-content">
                    <div class="message-header">
                        <span class="user-name">${this.escapeHtml(message.user.name)}</span>
                        <span class="timestamp">${this.formatTimestamp(message.created_at)}</span>
                        ${message.is_edited ? '<span class="text-muted small">(edited)</span>' : ''}
                    </div>
                    <div class="message-text">${this.formatMessageContent(message.content)}</div>
                    ${this.currentChannel && this.currentChannel.slug === 'picks' && message.location_latitude ? this.renderPickLocation(message) : ''}
                    ${message.yfclaim_item_id ? this.renderYFClaimReference(message.yfclaim_item_id) : ''}
                    ${message.attachments && message.attachments.length > 0 ? this.renderAttachments(message.attachments) : ''}
                    ${message.reply_count > 0 ? `<div class="mt-2"><small class="text-muted">${message.reply_count} replies</small></div>` : ''}
                </div>
                ${message.user.id === this.currentUserId ? this.renderMessageActions(message) : ''}
            `;
        }
        
        return div;
    },
    
    /**
     * Format message content
     */
    formatMessageContent(content) {
        // Escape HTML
        let formatted = this.escapeHtml(content);
        
        // Convert @mentions
        formatted = formatted.replace(/@(\w+)/g, '<span class="mention">@$1</span>');
        
        // Basic markdown support
        formatted = formatted.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');
        formatted = formatted.replace(/\*(.*?)\*/g, '<em>$1</em>');
        
        // Convert URLs to links
        formatted = formatted.replace(
            /(https?:\/\/[^\s]+)/g,
            '<a href="$1" target="_blank" rel="noopener">$1</a>'
        );
        
        return formatted;
    },
    
    /**
     * Render pick location info
     */
    renderPickLocation(message) {
        return `
            <div class="pick-info mt-2 p-2 bg-light rounded">
                ${message.location_name ? `<h6 class="mb-1">${this.escapeHtml(message.location_name)}</h6>` : ''}
                ${message.location_address ? `<p class="mb-1 small"><i class="fas fa-map-marker-alt"></i> ${this.escapeHtml(message.location_address)}</p>` : ''}
                ${message.event_date ? `<p class="mb-1 small"><i class="fas fa-calendar"></i> ${new Date(message.event_date).toLocaleDateString()}</p>` : ''}
                ${message.event_start_time ? `<p class="mb-1 small"><i class="fas fa-clock"></i> ${message.event_start_time}${message.event_end_time ? ` - ${message.event_end_time}` : ''}</p>` : ''}
                <a href="${this.basePath}/communication/picks.php" class="btn btn-sm btn-primary mt-2">
                    <i class="fas fa-map"></i> View on Map
                </a>
            </div>
        `;
    },
    
    /**
     * Render YFClaim item reference
     */
    renderYFClaimReference(itemId) {
        return `
            <div class="mt-2">
                <a href="/yfclaim/items/${itemId}" class="yfclaim-link" target="_blank">
                    <i class="fas fa-tag"></i> YFClaim Item #${itemId}
                </a>
            </div>
        `;
    },
    
    /**
     * Render message attachments
     */
    renderAttachments(attachments) {
        let html = '<div class="message-attachments">';
        
        attachments.forEach(attachment => {
            const icon = attachment.is_image ? 'fa-image' : 'fa-file';
            html += `
                <a href="${attachment.url}" class="attachment" target="_blank">
                    <i class="fas ${icon} attachment-icon"></i>
                    ${this.escapeHtml(attachment.original_filename)}
                </a>
            `;
        });
        
        html += '</div>';
        return html;
    },
    
    /**
     * Render message actions
     */
    renderMessageActions(message) {
        return `
            <div class="message-actions">
                <button onclick="CommunicationApp.editMessage(${message.id})" title="Edit">
                    <i class="fas fa-edit"></i>
                </button>
                <button onclick="CommunicationApp.deleteMessage(${message.id})" title="Delete">
                    <i class="fas fa-trash"></i>
                </button>
            </div>
        `;
    },
    
    /**
     * Send a message
     */
    async sendMessage() {
        const input = document.getElementById('message-input');
        const content = input.value.trim();
        
        if (!content || !this.currentChannel) return;
        
        // Disable input while sending
        input.disabled = true;
        
        try {
            const response = await fetch(`${this.basePath}/api/communication/channels/${this.currentChannel.id}/messages`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ content })
            });
            
            const data = await response.json();
            
            if (data.success) {
                input.value = '';
                // Message will appear via real-time update or reload
                await this.loadMessages();
            } else {
                this.showError(data.error || 'Failed to send message');
            }
        } catch (error) {
            console.error('Error sending message:', error);
            this.showError('Network error sending message');
        } finally {
            input.disabled = false;
            input.focus();
        }
    },
    
    /**
     * Edit a message
     */
    async editMessage(messageId) {
        const message = this.messages.find(m => m.id === messageId);
        if (!message) return;
        
        const newContent = prompt('Edit message:', message.content);
        if (newContent === null || newContent.trim() === message.content) return;
        
        try {
            const response = await fetch(`${this.basePath}/api/communication/messages/${messageId}`, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ content: newContent })
            });
            
            const data = await response.json();
            
            if (data.success) {
                await this.loadMessages();
            } else {
                this.showError(data.error || 'Failed to edit message');
            }
        } catch (error) {
            console.error('Error editing message:', error);
            this.showError('Network error editing message');
        }
    },
    
    /**
     * Delete a message
     */
    async deleteMessage(messageId) {
        if (!confirm('Are you sure you want to delete this message?')) return;
        
        try {
            const response = await fetch(`${this.basePath}/api/communication/messages/${messageId}`, {
                method: 'DELETE'
            });
            
            const data = await response.json();
            
            if (data.success) {
                await this.loadMessages();
            } else {
                this.showError(data.error || 'Failed to delete message');
            }
        } catch (error) {
            console.error('Error deleting message:', error);
            this.showError('Network error deleting message');
        }
    },
    
    /**
     * Create a new channel
     */
    async createChannel() {
        const nameInput = document.getElementById('channel-name-input');
        const descriptionInput = document.getElementById('channel-description-input');
        const typeSelect = document.getElementById('channel-type-select');
        
        const data = {
            name: nameInput.value.trim(),
            description: descriptionInput.value.trim(),
            type: typeSelect.value
        };
        
        if (!data.name) return;
        
        try {
            const response = await fetch(`${this.basePath}/api/communication/channels`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(data)
            });
            
            const result = await response.json();
            
            if (result.success) {
                // Close modal
                bootstrap.Modal.getInstance(document.getElementById('createChannelModal')).hide();
                
                // Reset form
                nameInput.value = '';
                descriptionInput.value = '';
                typeSelect.value = 'public';
                
                // Reload channels
                await this.loadChannels();
                
                // Select the new channel
                const newChannel = this.channels.find(c => c.id === result.data.id);
                if (newChannel) {
                    this.selectChannel(newChannel);
                }
            } else {
                this.showError(result.error || 'Failed to create channel');
            }
        } catch (error) {
            console.error('Error creating channel:', error);
            this.showError('Network error creating channel');
        }
    },
    
    /**
     * Search messages
     */
    async searchMessages() {
        if (!this.currentChannel) return;
        
        const searchInput = document.getElementById('search-input');
        const query = searchInput.value.trim();
        
        if (query.length < 3) {
            this.showError('Search query must be at least 3 characters');
            return;
        }
        
        const resultsContainer = document.getElementById('search-results');
        resultsContainer.innerHTML = '<div class="loading"><div class="loading-spinner"></div></div>';
        
        try {
            const response = await fetch(`${this.basePath}/api/communication/channels/${this.currentChannel.id}/messages/search?q=${encodeURIComponent(query)}`);
            const data = await response.json();
            
            if (data.success) {
                this.renderSearchResults(data.data, query);
            } else {
                resultsContainer.innerHTML = '<div class="error-message">Search failed</div>';
            }
        } catch (error) {
            console.error('Error searching messages:', error);
            resultsContainer.innerHTML = '<div class="error-message">Network error</div>';
        }
    },
    
    /**
     * Render search results
     */
    renderSearchResults(results, query) {
        const container = document.getElementById('search-results');
        
        if (results.length === 0) {
            container.innerHTML = '<div class="text-muted text-center p-3">No results found</div>';
            return;
        }
        
        container.innerHTML = '';
        
        results.forEach(message => {
            const div = document.createElement('div');
            div.className = 'search-result';
            
            // Highlight search term
            const highlightedContent = message.content.replace(
                new RegExp(query, 'gi'),
                '<span class="search-highlight">$&</span>'
            );
            
            div.innerHTML = `
                <div class="d-flex justify-content-between mb-1">
                    <strong>${this.escapeHtml(message.user.name)}</strong>
                    <small class="text-muted">${this.formatTimestamp(message.created_at)}</small>
                </div>
                <div>${highlightedContent}</div>
            `;
            
            div.addEventListener('click', () => {
                // Close modal and scroll to message
                bootstrap.Modal.getInstance(document.getElementById('searchModal')).hide();
                this.scrollToMessage(message.id);
            });
            
            container.appendChild(div);
        });
    },
    
    /**
     * Scroll to a specific message
     */
    scrollToMessage(messageId) {
        const messageEl = document.querySelector(`[data-message-id="${messageId}"]`);
        if (messageEl) {
            messageEl.scrollIntoView({ behavior: 'smooth', block: 'center' });
            messageEl.classList.add('highlight');
            setTimeout(() => messageEl.classList.remove('highlight'), 2000);
        }
    },
    
    /**
     * Mark channel as read
     */
    async markChannelAsRead() {
        if (!this.currentChannel) return;
        
        try {
            await fetch(`${this.basePath}/api/communication/channels/${this.currentChannel.id}/read`, {
                method: 'POST'
            });
        } catch (error) {
            console.error('Error marking channel as read:', error);
        }
    },
    
    /**
     * Update notification preferences
     */
    async updatePreferences() {
        const notificationPref = document.getElementById('notification-preference').value;
        const emailDigest = document.getElementById('email-digest-frequency').value;
        
        try {
            const response = await fetch(`${this.basePath}/api/communication/notifications/preferences`, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    notification_preference: notificationPref,
                    email_digest_frequency: emailDigest
                })
            });
            
            const data = await response.json();
            
            if (data.success) {
                bootstrap.Modal.getInstance(document.getElementById('preferencesModal')).hide();
                this.showSuccess('Preferences updated successfully');
            } else {
                this.showError(data.error || 'Failed to update preferences');
            }
        } catch (error) {
            console.error('Error updating preferences:', error);
            this.showError('Network error updating preferences');
        }
    },
    
    /**
     * Set up real-time updates (placeholder - would use SSE or WebSockets)
     */
    setupRealTimeUpdates() {
        // This would connect to server-sent events or WebSocket
        // For now, just poll for updates
        setInterval(() => {
            if (this.currentChannel) {
                this.checkForNewMessages();
            }
        }, 5000); // Poll every 5 seconds
    },
    
    /**
     * Check for new messages
     */
    async checkForNewMessages() {
        if (!this.currentChannel || this.messages.length === 0) return;
        
        const lastMessageId = this.messages[this.messages.length - 1].id;
        
        try {
            const response = await fetch(`${this.basePath}/api/communication/channels/${this.currentChannel.id}/messages?after=${lastMessageId}`);
            const data = await response.json();
            
            if (data.success && data.data.length > 0) {
                // Add new messages
                data.data.forEach(message => {
                    this.messages.push(message);
                    const container = document.getElementById('messages-container');
                    container.appendChild(this.createMessageElement(message));
                });
                
                // Scroll to bottom
                const container = document.getElementById('messages-container');
                container.scrollTop = container.scrollHeight;
                
                // Show notification
                if (document.hidden) {
                    this.showNotification(data.data[0]);
                }
            }
        } catch (error) {
            console.error('Error checking for new messages:', error);
        }
    },
    
    /**
     * Request notification permission
     */
    requestNotificationPermission() {
        if ('Notification' in window && Notification.permission === 'default') {
            Notification.requestPermission();
        }
    },
    
    /**
     * Show browser notification
     */
    showNotification(message) {
        if ('Notification' in window && Notification.permission === 'granted') {
            const notification = new Notification(`${message.user.name} in ${this.currentChannel.name}`, {
                body: message.content.substring(0, 100),
                icon: message.user.avatar
            });
            
            notification.onclick = () => {
                window.focus();
                notification.close();
            };
        }
    },
    
    /**
     * Format timestamp
     */
    formatTimestamp(timestamp) {
        const date = new Date(timestamp);
        const now = new Date();
        
        // Today
        if (date.toDateString() === now.toDateString()) {
            return date.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit' });
        }
        
        // Yesterday
        const yesterday = new Date(now);
        yesterday.setDate(yesterday.getDate() - 1);
        if (date.toDateString() === yesterday.toDateString()) {
            return 'Yesterday ' + date.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit' });
        }
        
        // Within a week
        const oneWeekAgo = new Date(now);
        oneWeekAgo.setDate(oneWeekAgo.getDate() - 7);
        if (date > oneWeekAgo) {
            return date.toLocaleDateString('en-US', { weekday: 'short' }) + ' ' + 
                   date.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit' });
        }
        
        // Older
        return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
    },
    
    /**
     * Escape HTML
     */
    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    },
    
    /**
     * Show error message
     */
    showError(message) {
        // You could implement a toast notification here
        console.error(message);
        alert(message);
    },
    
    /**
     * Show success message
     */
    showSuccess(message) {
        // You could implement a toast notification here
        console.log(message);
        alert(message);
    }
};

// UI Helper functions called from HTML
function showCreateChannelModal() {
    new bootstrap.Modal(document.getElementById('createChannelModal')).show();
}

function showSearchModal() {
    new bootstrap.Modal(document.getElementById('searchModal')).show();
}

function showPreferencesModal() {
    new bootstrap.Modal(document.getElementById('preferencesModal')).show();
}

function showPinnedMessages() {
    // This would filter to show only pinned messages
    alert('Pinned messages feature coming soon');
}

function showChannelInfo() {
    // This would show channel details and participants
    alert('Channel info feature coming soon');
}

function showAttachmentModal() {
    // This would show file upload interface
    alert('File attachment feature coming soon');
}

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    CommunicationApp.init();
});