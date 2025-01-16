jQuery(document).ready(function($) {
    function initElectorateLookup() {
        if (!window.google || !window.google.maps || !window.google.maps.places) {
            setTimeout(initElectorateLookup, 100);
            return;
        }

        $('.gfield_electorate_lookup').each(function() {
            const input = this;
            const $input = $(input);
            const resultsDiv = $(`#${input.id}_geocode_results`);
            const boundaryType = $input.data('boundary-type') || 'CED';
            const boundaryConfig = window.gfElectorateLookupConfig?.boundaries?.[boundaryType];
            const stateRestrictions = $input.data('state-restrictions');
            const formId = $input.closest('form').attr('id').replace('gform_', '');
            const fieldId = input.id.split('_').pop();

            if (!boundaryConfig) {
                console.error('No boundary configuration found for type:', boundaryType);
                return;
            }

            // Create a hidden input for the actual value
            let hiddenInput = $(`#${input.id}_hidden`);
            if (hiddenInput.length === 0) {
                hiddenInput = $('<input>', {
                    type: 'hidden',
                    id: `${input.id}_hidden`,
                    name: $input.attr('name'),
                    class: 'gform_hidden'
                }).insertAfter($input);
                
                // Remove the name attribute from the visible input
                $input.removeAttr('name');
            }

            // Set up autocomplete options
            const autocompleteOptions = {
                fields: ['geometry', 'formatted_address', 'address_components'],
                componentRestrictions: { country: 'au' }
            };

            // Add state restrictions if specified
            if (stateRestrictions && stateRestrictions.length > 0) {
                const stateMapping = {
                    'NSW': 'New South Wales',
                    'VIC': 'Victoria',
                    'QLD': 'Queensland',
                    'WA': 'Western Australia',
                    'SA': 'South Australia',
                    'TAS': 'Tasmania',
                    'ACT': 'Australian Capital Territory',
                    'NT': 'Northern Territory'
                };

                // Create state restriction bounds
                autocompleteOptions.bounds = new google.maps.LatLngBounds();
                autocompleteOptions.strictBounds = true;

                // Add bounds for selected states
                const stateBounds = {
                    'NSW': { south: -37.505, north: -28.157, west: 141.002, east: 153.554 },
                    'VIC': { south: -39.159, north: -34.003, west: 140.962, east: 149.977 },
                    'QLD': { south: -29.178, north: -9.142, west: 137.996, east: 153.555 },
                    'WA': { south: -35.135, north: -13.689, west: 112.921, east: 129.002 },
                    'SA': { south: -38.063, north: -25.996, west: 129.001, east: 141.003 },
                    'TAS': { south: -43.599, north: -39.592, west: 143.818, east: 148.507 },
                    'ACT': { south: -35.921, north: -35.124, west: 148.762, east: 149.399 },
                    'NT': { south: -26.001, north: -10.966, west: 129.001, east: 138.001 }
                };

                stateRestrictions.forEach(state => {
                    const bounds = stateBounds[state];
                    if (bounds) {
                        const stateBound = new google.maps.LatLngBounds(
                            new google.maps.LatLng(bounds.south, bounds.west),
                            new google.maps.LatLng(bounds.north, bounds.east)
                        );
                        autocompleteOptions.bounds.union(stateBound);
                    }
                });
            }

            const autocomplete = new google.maps.places.Autocomplete(input, autocompleteOptions);

            function updateGformValue(value) {
                // Update hidden input value
                hiddenInput.val(value);
                
                // Trigger events for Gravity Forms and Populate Anything
                hiddenInput.trigger('change').trigger('input');
                
                // Trigger custom event for other potential listeners
                $input.trigger('electorate_lookup_updated', [value]);
            }

            // Prevent typing from triggering updates
            $input.on('input change', function(e) {
                e.stopPropagation();
                // Clear results when user starts typing again
                resultsDiv.empty();
            });

            let abortController = null;

            autocomplete.addListener('place_changed', async function() {
                const place = autocomplete.getPlace();

                // Clear any existing requests
                if (abortController) {
                    abortController.abort();
                }
                abortController = new AbortController();

                if (!place.geometry) {
                    resultsDiv.html('<div class="geocode-error">No location data available for this address</div>');
                    updateGformValue('');
                    return;
                }

                // Verify state restriction if enabled
                if (stateRestrictions && stateRestrictions.length > 0) {
                    const stateComponent = place.address_components.find(component => 
                        component.types.includes('administrative_area_level_1')
                    );

                    if (!stateComponent || !stateRestrictions.includes(stateComponent.short_name)) {
                        resultsDiv.html('<div class="geocode-error">Please select an address within the allowed states/territories</div>');
                        updateGformValue('');
                        return;
                    }
                }

                const lat = place.geometry.location.lat();
                const lng = place.geometry.location.lng();

                resultsDiv.html('<div class="geocode-loading">Finding your electorate...</div>');

                const absUrl = `${boundaryConfig.url}?geometry=${lng},${lat}&geometryType=esriGeometryPoint&inSR=4326&spatialRel=esriSpatialRelIntersects&outFields=*&returnGeometry=false&f=json`;

                try {
                    const response = await fetch(absUrl, {
                        signal: abortController.signal
                    });
                    
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }

                    const rawText = await response.text();
                    const data = JSON.parse(rawText);

                    if (!data.features || data.features.length === 0) {
                        throw new Error('No boundary data found');
                    }

                    const feature = data.features[0].attributes;
                    let boundaryName = feature[boundaryConfig.nameField];
                    
                    if (!boundaryName) {
                        const lcField = boundaryConfig.nameField.toLowerCase();
                        boundaryName = feature[lcField];
                        
                        if (!boundaryName) {
                            const baseField = lcField.replace(/_\d{4}$/, '');
                            boundaryName = feature[baseField];
                        }
                    }

                    if (boundaryName) {
                        updateGformValue(boundaryName);
                        resultsDiv.html(`<div class="geocode-success">Electorate found: ${boundaryName}</div>`);
                    } else {
                        throw new Error('Unable to determine boundary name');
                    }
                } catch (error) {
                    // Don't show error if it was due to abort
                    if (error.name === 'AbortError') {
                        return;
                    }

                    console.error('Error with ABS API:', error);
                    updateGformValue('');
                    resultsDiv.html('<div class="geocode-error">Unable to find electorate. Please try again or contact support if the issue persists.</div>');
                } finally {
                    abortController = null;
                }
            });

            // Prevent form submission on enter
            $input.on('keydown', function(event) {
                if (event.keyCode === 13) {
                    event.preventDefault();
                }
            });
        });
    }

    // Initialize on page load
    initElectorateLookup();

    // Initialize on form render
    $(document).on('gform_post_render', function() {
        initElectorateLookup();
    });
});