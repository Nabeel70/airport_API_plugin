jQuery(document).ready(function($) {
    var searchTimeout;

    // Delegate airport search input for dynamically injected content
    $(document).on('input', '.airport-search', function() {
        var input = $(this);
        var searchTerm = input.val();
        var fieldName = input.data('field');
        var suggestionsDiv = input.siblings('.airport-suggestions');

        clearTimeout(searchTimeout);

        if (searchTerm.length < 3) {
            suggestionsDiv.hide().empty();
            return;
        }
        searchTimeout = setTimeout(function() {
            searchAirports(searchTerm, fieldName, input, suggestionsDiv);
        }, 300);
    });

    // Hide suggestions when clicking outside
    $(document).on('click', function(e) {
        if (!$(e.target).closest('.form-group').length) {
            $('.airport-suggestions').hide();
        }
    });

    function searchAirports(searchTerm, fieldName, input, suggestionsDiv) {
        var fromLocation = $('#from_location').val();
        var toLocation = $('#to_location').val();

        $.ajax({
            url: airducap_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'airducap_search_airports',
                q: searchTerm,
                field_name: fieldName,
                from_location: fromLocation || '',
                to_location: toLocation || '',
                nonce: airducap_ajax.nonce
            },
            beforeSend: function() {
                suggestionsDiv.html('<div class="loading">Searching...</div>').show();
            },
            success: function(response) {
                console.log('Airport search response:', response);

                if (response && response.success && response.data) {
                    // Fix: Check if response.data has a nested data property (array)
                    var list = Array.isArray(response.data) ? response.data : response.data.data;
                    console.log('Airport data:', list);

                    if (Array.isArray(list) && list.length > 0) {
                        var html = '<ul class="airport-list">';
                        var validAirports = 0;

                        $.each(list, function(index, airport) {
                            console.log('Processing airport:', airport);

                            // More flexible property access
                            var id = airport.id || airport.pk || airport.airport_id;
                            var name = airport.name || airport.title || airport.label;

                            // Convert to string if needed
                            if (id) id = String(id);
                            if (name) name = String(name);

                            // More lenient validation - just need non-empty values
                            if (id && name && id.trim() && name.trim()) {
                                html += '<li class="airport-item" data-id="' + id + '" data-name="' + name + '">';
                                html += '<span class="airport-name">' + name + '</span>';
                                html += '</li>';
                                validAirports++;
                            } else {
                                console.log('Skipping airport - missing data:', {id: id, name: name, raw: airport});
                            }
                        });

                        html += '</ul>';

                        if (validAirports > 0) {
                            suggestionsDiv.html(html).show();
                        } else {
                            suggestionsDiv.html('<div class="no-results">No valid airports found in response</div>').show();
                        }
                    } else {
                        console.log('Invalid list data:', {isArray: Array.isArray(list), length: list ? list.length : 'N/A', list: list});
                        suggestionsDiv.html('<div class="no-results">No airports found</div>').show();
                    }
                } else {
                    console.log('Invalid response structure:', response);
                    var errorMsg = 'No airports found';
                    if (response && !response.success && response.data) {
                        if (typeof response.data === 'string') {
                            errorMsg = response.data;
                        } else if (response.data.message) {
                            errorMsg = response.data.message;
                        } else if (response.data.error) {
                            errorMsg = response.data.error;
                        }
                    }
                    suggestionsDiv.html('<div class="no-results">' + errorMsg + '</div>').show();
                }
            },
            error: function(xhr, status, error) {
                console.error('Airport search error:', error);
                console.error('Response:', xhr.responseText);
                suggestionsDiv.html('<div class="error">Error searching airports: ' + error + '</div>').show();
            }
        });
    }

    // Handle airport selection
    $(document).on('click', '.airport-item', function() {
        var item = $(this);
        var airportId = item.data('id');
        var airportName = item.data('name');
        var input = item.closest('.form-group').find('.airport-search');
        var hiddenInput = item.closest('.form-group').find('input[type="hidden"]');

        input.val(airportName);
        hiddenInput.val(airportId);
        item.closest('.airport-suggestions').hide();

        console.log('Selected airport:', airportName, 'ID:', airportId); // Debug log
    });

    // Delegate submit to handle dynamically injected form
    $(document).on('submit', '#airducap-flight-search', function(e) {
        e.preventDefault(); // This prevents the page refresh
        e.stopPropagation();

        console.log('Form submitted via AJAX'); // Debug log

        var form = $(this);
        var fromLocation = $('#from_location').val();
        var toLocation = $('#to_location').val();
        var departDate = $('#depart_date').val();
        var returnDate = $('#return_date').val();
        var adults = $('#adults').val();
        var children = $('#children').val();
        var infants = $('#infants').val();

        // Convert date format to DD/MM/YYYY as expected by the API
        function convertDateFormat(dateString) {
            console.log('Converting date:', dateString); // Debug log
            if (!dateString) return '';
            
            var s = dateString.trim();
            
            // If contains '-' and looks like YYYY-MM-DD (HTML date input format)
            if (s.indexOf('-') !== -1) {
                var parts = s.split('-');
                if (parts.length === 3 && parts[0].length === 4) {
                    var day = parts[2].padStart(2, '0');
                    var month = parts[1].padStart(2, '0');
                    var year = parts[0];
                    var result = day + '/' + month + '/' + year;
                    console.log('Converted YYYY-MM-DD to DD/MM/YYYY:', result);
                    return result;
                }
            }
            
            // If contains '/' - try to detect if it's already DD/MM/YYYY
            if (s.indexOf('/') !== -1) {
                var parts = s.split('/');
                if (parts.length === 3) {
                    var first = parseInt(parts[0], 10);
                    var second = parseInt(parts[1], 10);
                    var year = parts[2];
                    
                    // If first part > 12, it's likely DD/MM/YYYY already
                    if (first > 12) {
                        var result = parts[0].padStart(2, '0') + '/' + parts[1].padStart(2, '0') + '/' + year;
                        console.log('Already DD/MM/YYYY format:', result);
                        return result;
                    }
                    
                    // If second part > 12, it's MM/DD/YYYY, need to swap
                    if (second > 12) {
                        var result = parts[1].padStart(2, '0') + '/' + parts[0].padStart(2, '0') + '/' + year;
                        console.log('Converted MM/DD/YYYY to DD/MM/YYYY:', result);
                        return result;
                    }
                    
                    // Both are <= 12, assume it's DD/MM/YYYY format already
                    var result = parts[0].padStart(2, '0') + '/' + parts[1].padStart(2, '0') + '/' + year;
                    console.log('Assuming DD/MM/YYYY format:', result);
                    return result;
                }
            }
            
            // Fallback: return as-is
            console.log('Returning date as-is:', s);
            return s;
        }

        var formattedDepartDate = convertDateFormat(departDate);
        var formattedReturnDate = convertDateFormat(returnDate);

        console.log('Raw form data:', {
            fromLocation: fromLocation,
            toLocation: toLocation,
            departDate: departDate,
            returnDate: returnDate,
            adults: adults
        }); // Debug log

        console.log('Formatted dates:', {
            originalDepartDate: departDate,
            formattedDepartDate: formattedDepartDate,
            originalReturnDate: returnDate,
            formattedReturnDate: formattedReturnDate
        }); // Debug log

        // Validate required fields
        if (!fromLocation || !toLocation || !departDate || !adults) {
            alert('Please fill in all required fields: Origin, Destination, Departure Date, and number of Adults.');
            return false;
        }
        
        var formData = {
            action: 'airducap_search_flights',
            from_location: fromLocation,
            to_location: toLocation,
            date_of_travel: formattedDepartDate, // Use formatted date
            adults: adults,
            // Ensure currency is always sent to match live pricing/availability
            currency: (airducap_ajax && airducap_ajax.default_currency) ? airducap_ajax.default_currency : 'ZAR',
            nonce: airducap_ajax.nonce
        };

        // Add optional fields
        if (formattedReturnDate) {
            formData.date_of_return = formattedReturnDate; // Use formatted date
        }
        if (children && children > 0) {
            formData.children = children;
        }
        if (infants && infants > 0) {
            formData.infants = infants;
        }

        console.log('Final form data being sent to API:', formData); // Debug log

        searchFlights(formData);
        return false; // Prevent any form submission
    });

    function searchFlights(formData) {
        var resultsDiv = $('#flight-results');

        console.log('Searching flights with data:', formData); // Debug log

        $.ajax({
            url: airducap_ajax.ajax_url,
            type: 'POST',
            data: formData,
            beforeSend: function() {
                resultsDiv.html('<div class="loading-flights"><div class="spinner"></div><p>Searching for flights...</p></div>').show();
            },
            success: function(response) {
                console.log('Flight search response:', response); // Debug log
                if (response.success) {
                    displayFlightResults(response.data, resultsDiv);
                } else {
                    resultsDiv.html('<div class="error">Error: ' + response.data + '</div>');
                }
            },
            error: function(xhr, status, error) {
                console.error('Flight search error:', xhr.responseText);
                resultsDiv.html('<div class="error">Error searching flights: ' + error + '<br>Response: ' + xhr.responseText + '</div>');
            }
        });
    }
    
    function displayFlightResults(data, resultsDiv) {
        console.log('Displaying flight results:', data); // Debug log

        var html = '<div class="flight-results-container">';
        html += '<h2>Flight Search Results</h2>';

        // Simplified map image handling - no complex inline JavaScript
        if (data.map_image_url) {
            console.log('Map image URL:', data.map_image_url); // Debug the exact URL
            html += '<div class="route-map">';
            html += '<img src="' + data.map_image_url + '" alt="Flight Route Map" class="route-map-img" />';
            html += '<div class="map-fallback" style="display: none;">';
            html += '<div class="map-error">Map image failed to load. <a href="' + data.map_image_url + '" target="_blank">Click here to view map</a></div>';
            html += '</div>';
            html += '</div>';
        }
        
        if (data.notifications && data.notifications.length > 0) {
            html += '<div class="notifications">';
            $.each(data.notifications, function(index, notification) {
                html += '<div class="notification">' + notification + '</div>';
            });
            html += '</div>';
        }
        
        if (data.available_flights && data.available_flights.length > 0) {
            html += '<div class="flights-grid">';

            $.each(data.available_flights, function(index, flight) {
                // Normalize fields with fallbacks
                var planeName = flight.plane_name || flight.plane || flight.aircraft || 'Aircraft';
                var price = flight.computed_price || flight.price || '';
                var pricePerHead = flight.price_per_head || flight.price_per_person || '';
                var duration = flight.duration || '';
                var distance = flight.distance || flight.distance_km || '';
                var speed = flight.speed || '';
                var seats = flight.number_of_seats || flight.seats || '';
                var bookingUrl = flight.booking_url || flight.book_url || flight.url || '';

                // Images: support array or comma-separated string
                var images = [];
                if (Array.isArray(flight.plane_image_urls)) {
                    images = flight.plane_image_urls;
                } else if (typeof flight.plane_image_urls === 'string' && flight.plane_image_urls.length) {
                    images = flight.plane_image_urls.split(',').map(function(s){ return s.trim(); }).filter(Boolean);
                } else if (Array.isArray(flight.image_urls)) {
                    images = flight.image_urls;
                } else if (typeof flight.image_urls === 'string' && flight.image_urls.length) {
                    images = flight.image_urls.split(',').map(function(s){ return s.trim(); }).filter(Boolean);
                }

                var coverImg = images.length ? images[0] : '';

                html += '<div class="flight-card">';

                // Image section
                if (coverImg) {
                    html += '<div class="flight-card-image">';
                    html += '<img src="' + coverImg + '" alt="' + planeName + '" loading="lazy"/>';
                    if (images.length > 1) {
                        html += '<div class="flight-thumbs">';
                        images.slice(1,4).forEach(function(u){ html += '<img src="' + u + '" alt="thumb" loading="lazy"/>'; });
                        html += '</div>';
                    }
                    html += '</div>';
                }

                // Content section
                html += '<div class="flight-card-body">';
                html += '<h4 class="flight-title">' + planeName + '</h4>';

                html += '<div class="flight-meta">';
                if (duration) html += '<span class="meta-item"><i class="ico duration"></i>' + duration + '</span>';
                if (distance) html += '<span class="meta-item"><i class="ico distance"></i>' + ('' + distance).split('.')[0] + ' km</span>';
                if (speed) html += '<span class="meta-item"><i class="ico speed"></i>' + speed + ' kt</span>';
                if (seats) html += '<span class="meta-item"><i class="ico seats"></i>' + seats + ' seats</span>';
                html += '</div>';

                if (price) {
                    html += '<div class="flight-price">';
                    html += '<div class="price-main">' + price + '</div>';
                    if (pricePerHead) {
                        html += '<div class="price-sub">Per passenger: ' + pricePerHead + '</div>';
                    }
                    html += '</div>';
                }

                html += '<div class="flight-actions">';
                if (bookingUrl) {
                    html += '<a class="btn-book-flight" href="' + bookingUrl + '" target="_blank" rel="noopener">Book Now</a>';
                }
                html += '</div>';

                html += '</div>'; // body
                html += '</div>'; // card
            });

            html += '</div>'; // grid
        } else {
            html += '<div class="no-flights">No flights available for the selected criteria.</div>';
        }

        html += '</div>';
        resultsDiv.html(html);

        // Handle map image loading with jQuery event handlers (cleaner than inline JavaScript)
        if (data.map_image_url) {
            var $mapImg = $('.route-map-img');

            // Add a timeout to handle slow loading
            var imageLoadTimeout = setTimeout(function() {
                console.warn('Map image loading timeout - showing fallback');
                $mapImg.hide();
                $('.map-fallback').show();
            }, 10000); // 10 second timeout

            $mapImg.on('load', function() {
                console.log('Map image loaded successfully');
                clearTimeout(imageLoadTimeout);
                $(this).siblings('.map-fallback').hide();
                $(this).show();
            }).on('error', function() {
                console.error('Map image failed to load:', this.src);
                clearTimeout(imageLoadTimeout);

                // Try alternative map image loading approach
                var mapUrl = this.src;

                // Create a temporary image to test if it's a CORS/Mixed Content issue
                var testImg = new Image();
                testImg.onload = function() {
                    console.log('Map image accessible via JavaScript - likely a display issue');
                    // Try to load it again by changing the src
                    $mapImg.attr('src', '').attr('src', mapUrl);
                };
                testImg.onerror = function() {
                    console.error('Map image completely inaccessible - API key or domain restriction issue');
                    // Show detailed error message
                    $('.map-fallback').html(
                        '<div class="map-error">' +
                        '<strong>Map image failed to load</strong><br>' +
                        'This may be due to:<br>' +
                        '• Google API key domain restrictions<br>' +
                        '• Mixed content security policy<br>' +
                        '• API key permissions<br><br>' +
                        '<a href="' + mapUrl + '" target="_blank" class="btn-view-map">Click here to view map in new tab</a>' +
                        '</div>'
                    );
                };
                testImg.src = mapUrl;

                $(this).hide();
                $(this).siblings('.map-fallback').show();
            });
        }
    }

    // Handle flight booking
    $(document).on('click', '.btn-book-flight', function() {
        var flightData = $(this).data('flight');
        console.log('Booking flight:', flightData);
        alert('Booking functionality can be implemented here. Flight data: ' + JSON.stringify(flightData));
    });

    // Initialize datepickers to enforce dd/mm/yyyy format
    var dpOptions = {
        dateFormat: 'dd/mm/yy',
        minDate: 0,
        changeMonth: true,
        changeYear: true
    };

    // Bind on focus so fields added later get a datepicker
    if ($.fn.datepicker) {
        $(document).on('focus', '#depart_date, #return_date', function() {
            var $this = $(this);
            if (!$this.hasClass('hasDatepicker')) {
                $this.datepicker(dpOptions);
                if ($this.attr('id') === 'depart_date') {
                    $this.on('change', function () {
                        var d = $this.datepicker('getDate');
                        if (d) {
                            $('#return_date').datepicker('option', 'minDate', d);
                        }
                    });
                }
            }
        });
    } else {
        // Fallback for environments without jQuery UI
        var today = new Date().toISOString().split('T')[0];
        $('#depart_date, #return_date').attr('min', today);
        $('#depart_date').on('change', function() {
            var departDate = $(this).val();
            $('#return_date').attr('min', departDate);
            var returnDate = $('#return_date').val();
            if (returnDate && returnDate < departDate) {
                $('#return_date').val('');
            }
        });
    }

    // Debug: Check if AJAX variables are loaded
    console.log('AirDuCap AJAX object:', airducap_ajax);
});