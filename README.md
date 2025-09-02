# Air Du Cap API Integration Plugin

## Overview
This WordPress plugin integrates with the Air Du Cap API to provide flight booking and airport search functionality on your website. It creates a beautiful search form that matches your existing design and provides real-time flight search capabilities.

## Features
- Real-time airport search with autocomplete
- Flight search with multiple parameters
- Responsive design matching your existing style
- AJAX-powered search (no page reloads)
- Support for passenger counts (adults, children, infants)
- Date validation and selection
- Business/Economy class selection
- Error handling and validation
- Production-ready code with security features

## Installation

1. **Upload the Plugin**
   - The plugin is already installed in: `wp-content/plugins/airducap-integration/`

2. **Activate the Plugin**
   - Go to WordPress Admin → Plugins
   - Find "Air Du Cap API Integration"
   - Click "Activate"

3. **Configure API Credentials**
   - The plugin is pre-configured with your API credentials:
     - Username: dev101@dev101.com
     - Password: QgdYlFgTvAQTcCC
     - API Base URL: https://uat-book.airducap.com

## Usage

### Using Shortcodes

#### 1. Flight Search Form
Add this shortcode to any page or post where you want the search form:

```
[airducap_search_form]
```

**Optional Parameters:**
- `show_tours="false"` - Hide the tours tab (default: true)

**Example:**
```
[airducap_search_form show_tours="false"]
```

#### 2. Flight Results (Optional)
If you want to display results on a separate page:

```
[airducap_flight_results]
```

### Integration with Elementor

1. **Add HTML Widget**
   - In Elementor, add an HTML widget
   - Paste the shortcode: `[airducap_search_form]`

2. **Add to Custom Template**
   - Create a new template in Elementor
   - Add the shortcode to the template
   - Apply the template to your desired pages

### Integration with Page Builders

The plugin works with all major page builders:
- **Elementor**: Use HTML widget with shortcode
- **Gutenberg**: Use Shortcode block
- **Classic Editor**: Paste shortcode directly
- **Other Builders**: Use HTML/Text widget

## API Endpoints Used

The plugin integrates with these Air Du Cap API endpoints:

1. **Airport Search**: `/airports/api/list/`
   - Provides autocomplete airport search
   - Parameters: q (search term), field_name, from_location, to_location

2. **Flight Search**: `/flights/api/search/`
   - Searches for available flights
   - Parameters: from_location, to_location, dates, passengers, etc.

## Functionality

### Airport Search
- Type 2+ characters to trigger search
- Real-time autocomplete dropdown
- Click to select airport
- Validation to prevent same origin/destination

### Flight Search
- Comprehensive form validation
- Date picker with minimum date validation
- Passenger count controls (max 9 passengers)
- Currency support (default USD)
- Loading states and error handling

### Search Results
- Displays available flights in cards
- Shows flight details (airline, times, duration, stops)
- Includes route map if available
- Responsive design for mobile devices

## Customization

### Styling
Edit `/assets/airducap.css` to customize the appearance:
- Colors: Modify CSS variables for brand colors
- Layout: Adjust responsive breakpoints
- Components: Style individual form elements

### Functionality
Edit `/assets/airducap.js` to modify behavior:
- Add custom validation rules
- Modify search parameters
- Add tracking/analytics
- Customize result display

### PHP Integration
Edit `airducap-integration.php` to:
- Modify API credentials
- Add custom hooks
- Extend functionality
- Add admin settings

## Troubleshooting

### Common Issues

1. **No search results**
   - Check API credentials
   - Verify internet connection
   - Check browser console for errors

2. **Styling issues**
   - Clear browser cache
   - Check for CSS conflicts
   - Verify plugin activation

3. **Form not working**
   - Check JavaScript errors in console
   - Verify WordPress AJAX is working
   - Test on different browsers

### Debug Mode
Add this to your wp-config.php for debugging:
```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

## Security Features

- AJAX nonce verification
- Input sanitization
- SQL injection prevention
- XSS protection
- Rate limiting ready

## Browser Support

- Chrome 70+
- Firefox 65+
- Safari 12+
- Edge 44+
- Mobile browsers (iOS Safari, Chrome Mobile)

## Performance

- Optimized AJAX requests
- Debounced search (300ms delay)
- Cached API responses
- Minified assets ready

## Support

For technical support or customizations:
1. Check the WordPress debug log
2. Test with default theme
3. Disable other plugins temporarily
4. Contact your developer for custom modifications

## Changelog

### Version 1.0.0
- Initial release
- Airport search functionality
- Flight search and results
- Responsive design
- API integration with authentication
- Shortcode support
- Error handling and validation

## License

This plugin is licensed under GPL v2 or later.
