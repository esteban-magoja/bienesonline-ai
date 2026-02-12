<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Property Translations - English
    |--------------------------------------------------------------------------
    |
    | Real estate property related translations
    |
    */

    // Property types
    'types' => [
        'house' => 'House',
        'apartment' => 'Apartment',
        'office' => 'Office',
        'commercial' => 'Commercial Space',
        'land' => 'Land',
        'field' => 'Field',
        'farm' => 'Farm',
        'warehouse' => 'Warehouse',
    ],

    // Transaction types
    'transaction_types' => [
        'sale' => 'Sale',
        'rent' => 'Rent',
        'temporary_rent' => 'Temporary Rent',
    ],

    // Property features
    'features' => [
        'bedrooms' => 'Bedrooms',
        'bedrooms_short' => 'bed.',
        'bathrooms' => 'Bathrooms',
        'bathrooms_short' => 'bath.',
        'garage' => 'Garage',
        'garages' => 'Garages',
        'parking_spaces' => 'Parking Spaces',
        'parking_short' => 'parking',
        'covered_area' => 'Covered Area',
        'covered_area_short' => 'Area (m²)',
        'land_area' => 'Land Area',
        'pool' => 'Pool',
        'garden' => 'Garden',
        'balcony' => 'Balcony',
        'terrace' => 'Terrace',
        'furnished' => 'Furnished',
        'pets_allowed' => 'Pets Allowed',
    ],

    // Currencies
    'currencies' => [
        'USD' => 'US Dollars (USD)',
        'ARS' => 'Argentine Pesos (ARS)',
        'EUR' => 'Euros (EUR)',
    ],

    // UI labels and texts
    'in' => 'in',
    'for' => 'for',
    'all' => 'All',
    'search_properties' => 'Search Properties',
    'property_detail' => 'Property Detail',
    'view_details' => 'View Details',
    'contact_owner' => 'Contact Owner',
    'share_property' => 'Share Property',
    'related_properties' => 'Related Properties',
    'price' => 'Price',
    'location' => 'Location',
    'description' => 'Description',
    'characteristics' => 'Characteristics',
    'amenities' => 'Amenities',
    'map' => 'Map',
    'images' => 'Images',
    'contact_form' => 'Contact Form',
    
    // Search
    'search' => 'Search',
    'search_placeholder' => 'E.g: Modern house with garden in Córdoba',
    'filter_by_country' => 'Filter by Country',
    'select_country' => 'Select a country',
    'search_results' => 'Search Results',
    'no_results' => 'No properties found',
    'showing_results' => 'Showing :count results',
    'similarity_score' => 'Match',
    
    // Contact form
    'your_name' => 'Your Name',
    'your_email' => 'Your Email',
    'your_phone' => 'Your Phone',
    'your_message' => 'Your Message',
    'send_message' => 'Send Message',
    'call_now' => 'Call Now',
    'whatsapp_contact' => 'Contact via WhatsApp',
    
    // Status
    'available' => 'Available',
    'sold' => 'Sold',
    'rented' => 'Rented',
    'reserved' => 'Reserved',
    
    // Location
    'country' => 'Country',
    'province' => 'Province',
    'city' => 'City',
    'neighborhood' => 'Neighborhood',
    'address' => 'Address',
    
    // Units
    'sqm' => 'sqm',
    'per_month' => 'per month',
    
    // Messages
    'message_sent' => 'Message sent successfully',
    'message_error' => 'Error sending message',
    'loading' => 'Loading...',
    
    // Advanced search
    'search_all_listings' => 'Search All Listings',
    'no_listings_found' => 'No listings found',
    'adjust_search_term' => 'Try adjusting your search term',
    'enter_search_term' => 'Enter a search term above to find properties',
    'similarity' => 'Similarity',
    'by' => 'By',
    'view_details' => 'View Details',
    
    // Search page
    'search_hero_title' => 'Find Your Dream Property',
    'search_hero_subtitle' => 'AI-powered intelligent search to find exactly what you need',
    
    // Search form
    'search_form' => [
        'country_label' => 'Country',
        'country_required' => '(required)',
        'select_country' => 'Select a country',
        'what_looking_for' => 'What are you looking for?',
        'min_chars' => '(minimum 5 characters)',
        'char_counter' => 'minimum characters',
        'search_button' => 'Search',
        'searching' => 'Searching...',
        'clear' => 'Clear',
        'clear_filters' => 'Clear filters',
        'check_fields' => 'Please check the following fields:',
        'searching_properties' => 'Searching properties',
        'processing_search' => 'Processing your search...',
        'placeholder' => 'E.g.: Modern house with pool in quiet area, downtown apartment...',
    ],
    
    // Search results
    'search_results' => [
        'title' => 'Search results',
        'property_found' => 'property found',
        'properties_found' => 'properties found',
        'in_country' => 'in :country',
        'for_term' => 'for ":term"',
        'no_properties_found' => 'No properties found',
        'try_different_search' => 'Try different search terms or change the selected country.',
        'relevance' => 'Relevance',
        'featured' => 'Featured',
    ],
    
    // CTA Section
    'cta' => [
        'ready_title' => 'Ready to find your next home?',
        'ready_subtitle' => 'Use our smart search to find exactly what you need',
        'smart_search_title' => 'Smart Search',
        'smart_search_desc' => 'Describe what you\'re looking for in natural language and our AI will find the best matches',
        'multiple_locations_title' => 'Multiple Locations',
        'multiple_locations_desc' => 'Explore properties in different countries and find the perfect place for you',
        'relevant_results_title' => 'Relevant Results',
        'relevant_results_desc' => 'See a relevance score for each property based on your search',
        'search_hint' => 'Start your search by typing something like: "Modern house with garden near downtown" or "Bright apartment with sea view"',
    ],
    
    // JS messages
    'js' => [
        'generating_embeddings' => 'Generating AI embeddings...',
        'analyzing_similarities' => 'Analyzing semantic similarities...',
        'sorting_results' => 'Sorting results by relevance...',
        'filtering_properties' => 'Filtering properties...',
        'smart_search_progress' => 'Smart search in progress...',
        'searching_properties' => 'Searching properties...',
        'select_country_error' => 'You must select a country.',
        'enter_search_error' => 'You must enter a search term.',
        'min_chars_error' => 'Search term must be at least 5 characters.',
    ],
    
    // Property detail
    'property_detail' => 'Property Detail',
    'property_type' => 'Property Type',
    'transaction_type' => 'Transaction Type',
    'condition' => 'Condition',
    'covered_area_sqm' => 'Covered Area (sqm)',
    'land_area_sqm' => 'Land Area (sqm)',
    'contact_advertiser' => 'Contact Advertiser',
    'whatsapp_message' => 'Hello, I\'m interested in the property: :property',
    'message_placeholder' => 'I am interested in this property...',
    'default_message' => 'Hello, I am interested in the property ":property". I would like to receive more information.',
    'send_inquiry' => 'Send Inquiry',
    'your_property' => 'This is your property',
    'related_properties' => 'Related Properties',
    
    // Public listings
    'properties' => 'Properties',
    'all_properties' => 'All Properties',
    'listings' => 'Listings',
    'browse_properties' => 'Browse Properties',
    'filter_results' => 'Filter Results',
    'sort_by' => 'Sort by',
    'filters' => 'Filters',
    'apply_filters' => 'Apply Filters',
    'clear_all_filters' => 'Clear All Filters',
    
    // Sorting
    'sort' => [
        'featured' => 'Featured',
        'newest' => 'Newest',
        'oldest' => 'Oldest',
        'price_asc' => 'Price: Low to High',
        'price_desc' => 'Price: High to Low',
        'area_asc' => 'Area: Low to High',
        'area_desc' => 'Area: High to Low',
    ],
    
    // Filters
    'filters_label' => [
        'price_range' => 'Price Range',
        'min_price' => 'Min Price',
        'max_price' => 'Max Price',
        'property_type' => 'Property Type',
        'transaction_type' => 'Transaction Type',
        'min_bedrooms' => 'Min Bedrooms',
        'min_bathrooms' => 'Min Bathrooms',
        'min_area' => 'Min Area (sqm)',
        'max_area' => 'Max Area (sqm)',
    ],
    
    // Result messages
    'results' => [
        'total' => 'Total results',
        'showing' => 'Showing :from - :to of :total results',
        'no_results_title' => 'No properties found',
        'no_results_message' => 'There are no properties matching your search criteria.',
        'try_adjusting' => 'Try adjusting the filters or searching in another location.',
        'found' => ':count property found|:count properties found',
    ],
    
    // Property types translated (for URLs)
    'house' => 'house',
    'houses' => 'houses',
    'apartment' => 'apartment',
    'apartments' => 'apartments',
    'office' => 'office',
    'offices' => 'offices',
    'commercial' => 'commercial',
    'commercials' => 'commercials',
    'land' => 'land',
    'lands' => 'lands',
    'field' => 'field',
    'fields' => 'fields',
    'farm' => 'farm',
    'farms' => 'farms',
    'warehouse' => 'warehouse',
    'warehouses' => 'warehouses',
    
    // Transaction types translated (for URLs)
    'sale' => 'sale',
    'rent' => 'rent',
    'temporary_rent' => 'temporary-rent',
    
    // User profile / realtor
    'user_profile' => [
        'title' => 'Properties by :name',
        'description' => ':count properties by :name in :location',
        'realtors' => 'Realtors',
        'various_locations' => 'various locations',
        'contact_info' => 'Contact Information',
        'agency' => 'Agency',
        'location' => 'Location',
        'phone' => 'Phone',
        'email' => 'Email',
        'statistics' => 'Statistics',
        'active_properties' => 'Active Properties',
        'properties_for_sale' => 'For Sale',
        'properties_for_rent' => 'For Rent',
        'view_all_properties' => 'View All Properties',
        'no_properties' => 'This user has no properties currently listed.',
        'contact_advertiser' => 'Contact Advertiser',
        'send_whatsapp' => 'Send WhatsApp',
        'call_now' => 'Call Now',
    ],
];
