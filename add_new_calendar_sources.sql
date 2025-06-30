-- Add new calendar sources for Yakima Valley area
-- These sources cover municipal calendars, community events, and tourism sites

INSERT INTO calendar_sources (name, url, scrape_type, scrape_config, active) VALUES

-- City/Municipal Calendars
('City of Ellensburg Calendar', 'https://ci.ellensburg.wa.us/Calendar.aspx', 'html', 
 JSON_OBJECT('geographic_area', 'Ellensburg', 'event_types', 'Municipal events, Community events', 'notes', 'Official city calendar; URL validated'), 1),

('City of Grandview Calendar', 'https://www.grandview.wa.us/calendar/', 'html', 
 JSON_OBJECT('geographic_area', 'Grandview', 'event_types', 'City events, Library events', 'notes', 'Includes City Calendar and Library Calendar; URL validated'), 1),

('City of Moxee Calendar', 'https://cityofmoxee.us/', 'html', 
 JSON_OBJECT('geographic_area', 'Moxee', 'event_types', 'Community events, Hop Festival, Lighted Parade, Volksfest', 'notes', 'Official city website; Features Moxee Lighted Parade, Hop Festival, and other community events'), 1),

('City of Selah Events', 'https://selahwa.gov/parks-and-recreation/city-events/', 'html', 
 JSON_OBJECT('geographic_area', 'Selah', 'event_types', 'Community events, Sports events, Car shows', 'notes', 'Includes events like Hot Rods on First Street, Firecracker 5k; URL validated'), 1),

('City of Sunnyside Calendar', 'https://www.sunnyside-wa.gov/Calendar.aspx', 'html', 
 JSON_OBJECT('geographic_area', 'Sunnyside', 'event_types', 'City events, community gatherings', 'notes', 'Contact: 818 E. Edison Ave, Sunnyside, WA 98944, Phone: 509-836-6305; URL validated'), 1),

('City of Toppenish Calendar', 'https://www.visityakima.com/yakima-valley-event-location/Toppenish', 'html', 
 JSON_OBJECT('geographic_area', 'Toppenish', 'event_types', 'City Council Meetings, Planning Commission Meetings, Government Events', 'notes', 'Toppenish events on Visit Yakima tourism site; URL validated'), 1),

('City of Union Gap Calendar', 'https://uniongapwa.gov/events/', 'html', 
 JSON_OBJECT('geographic_area', 'Union Gap', 'event_types', 'Municipal events', 'notes', 'Official city calendar; URL validated'), 1),

('City of Wapato Calendar', 'https://wapato-city.org/calendar.php', 'html', 
 JSON_OBJECT('geographic_area', 'Wapato', 'event_types', 'City events', 'notes', 'Located at 205 E 3rd St., Wapato, WA 98951; URL validated'), 1),

('City of Yakima Official Calendar', 'https://www.yakimawa.gov/media/calendar/', 'html', 
 JSON_OBJECT('geographic_area', 'Yakima City', 'event_types', 'City Council meetings, Planning Commission meetings, municipal events', 'notes', 'Official city government calendar; URL validated'), 1),

('City of Zillah Calendar', 'https://www.cityofzillah.us/calendar.php', 'html', 
 JSON_OBJECT('geographic_area', 'Zillah', 'event_types', 'City Council meetings, Civic Center events', 'notes', 'Municipal and community events; URL validated'), 1),

-- Tourism and Community Events
('Come to the Sun Events', 'https://www.visityakima.com/yakima-valley-event-location/Sunnyside', 'html', 
 JSON_OBJECT('geographic_area', 'Sunnyside', 'event_types', 'Festivals, Farmers Markets, Seasonal events', 'notes', 'Sunnyside events on Visit Yakima tourism site; URL validated'), 1),

('Downtown Yakima Events', 'https://downtownyakima.com/events/downtown-summer-nights/', 'html', 
 JSON_OBJECT('geographic_area', 'Downtown Yakima', 'event_types', 'Summer events, community gatherings', 'notes', 'Features Yakima Federal Downtown Summer Nights; URL validated'), 1),

('Ellensburg Downtown Events', 'https://www.ellensburgdowntown.org/edaevents', 'html', 
 JSON_OBJECT('geographic_area', 'Downtown Ellensburg', 'event_types', 'Night Markets, Arts events, Community gatherings', 'notes', 'Features events like Ellensburg Night Market, Ignite the Arts; URL validated'), 1),

-- Chamber of Commerce and Business Organizations
('Greater Yakima Chamber of Commerce Event Calendar', 'https://chamber.yakima.org/community-events', 'html', 
 JSON_OBJECT('geographic_area', 'Yakima', 'event_types', 'Arts & Culture, Chamber of Commerce, Clubs', 'notes', 'Contact: 10 North 9th Street, Yakima, WA 98901, Phone: (509) 248-2021; URL validated'), 1),

('Kittitas County Chamber Events', 'https://business.kittitascountychamber.com/events/calendar', 'html', 
 JSON_OBJECT('geographic_area', 'Ellensburg/Kittitas County', 'event_types', 'Summer Concert Series, Wine Tours, Health Clinics, Field Day events', 'notes', 'Chamber-sponsored events; URL validated'), 1),

-- Community Groups and Organizations
('Moxee Community Group', 'https://www.facebook.com/groups/988948489198735/', 'facebook', 
 JSON_OBJECT('geographic_area', 'Moxee', 'event_types', 'Community events, Yard sales, Local gatherings', 'notes', 'Community Facebook group for Moxee events and activities; May require login'), 0),

