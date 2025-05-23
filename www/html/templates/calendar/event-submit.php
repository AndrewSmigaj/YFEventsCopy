<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Submit Event - Yakima Events Calendar</title>
    <link rel="stylesheet" href="/css/calendar.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://maps.googleapis.com/maps/api/js?key=<?= htmlspecialchars($googleMapsApiKey) ?>&libraries=places" async defer></script>
    <style>
        .form-container {
            max-width: 800px;
            margin: 2rem auto;
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            overflow: hidden;
        }
        
        .form-header {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            padding: 2rem;
            text-align: center;
        }
        
        .form-header h1 {
            margin: 0;
            font-size: 1.8rem;
        }
        
        .form-header p {
            margin: 0.5rem 0 0 0;
            opacity: 0.9;
        }
        
        .form-content {
            padding: 2rem;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: var(--primary-color);
        }
        
        .form-group .required {
            color: var(--accent-color);
        }
        
        .form-control {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid var(--medium-gray);
            border-radius: var(--border-radius);
            font-size: 1rem;
            transition: var(--transition);
        }
        
        .form-control:focus {
            outline: none;
            border-color: var(--secondary-color);
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
        }
        
        .form-control.error {
            border-color: var(--accent-color);
        }
        
        textarea.form-control {
            min-height: 120px;
            resize: vertical;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }
        
        .form-help {
            font-size: 0.9rem;
            color: var(--dark-gray);
            margin-top: 0.25rem;
        }
        
        .address-lookup {
            position: relative;
        }
        
        .address-suggestions {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: white;
            border: 1px solid var(--medium-gray);
            border-top: none;
            border-radius: 0 0 var(--border-radius) var(--border-radius);
            max-height: 200px;
            overflow-y: auto;
            z-index: 100;
            display: none;
        }
        
        .address-suggestion {
            padding: 0.75rem;
            cursor: pointer;
            border-bottom: 1px solid var(--light-gray);
        }
        
        .address-suggestion:hover {
            background: var(--light-gray);
        }
        
        .contact-info {
            background: #f8f9fa;
            padding: 1rem;
            border-radius: var(--border-radius);
            margin-bottom: 1rem;
        }
        
        .contact-info h3 {
            margin: 0 0 1rem 0;
            color: var(--primary-color);
        }
        
        .form-actions {
            display: flex;
            gap: 1rem;
            justify-content: flex-end;
            margin-top: 2rem;
            padding-top: 2rem;
            border-top: 1px solid var(--light-gray);
        }
        
        .preview-section {
            background: #f8f9fa;
            padding: 1.5rem;
            border-radius: var(--border-radius);
            margin-top: 2rem;
            border: 1px solid var(--light-gray);
        }
        
        .preview-section h3 {
            margin: 0 0 1rem 0;
            color: var(--primary-color);
        }
        
        .preview-event {
            background: white;
            padding: 1rem;
            border-radius: var(--border-radius);
            border: 1px solid var(--medium-gray);
        }
        
        .alert {
            padding: 1rem;
            border-radius: var(--border-radius);
            margin-bottom: 1rem;
        }
        
        .alert-info {
            background: rgba(52, 152, 219, 0.1);
            border: 1px solid rgba(52, 152, 219, 0.3);
            color: #2980b9;
        }
        
        .alert-success {
            background: rgba(39, 174, 96, 0.1);
            border: 1px solid rgba(39, 174, 96, 0.3);
            color: #27ae60;
        }
        
        .alert-error {
            background: rgba(231, 76, 60, 0.1);
            border: 1px solid rgba(231, 76, 60, 0.3);
            color: #e74c3c;
        }
        
        @media (max-width: 768px) {
            .form-container {
                margin: 1rem;
            }
            
            .form-content {
                padding: 1rem;
            }
            
            .form-row {
                grid-template-columns: 1fr;
            }
            
            .form-actions {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <div class="calendar-container">
        <!-- Header -->
        <header class="calendar-header">
            <div class="header-content">
                <h1><i class="fas fa-calendar-plus"></i> Submit Event</h1>
                <div class="header-actions">
                    <a href="/events" class="btn btn-outline">
                        <i class="fas fa-arrow-left"></i> Back to Calendar
                    </a>
                </div>
            </div>
        </header>
        
        <main class="calendar-main">
            <div class="form-container">
                <div class="form-header">
                    <h1>Submit Your Event</h1>
                    <p>Share your event with the Yakima community</p>
                </div>
                
                <div class="form-content">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i>
                        <strong>Please note:</strong> All submitted events are reviewed before appearing on the calendar. 
                        This process typically takes 24-48 hours.
                    </div>
                    
                    <form id="event-submit-form">
                        <!-- Basic Information -->
                        <div class="form-group">
                            <label for="event-title">
                                Event Title <span class="required">*</span>
                            </label>
                            <input 
                                type="text" 
                                id="event-title" 
                                name="title" 
                                class="form-control" 
                                required
                                placeholder="Enter your event title"
                                maxlength="255"
                            >
                            <div class="form-help">A clear, descriptive title for your event</div>
                        </div>
                        
                        <div class="form-group">
                            <label for="event-description">Event Description</label>
                            <textarea 
                                id="event-description" 
                                name="description" 
                                class="form-control"
                                placeholder="Describe your event, what attendees can expect, any special requirements, etc."
                                maxlength="2000"
                            ></textarea>
                            <div class="form-help">Optional but recommended. Helps people understand what your event is about.</div>
                        </div>
                        
                        <!-- Date and Time -->
                        <div class="form-row">
                            <div class="form-group">
                                <label for="start-date">
                                    Start Date <span class="required">*</span>
                                </label>
                                <input 
                                    type="date" 
                                    id="start-date" 
                                    name="start_date" 
                                    class="form-control" 
                                    required
                                >
                            </div>
                            
                            <div class="form-group">
                                <label for="start-time">
                                    Start Time <span class="required">*</span>
                                </label>
                                <input 
                                    type="time" 
                                    id="start-time" 
                                    name="start_time" 
                                    class="form-control" 
                                    required
                                >
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="end-date">End Date</label>
                                <input 
                                    type="date" 
                                    id="end-date" 
                                    name="end_date" 
                                    class="form-control"
                                >
                                <div class="form-help">Leave blank for single-day events</div>
                            </div>
                            
                            <div class="form-group">
                                <label for="end-time">End Time</label>
                                <input 
                                    type="time" 
                                    id="end-time" 
                                    name="end_time" 
                                    class="form-control"
                                >
                                <div class="form-help">Optional but helpful for planning</div>
                            </div>
                        </div>
                        
                        <!-- Location -->
                        <div class="form-group">
                            <label for="location-name">Venue/Location Name</label>
                            <input 
                                type="text" 
                                id="location-name" 
                                name="location" 
                                class="form-control"
                                placeholder="e.g., Yakima Valley Museum, Central Park, etc."
                                maxlength="255"
                            >
                            <div class="form-help">The name of the venue or general location</div>
                        </div>
                        
                        <div class="form-group">
                            <label for="event-address">Full Address</label>
                            <div class="address-lookup">
                                <input 
                                    type="text" 
                                    id="event-address" 
                                    name="address" 
                                    class="form-control"
                                    placeholder="Street address, city, state, zip code"
                                    autocomplete="off"
                                >
                                <div id="address-suggestions" class="address-suggestions"></div>
                            </div>
                            <div class="form-help">This helps people find your event and enables map features</div>
                        </div>
                        
                        <!-- Contact Information -->
                        <div class="contact-info">
                            <h3><i class="fas fa-address-card"></i> Contact Information (Optional)</h3>
                            <p class="form-help">Provide contact details so people can reach out with questions about your event.</p>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="contact-email">Contact Email</label>
                                    <input 
                                        type="email" 
                                        id="contact-email" 
                                        name="contact_email" 
                                        class="form-control"
                                        placeholder="your.email@example.com"
                                    >
                                </div>
                                
                                <div class="form-group">
                                    <label for="contact-phone">Contact Phone</label>
                                    <input 
                                        type="tel" 
                                        id="contact-phone" 
                                        name="contact_phone" 
                                        class="form-control"
                                        placeholder="(509) 555-0123"
                                    >
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="website-url">Website or Social Media</label>
                                <input 
                                    type="url" 
                                    id="website-url" 
                                    name="external_url" 
                                    class="form-control"
                                    placeholder="https://www.example.com or https://facebook.com/events/..."
                                >
                                <div class="form-help">Link to your event page, Facebook event, or website</div>
                            </div>
                        </div>
                        
                        <!-- Additional Options -->
                        <div class="form-group">
                            <label for="event-category">Event Category</label>
                            <select id="event-category" name="category" class="form-control">
                                <option value="">Select a category (optional)</option>
                                <?php if (isset($categories)): ?>
                                    <?php foreach ($categories as $category): ?>
                                        <option value="<?= htmlspecialchars($category['slug']) ?>">
                                            <?= htmlspecialchars($category['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </select>
                            <div class="form-help">Helps people find events they're interested in</div>
                        </div>
                        
                        <!-- Submitter Information -->
                        <div class="form-group">
                            <label for="submitter-email">Your Email (Optional)</label>
                            <input 
                                type="email" 
                                id="submitter-email" 
                                name="submitter_email" 
                                class="form-control"
                                placeholder="your.email@example.com"
                            >
                            <div class="form-help">We'll only use this to contact you about your submission if needed</div>
                        </div>
                        
                        <!-- Preview Section -->
                        <div id="event-preview" class="preview-section" style="display: none;">
                            <h3><i class="fas fa-eye"></i> Event Preview</h3>
                            <div class="preview-event">
                                <div id="preview-content">
                                    <!-- Preview will be populated by JavaScript -->
                                </div>
                            </div>
                        </div>
                        
                        <!-- Form Actions -->
                        <div class="form-actions">
                            <button type="button" id="preview-btn" class="btn btn-outline">
                                <i class="fas fa-eye"></i> Preview Event
                            </button>
                            <button type="submit" id="submit-btn" class="btn btn-primary">
                                <i class="fas fa-paper-plane"></i> Submit Event
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </main>
    </div>
    
    <!-- Success Modal -->
    <div id="success-modal" class="modal">
        <div class="modal-content small">
            <h3><i class="fas fa-check-circle" style="color: var(--success-color);"></i> Event Submitted Successfully!</h3>
            <p>Thank you for submitting your event. It will be reviewed and typically appears on the calendar within 24-48 hours.</p>
            <p>You'll receive an email confirmation if you provided your email address.</p>
            <div class="modal-actions">
                <a href="/events" class="btn btn-primary">View Calendar</a>
                <button onclick="location.reload()" class="btn btn-outline">Submit Another Event</button>
            </div>
        </div>
    </div>
    
    <!-- Error Display -->
    <div id="error-display" style="display: none;"></div>
    
    <script>
        // Initialize form functionality
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('event-submit-form');
            const previewBtn = document.getElementById('preview-btn');
            const submitBtn = document.getElementById('submit-btn');
            const addressInput = document.getElementById('event-address');
            
            // Address autocomplete
            if (typeof google !== 'undefined' && google.maps) {
                initializeAddressAutocomplete();
            }
            
            // Form validation
            form.addEventListener('submit', handleFormSubmit);
            
            // Preview functionality
            previewBtn.addEventListener('click', showPreview);
            
            // Real-time validation
            form.addEventListener('input', validateForm);
            
            // Date validation
            setupDateValidation();
        });
        
        function initializeAddressAutocomplete() {
            const addressInput = document.getElementById('event-address');
            const suggestionsContainer = document.getElementById('address-suggestions');
            
            const autocomplete = new google.maps.places.Autocomplete(addressInput, {
                types: ['establishment', 'geocode'],
                componentRestrictions: { country: 'us' },
                bounds: new google.maps.LatLngBounds(
                    new google.maps.LatLng(46.4, -120.8),  // Southwest corner of Yakima area
                    new google.maps.LatLng(46.8, -120.2)   // Northeast corner of Yakima area
                )
            });
            
            autocomplete.addListener('place_changed', function() {
                const place = autocomplete.getPlace();
                if (place.geometry) {
                    // Store coordinates for later use
                    addressInput.dataset.lat = place.geometry.location.lat();
                    addressInput.dataset.lng = place.geometry.location.lng();
                }
            });
        }
        
        function setupDateValidation() {
            const startDate = document.getElementById('start-date');
            const endDate = document.getElementById('end-date');
            const today = new Date().toISOString().split('T')[0];
            
            // Set minimum date to today
            startDate.min = today;
            endDate.min = today;
            
            // Update end date minimum when start date changes
            startDate.addEventListener('change', function() {
                endDate.min = startDate.value;
                if (endDate.value && endDate.value < startDate.value) {
                    endDate.value = startDate.value;
                }
            });
        }
        
        function validateForm() {
            const form = document.getElementById('event-submit-form');
            const submitBtn = document.getElementById('submit-btn');
            const requiredFields = form.querySelectorAll('[required]');
            
            let isValid = true;
            
            requiredFields.forEach(field => {
                if (!field.value.trim()) {
                    isValid = false;
                    field.classList.add('error');
                } else {
                    field.classList.remove('error');
                }
            });
            
            // Validate date/time combination
            const startDate = document.getElementById('start-date').value;
            const startTime = document.getElementById('start-time').value;
            const endDate = document.getElementById('end-date').value;
            const endTime = document.getElementById('end-time').value;
            
            if (startDate && startTime) {
                const startDateTime = new Date(`${startDate}T${startTime}`);
                const now = new Date();
                
                if (startDateTime <= now) {
                    isValid = false;
                    showError('Event start time must be in the future');
                }
                
                if (endDate && endTime) {
                    const endDateTime = new Date(`${endDate}T${endTime}`);
                    if (endDateTime <= startDateTime) {
                        isValid = false;
                        showError('Event end time must be after start time');
                    }
                }
            }
            
            submitBtn.disabled = !isValid;
            return isValid;
        }
        
        function showPreview() {
            const formData = getFormData();
            const previewSection = document.getElementById('event-preview');
            const previewContent = document.getElementById('preview-content');
            
            const startDateTime = new Date(`${formData.start_date}T${formData.start_time}`);
            const endDateTime = formData.end_date && formData.end_time ? 
                new Date(`${formData.end_date}T${formData.end_time}`) : null;
            
            previewContent.innerHTML = `
                <h4>${formData.title || 'Untitled Event'}</h4>
                <div class="event-meta">
                    <p><i class="fas fa-calendar"></i> ${startDateTime.toLocaleDateString('en-US', { 
                        weekday: 'long', 
                        year: 'numeric', 
                        month: 'long', 
                        day: 'numeric' 
                    })}</p>
                    <p><i class="fas fa-clock"></i> ${startDateTime.toLocaleTimeString('en-US', { 
                        hour: 'numeric', 
                        minute: '2-digit' 
                    })}${endDateTime ? ` - ${endDateTime.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit' })}` : ''}</p>
                    ${formData.location ? `<p><i class="fas fa-map-marker-alt"></i> ${formData.location}</p>` : ''}
                    ${formData.address ? `<p><i class="fas fa-location-dot"></i> ${formData.address}</p>` : ''}
                </div>
                ${formData.description ? `<div class="event-description">${formData.description}</div>` : ''}
                ${formData.external_url ? `<p><a href="${formData.external_url}" target="_blank">More Information</a></p>` : ''}
            `;
            
            previewSection.style.display = 'block';
            previewSection.scrollIntoView({ behavior: 'smooth' });
        }
        
        function getFormData() {
            const form = document.getElementById('event-submit-form');
            const formData = new FormData(form);
            const data = {};
            
            for (let [key, value] of formData.entries()) {
                data[key] = value;
            }
            
            return data;
        }
        
        async function handleFormSubmit(e) {
            e.preventDefault();
            
            if (!validateForm()) {
                showError('Please fill in all required fields correctly.');
                return;
            }
            
            const submitBtn = document.getElementById('submit-btn');
            const originalText = submitBtn.innerHTML;
            
            // Show loading state
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Submitting...';
            
            try {
                const formData = getFormData();
                
                // Combine date and time fields
                const startDateTime = `${formData.start_date}T${formData.start_time}:00`;
                const endDateTime = formData.end_date && formData.end_time ? 
                    `${formData.end_date}T${formData.end_time}:00` : null;
                
                // Build contact info object
                const contactInfo = {};
                if (formData.contact_email) contactInfo.email = formData.contact_email;
                if (formData.contact_phone) contactInfo.phone = formData.contact_phone;
                
                // Prepare submission data
                const eventData = {
                    title: formData.title,
                    description: formData.description || null,
                    start_datetime: startDateTime,
                    end_datetime: endDateTime,
                    location: formData.location || null,
                    address: formData.address || null,
                    external_url: formData.external_url || null,
                    contact_info: Object.keys(contactInfo).length > 0 ? contactInfo : null
                };
                
                // Add coordinates if available
                const addressInput = document.getElementById('event-address');
                if (addressInput.dataset.lat && addressInput.dataset.lng) {
                    eventData.latitude = parseFloat(addressInput.dataset.lat);
                    eventData.longitude = parseFloat(addressInput.dataset.lng);
                }
                
                // Submit to API
                const response = await fetch('/ajax/calendar-events.php/submit', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(eventData)
                });
                
                const result = await response.json();
                
                if (result.success) {
                    // Show success modal
                    document.getElementById('success-modal').style.display = 'block';
                } else {
                    showError(result.error || 'Failed to submit event. Please try again.');
                }
                
            } catch (error) {
                console.error('Submission error:', error);
                showError('Network error. Please check your connection and try again.');
            } finally {
                // Restore button
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText;
            }
        }
        
        function showError(message) {
            // Remove existing error alerts
            const existingAlerts = document.querySelectorAll('.alert-error');
            existingAlerts.forEach(alert => alert.remove());
            
            // Create and show error alert
            const alert = document.createElement('div');
            alert.className = 'alert alert-error';
            alert.innerHTML = `<i class="fas fa-exclamation-triangle"></i> ${message}`;
            
            const formContent = document.querySelector('.form-content');
            formContent.insertBefore(alert, formContent.firstChild);
            
            // Scroll to top to show error
            alert.scrollIntoView({ behavior: 'smooth' });
            
            // Auto-remove after 5 seconds
            setTimeout(() => {
                alert.remove();
            }, 5000);
        }
        
        // Close modal when clicking outside
        window.addEventListener('click', function(e) {
            const modal = document.getElementById('success-modal');
            if (e.target === modal) {
                modal.style.display = 'none';
            }
        });
    </script>
</body>
</html>