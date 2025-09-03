jQuery(document).ready(function($) {
    var searchTimeout;

    // Initialize passengers dropdown functionality
    initializePassengersDropdown();
    
    // Initialize tab functionality
    initializeTabFunctionality();

    function initializePassengersDropdown() {
        // Toggle dropdown
        $(document).on('click', '#passengers-dropdown', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            var menu = $('#passengers-menu');
            var btn = $(this);
            
            if (menu.hasClass('show')) {
                menu.removeClass('show');
                btn.removeClass('active');
            } else {
                menu.addClass('show');
                btn.addClass('active');
            }
        });

        // Close dropdown when clicking outside
        $(document).on('click', function(e) {
            if (!$(e.target).closest('.passengers-dropdown-container').length) {
                $('#passengers-menu').removeClass('show');
                $('#passengers-dropdown').removeClass('active');
            }
        });

        // Passenger count controls
        $(document).on('click', '.passenger-btn', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            var type = $(this).data('type');
            var isPlus = $(this).hasClass('plus');
            var countElement = $('#' + type + '-count');
            var hiddenInput = $('#' + type);
            var currentCount = parseInt(countElement.text());
            
            var newCount = currentCount;
            
            if (isPlus) {
                newCount = Math.min(currentCount + 1, 9);
            } else {
                var minValue = type === 'adults' ? 1 : 0;
                newCount = Math.max(currentCount - 1, minValue);
            }
            
            countElement.text(newCount);
            hiddenInput.val(newCount);
            
            updatePassengerSummary();
            updateButtonStates();
        });

        // Travel class selection
        $(document).on('change', 'input[name="travel_class"]', function() {
            $('#class').val($(this).val());
            updatePassengerSummary();
        });

        function updatePassengerSummary() {
            var adults = parseInt($('#adults-count').text());
            var children = parseInt($('#children-count').text());
            var infants = parseInt($('#infants-count').text());
            var travelClass = $('input[name="travel_class"]:checked').val();
            
            var totalPassengers = adults + children + infants;
            var summary = '';
            
            if (adults > 0) {
                summary += adults + (adults === 1 ? ' Adult' : ' Adults');
            }
            if (children > 0) {
                summary += (summary ? ', ' : '') + children + (children === 1 ? ' Child' : ' Children');
            }
            if (infants > 0) {
                summary += (summary ? ', ' : '') + infants + (infants === 1 ? ' Infant' : ' Infants');
            }
            
            // Add class
            var classNames = {
                'economy': 'Economy',
                'business': 'Business',
                'first': 'First Class'
            };
            
            summary += ', ' + (classNames[travelClass] || 'Economy');
            
            $('.passenger-summary').text(summary);
        }

        function updateButtonStates() {
            // Update minus button states
            $('.passenger-btn.minus[data-type="adults"]').prop('disabled', parseInt($('#adults-count').text()) <= 1);
            $('.passenger-btn.minus[data-type="children"]').prop('disabled', parseInt($('#children-count').text()) <= 0);
            $('.passenger-btn.minus[data-type="infants"]').prop('disabled', parseInt($('#infants-count').text()) <= 0);
            
            // Update plus button states
            $('.passenger-btn.plus').prop('disabled', false);
            $('.passenger-btn.plus').each(function() {
                var type = $(this).data('type');
                var count = parseInt($('#' + type + '-count').text());
                if (count >= 9) {
                    $(this).prop('disabled', true);
                }
            });
        }

        // Initialize button states
        updateButtonStates();
    }

    function initializeTabFunctionality() {
        $(document).on('click', '.tab-button', function() {
            var tab = $(this).data('tab');
            
            // Update active tab
            $('.tab-button').removeClass('active');
            $(this).addClass('active');
            
            // Handle round trip vs one way functionality
            if (tab === 'round-trip') {
                $('#return_date').attr('required', true);
                $('.return-field').show();
            } else {
                $('#return_date').attr('required', false);
                $('.return-field').show(); // Keep visible but not required
            }
        });
    }

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
                console.log('Response data structure:', JSON.stringify(response.data, null, 2)); // Detailed structure
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

        // Map section
        if (data.map_image_url) {
            console.log('Map image URL:', data.map_image_url);
            html += '<div class="route-map">';
            html += '<img src="' + data.map_image_url + '" alt="Flight Route Map" class="route-map-img" />';
            html += '<div class="map-fallback" style="display: none;">';
            html += '<div class="map-error">Map image failed to load. <a href="' + data.map_image_url + '" target="_blank">Click here to view map</a></div>';
            html += '</div>';
            html += '</div>';
        }
        
        // Notifications
        if (data.notifications && data.notifications.length > 0) {
            html += '<div class="notifications">';
            $.each(data.notifications, function(index, notification) {
                html += '<div class="notification">' + notification + '</div>';
            });
            html += '</div>';
        }
        
        // Flight results - using Bootstrap-based design like the client's website
        if (data.available_flights && data.available_flights.length > 0) {
            html += '<div class="row row-cols-sm-2 row-cols-md-3 row-cols-lg-3 g-4 my-4 justify-content-center">';

            $.each(data.available_flights, function(index, flight) {
                console.log('Processing flight:', flight); // Debug each flight
                
                // Extract flight data with proper fallbacks
                var planeName = flight.plane_name || flight.plane || 'Aircraft';
                var planeDescription = flight.plane_description || flight.description || '';
                var totalPrice = flight.total_price || flight.computed_price || flight.price || '';
                var pricePerHead = flight.price_per_head || flight.price_per_person || '';
                
                // Convert speed from knots to km/h for display (multiply by 1.852)
                var speedKnots = flight.speed || 0;
                var cruiseSpeed = speedKnots ? Math.round(speedKnots * 1.852) + ' km/h' : '';
                
                var maxLoad = flight.max_load || flight.number_of_seats || flight.seats || '';
                var duration = flight.duration || flight.flight_duration || '';
                
                // Round distance to whole number and add km
                var distanceRaw = flight.distance || flight.distance_km || 0;
                var distance = distanceRaw ? Math.round(distanceRaw) + ' km' : '';
                
                var bookingUrl = flight.booking_url || flight.book_url || '';
                
                // Extract departure and return dates from the form data or flight data
                var departureDate = flight.departure_date || $('#depart_date').val() || '';
                var returnDate = flight.return_date || $('#return_date').val() || '';
                
                // Format dates to match client's format (e.g., "Sept. 9, 2025")
                function formatDisplayDate(dateStr) {
                    if (!dateStr) return '';
                    
                    try {
                        // Parse DD/MM/YYYY format
                        var parts = dateStr.split('/');
                        if (parts.length === 3) {
                            var day = parseInt(parts[0], 10);
                            var month = parseInt(parts[1], 10) - 1; // Month is 0-indexed
                            var year = parseInt(parts[2], 10);
                            
                            var date = new Date(year, month, day);
                            var months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun',
                                         'Jul', 'Aug', 'Sept', 'Oct', 'Nov', 'Dec'];
                            
                            return months[date.getMonth()] + '. ' + day + ', ' + year;
                        }
                    } catch (e) {
                        console.error('Date formatting error:', e);
                    }
                    return dateStr;
                }
                
                var formattedDepartureDate = formatDisplayDate(departureDate);
                var formattedReturnDate = formatDisplayDate(returnDate);
                
                // Handle plane images - support multiple images for carousel
                var planeImages = [];
                if (flight.plane_images && Array.isArray(flight.plane_images)) {
                    planeImages = flight.plane_images;
                } else if (flight.plane_image_urls && Array.isArray(flight.plane_image_urls)) {
                    planeImages = flight.plane_image_urls;
                } else if (flight.plane_image_urls && typeof flight.plane_image_urls === 'string') {
                    planeImages = flight.plane_image_urls.split(',').map(function(url) { return url.trim(); });
                } else if (flight.plane_image) {
                    planeImages = [flight.plane_image];
                }

                html += '<div class="col mb-3">';
                html += '<div class="card shadow">';
                
                // Image carousel section
                if (planeImages.length > 0) {
                    var sliderId = 'swiper-' + index;
                    html += '<div class="swiper plane-image-slides" id="' + sliderId + '">';
                    html += '<div class="swiper-wrapper">';
                    
                    $.each(planeImages, function(imgIndex, imageUrl) {
                        var activeClass = imgIndex === 0 ? ' swiper-slide-active' : '';
                        html += '<div class="swiper-slide' + activeClass + '">';
                        html += '<img src="' + imageUrl + '" class="card-img-top ratio ratio-16x9" alt="' + planeName + '" loading="lazy" onerror="this.style.display=\'none\'">';
                        html += '<div class="card-img-overlay text-end">';
                        if (planeDescription) {
                            html += '<i class="fa-solid fa-circle-info" data-bs-toggle="popover" data-bs-content="' + planeDescription + '"></i>';
                        }
                        html += '</div>';
                        html += '</div>';
                    });
                    
                    html += '</div>';
                    if (planeImages.length > 1) {
                        html += '<div class="swiper-button-prev"></div>';
                        html += '<div class="swiper-button-next"></div>';
                    }
                    html += '</div>';
                } else {
                    // Fallback for flights without images
                    html += '<div class="no-image-placeholder">';
                    html += '<div class="placeholder-content">';
                    html += '<i class="fa-solid fa-plane" style="font-size: 48px; color: #ccc;"></i>';
                    html += '<p style="color: #666; margin-top: 10px;">' + planeName + '</p>';
                    html += '</div>';
                    html += '</div>';
                }
                
                // Card body
                html += '<div class="card-body d-flex flex-column justify-content-end">';
                
                // Dates - show only if available
                if (formattedDepartureDate) {
                    html += '<h6>Departure: ' + formattedDepartureDate + '</h6>';
                }
                if (formattedReturnDate) {
                    html += '<h6>Return: ' + formattedReturnDate + '</h6>';
                }
                
                // Plane name
                html += '<h6 class="card-title text-center my-3">' + planeName + '</h6>';
                
                // Plane properties
                html += '<div class="row row-cols-4 plane-properties justify-content-center">';
                
                if (cruiseSpeed) {
                    html += '<div class="col">';
                    html += '<span class="property-name">Cruise Speed</span>';
                    html += '<span class="property-value">' + cruiseSpeed + '</span>';
                    html += '</div>';
                }
                
                if (maxLoad) {
                    html += '<div class="col">';
                    html += '<span class="property-name">Max Load</span>';
                    html += '<span class="property-value">' + maxLoad + '<i class="fa-solid fa-user"></i></span>';
                    html += '</div>';
                }
                
                if (duration) {
                    html += '<div class="col">';
                    html += '<span class="property-name">Duration</span>';
                    html += '<span class="property-value">' + duration + '</span>';
                    html += '</div>';
                }
                
                if (distance) {
                    html += '<div class="col">';
                    html += '<span class="property-name">Distance</span>';
                    html += '<span class="property-value">' + distance + '</span>';
                    html += '</div>';
                }
                
                html += '</div>';
                html += '<hr>';
                
                // Pricing section
                html += '<div class="text-center">';
                html += '<span class="property-name fs-4">Total Charter Price</span>';
                
                if (totalPrice) {
                    html += '<p class="card-text flight-price fs-3">' + totalPrice + '<small class="excl-vat">excl. VAT</small></p>';
                }
                
                if (pricePerHead) {
                    html += '<div class="my-2 d-flex flex-column">';
                    html += '<span class="property-name fs-6">Price per head is <span class="flight-price fs-6">' + pricePerHead + '</span></span>';
                    html += '<em class="mb-3 price-per-head-info">Charter bookings are for whole aircraft.<br>Price per head is for a full plane and only shown as a guide.</em>';
                    html += '</div>';
                }
                
                // Booking button
                if (bookingUrl) {
                    console.log('Adding booking button for flight:', planeName, 'URL:', bookingUrl);
                    html += '<a href="' + bookingUrl + '" class="btn btn-outline-primary px-4" target="_blank" rel="noopener">Book Flight</a>';
                } else {
                    console.log('No booking URL for flight:', planeName);
                }
                
                html += '</div>';
                html += '</div>'; // card-body
                html += '</div>'; // card
                html += '</div>'; // col
            });

            html += '</div>'; // row
        } else {
            html += '<div class="no-flights">No flights available for the selected criteria.</div>';
        }

        html += '</div>';
        resultsDiv.html(html);

        // Initialize Swiper carousels for multiple images
        if (typeof Swiper !== 'undefined') {
            setTimeout(function() {
                $('.plane-image-slides').each(function() {
                    new Swiper(this, {
                        navigation: {
                            nextEl: '.swiper-button-next',
                            prevEl: '.swiper-button-prev',
                        },
                        loop: true,
                        autoplay: {
                            delay: 5000,
                        },
                    });
                });
            }, 100);
        }

        // Initialize Bootstrap popovers for plane descriptions
        if (typeof bootstrap !== 'undefined') {
            setTimeout(function() {
                $('[data-bs-toggle="popover"]').each(function() {
                    new bootstrap.Popover(this);
                });
            }, 100);
        }

        // Handle map image loading
        if (data.map_image_url) {
            var $mapImg = $('.route-map-img');
            var imageLoadTimeout = setTimeout(function() {
                console.warn('Map image loading timeout - showing fallback');
                $mapImg.hide();
                $('.map-fallback').show();
            }, 10000);

            $mapImg.on('load', function() {
                console.log('Map image loaded successfully');
                clearTimeout(imageLoadTimeout);
                $(this).siblings('.map-fallback').hide();
                $(this).show();
            }).on('error', function() {
                console.error('Map image failed to load:', this.src);
                clearTimeout(imageLoadTimeout);
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