('Moxee Hop Festival', 'https://www.evcea.org/', 'html', 
 JSON_OBJECT('geographic_area', 'Moxee', 'event_types', 'Festival, Community celebration', 'notes', 'Annual event organized by East Valley Enhancement Association (EVCEA); URL validated'), 1),

('Selah Community Days', 'https://www.selahdays.com/pages/events.html', 'html', 
 JSON_OBJECT('geographic_area', 'Selah', 'event_types', 'Community celebrations, Local festivals', 'notes', 'Annual celebration on the 3rd weekend in May; URL validated'), 1),

-- Major Venues and Entertainment
('State Fair Park Event Calendar', 'https://www.statefairpark.org/events', 'html', 
 JSON_OBJECT('geographic_area', 'Yakima', 'event_types', 'Sporting Events, Concerts, Trade Shows, Family Events', 'notes', 'Major venue for large events in Yakima; URL validated'), 1),

-- Media and News Sources
('Sunnyside Sun Events', 'https://www.sunnysidesun.com/calendar/', 'html', 
 JSON_OBJECT('geographic_area', 'Sunnyside', 'event_types', 'Local community events, Senior center activities, Arts and crafts events', 'notes', 'Local newspaper event listings; URL validated'), 1),

('Yakima Herald-Republic Events Calendar', 'https://www.yakimaherald.com/calendar/', 'html', 
 JSON_OBJECT('geographic_area', 'Yakima and surrounding areas', 'event_types', 'Various community events', 'notes', 'Local newspaper event listings; URL validated'), 1),

-- Educational Institutions
('Town of Naches Events', 'https://www.visityakima.com/yakima-valley-event-location/Naches', 'html', 
 JSON_OBJECT('geographic_area', 'Naches', 'event_types', 'Municipal events, Council Study Sessions', 'notes', 'Naches events on Visit Yakima tourism site; URL validated'), 1),

('Wapato School District Activities', 'https://www.wapatosd.org/apps/pages/index.jsp?uREC_ID=791044&type=d&pREC_ID=1184013', 'html', 
 JSON_OBJECT('geographic_area', 'Wapato', 'event_types', 'School district events and activities', 'notes', '2024-25 Activity Calendar available; URL validated'), 1),

('Wapato Wolves Athletics', 'https://wapatoathletics.com/events', 'html', 
 JSON_OBJECT('geographic_area', 'Wapato', 'event_types', 'Sporting events, Game schedules', 'notes', 'School athletics events; URL validated'), 1),

('Zillah High School Events', 'https://zhs.zillahschools.org/apps/events/', 'html', 
 JSON_OBJECT('geographic_area', 'Zillah', 'event_types', 'School events, Sports, Academic events', 'notes', 'School-related activities; URL validated'), 1),

-- Government and Regional Organizations
('YVCOG Calendar', 'https://www.yvcog.us/Calendar.aspx', 'html', 
 JSON_OBJECT('geographic_area', 'Yakima Valley', 'event_types', 'Regional meetings and events', 'notes', 'Government coordination events; URL validated'), 1),

('Yakima County Community Events Calendar', 'https://yakimacounty.us/2887/Community-Events-Calendar', 'html', 
 JSON_OBJECT('geographic_area', 'Yakima County', 'event_types', 'County-wide events, Yakama Nation Treaty Days', 'notes', 'Official county government calendar; URL validated'), 1),

-- Libraries and Cultural Organizations
('Yakima Valley Libraries Events Calendar', 'https://yvl.libcal.com/', 'html', 
 JSON_OBJECT('geographic_area', 'Yakima Valley', 'event_types', 'Library events, Community programs, Educational workshops', 'notes', 'Official library system events calendar; URL validated'), 1),

-- Tourism-Specific Event Locations
('Yakima Valley Tourism - Naches Events', 'https://www.visityakima.com/yakima-valley-event-location/Naches', 'html', 
 JSON_OBJECT('geographic_area', 'Naches', 'event_types', 'Local venue events, Music festivals', 'notes', 'Includes events like Chinook Fest, Whistlin Jack''s Lodge events; URL validated'), 1),

('Yakima Valley Tourism - Toppenish Events', 'https://www.visityakima.com/yakima-valley-event-location/Toppenish', 'html', 
 JSON_OBJECT('geographic_area', 'Toppenish', 'event_types', 'Rodeos, Concerts, Casino Events', 'notes', 'Includes events like 90th Annual Toppenish Rodeo, Live MMA at Legends Casino; URL validated'), 1),

('Yakima Valley Tourism - Union Gap Events', 'https://www.visityakima.com/yakima-valley-event-location/Union%20Gap', 'html', 
 JSON_OBJECT('geographic_area', 'Union Gap', 'event_types', 'Community events, Historical reenactments, Agricultural events', 'notes', 'Features events like Union Gap Old Town Days & Civil War Reenactment; URL validated'), 1),

('Yakima Valley Tourism Events Calendar', 'https://www.visityakima.com/yakima-valley-events', 'html', 
 JSON_OBJECT('geographic_area', 'Yakima Valley', 'event_types', 'Kids and Family, Live Music, Festivals and Fun, Wine Country Events, Beer & Hop Events', 'notes', 'Comprehensive tourism-focused calendar; URL validated'), 1);

-- Show summary of added sources
SELECT 'New calendar sources added successfully!' as status;
SELECT COUNT(*) as total_sources FROM calendar_sources;