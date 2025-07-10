// Initialize calendar with correct endpoints
document.addEventListener('DOMContentLoaded', function() {
    // Override the default API endpoint
    window.calendar = new YakimaCalendar({
        apiEndpoint: '/api/events-simple.php',
        shopsEndpoint: '/api/shops/',
        currentDate: new Date(),
        defaultView: 'day'
    });
    
    window.calendar.init();
});