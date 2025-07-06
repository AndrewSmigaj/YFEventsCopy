// Initialize calendar with correct endpoints
document.addEventListener('DOMContentLoaded', function() {
    // Override the default API endpoint
    window.calendar = new YakimaCalendar({
        apiEndpoint: '/api/events',
        unifiedEndpoint: '/api/calendar/unified',
        shopsEndpoint: '/api/shops/',
        currentDate: new Date(),
        defaultView: 'month',
        includeEstateSales: true
    });
    
    window.calendar.init();
});